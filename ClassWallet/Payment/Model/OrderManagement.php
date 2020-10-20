<?php 
namespace ClassWallet\Payment\Model;

use \ClassWallet\Payment\Api\OrderInterface;
use Magento\Sales\Model\Order;
use ClassWallet\Payment\Logger\Logger;

class OrderManagement implements OrderInterface
{

  	/**
   	* @var \Magento\Framework\App\RequestInterface
   	*/
  	protected $request;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService; 

    private $logger;   

  	/**
   	* @param \Magento\Framework\App\RequestInterface $request
   	*/
  	public function __construct(
      	\Magento\Framework\App\RequestInterface $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        Logger $logger
  	)
  	{
      	$this->request            =   $request;
        $this->storeManager       =   $storeManager;
        $this->orderRepository    =   $orderRepository;
        $this->orderFactory       =   $orderFactory;
        $this->_invoiceService    =   $invoiceService;
        $this->_transaction       =   $transaction;
        $this->invoiceSender      =   $invoiceSender;
        $this->transactionRepository = $transactionRepository;
        $this->logger             =   $logger;
  	}

  	public function getOrder($orderId) {
      	
      try{
          $orderData = $this->orderRepository->get($orderId);

          $orderItems             =   array();

          foreach ($orderData->getAllItems() as $item) {
              $orderItems[]   =   array(
                                      'itemId'        => $item->getItemId(),
                                      'description'   => $item->getName(),  
                                      'quantity'      => $item->getQtyOrdered(),  
                                      'price'         => $item->getPrice()
                                  );
          }

          $returnData                =  array(
                                              'orderId' =>  $orderData->getEntityId(),
                                              'details' =>  array(
                                                              'items' => $orderItems,
                                                              'shipping' => $orderData->getShippingAmount(),
                                                              'tax' => $orderData->getTaxAmount()
                                                            )
                                        );
             
          return json_encode($returnData);
      }catch(\Exception $e){
          return $e->getMessage();
      }  
  	}

    public function setClasswalletOrder($orderId) {
        
        $data         =   $this->request->getContent();
        $postData     =   json_decode($data, true);
        $message      =   '';

        if(isset($postData['status'])){
            
            $status       =   $postData['status'];  
            
            if($status != 'failed'){
                $purchaseOrderId  =   $postData['PurchaseOrderId'];
                $invoiceResult    =   $this->markOrderComplete($orderId, $purchaseOrderId);
                return $invoiceResult;
            }else{
                if(isset($postData['message'])){
                  $message      =   $postData['message'];    
                }                
                return "Payment failed due to error {$message}";
            }

        }        

    }  

    protected function markOrderComplete($orderId, $purchaseOrderId){
        $orderData = $this->orderRepository->get($orderId);

        try{

            if(!$orderData->canInvoice()) {
              throw new \Exception("Unable to create invoices.");
            }

            $payment = $orderData->getPayment();
            $payment->setTransactionId($purchaseOrderId);
                
            $orderData->setState(Order::STATE_PROCESSING)->setStatus($orderData->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
              
            $orderData->setTotalPaid($orderData->getGrandTotal());
            $orderData->setBaseTotalPaid($orderData->getBaseGrandTotal());

            $transaction = $this->transactionRepository->getByTransactionId(
                "-1",
                $payment->getId(),
                $orderData->getId()
            );

            if($transaction) {
              $transaction->setTxnId($purchaseOrderId);
              $transaction->setAdditionalInformation(  
                "ClassWallet Transaction Id",$purchaseOrderId
              );
              $transaction->setAdditionalInformation(  
                "status","successful"
              );
              $transaction->setIsClosed(1);
              $transaction->save(); 
            }
              //exit;
              
            $payment->addTransactionCommentsToOrder(
              $transaction,
               "Transaction is completed successfully"
            );
            $payment->setParentTransactionId(null); 
            $additionalInfo   = array(
                                   "method_title" => "Class Wallet",
                                   "purchase_order_id" => $purchaseOrderId
                                );

            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => $additionalInfo]
            );  
              
            $payment->save();
            $orderData->save();
              
            $invoice = $this->_invoiceService->prepareInvoice($orderData);
            $invoice->register();
            $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
            $transactionSave = $this->_transaction->addObject($invoice)->addObject($orderData);
            $transactionSave->save();
            $invoice->save();

            $orderData->setState(\Magento\Sales\Model\Order::STATE_COMPLETE);
            $orderData->setStatus(\Magento\Sales\Model\Order::STATE_COMPLETE);
            $orderData->addStatusToHistory($orderData->getStatus(), 'Order completed successfully');
            $orderData->save();

            return "Order Completed";

        }catch(\Exception $e){
            return $e->getMessage();
        }

    }  
}