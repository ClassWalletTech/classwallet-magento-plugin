<?php
namespace ClassWallet\Payment\Controller\Create;

use Magento\Customer\Api\AccountManagementInterface;
use ClassWallet\Payment\Logger\Logger;

class Account extends \Magento\Framework\App\Action\Action
{

	/**
     * @var AccountManagementInterface
     */
    protected $customerAccountManagement;


	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Customer\Model\CustomerFactory $customerFactory,
    \Magento\Customer\Model\SessionFactory $customerSessionFactory,
    \Magento\Framework\Message\ManagerInterface $messageManager,
    \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
    \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory,
    \Magento\Catalog\Model\Session $catalogSession,
    Logger $logger,
    \Magento\Customer\Model\AddressFactory $addressFactory
	)
	{
      $this->storeManager       	=   $storeManager;
      $this->customerFactory    	=   $customerFactory;
      $this->customerSessionFactory    =   $customerSessionFactory;		
      $this->_messageManager 		= 	$messageManager;
      $this->addressRepository 	= 	$addressRepository;
    	$this->addressDataFactory = 	$addressDataFactory;
      $this->catalogSession     =   $catalogSession;
      $this->logger             =   $logger;
      $this->addressFactory     =   $addressFactory;
		return parent::__construct($context);
	}

	public function execute()
	{
    	  $formData 	  = 	$this->getRequest()->getPostValue();
        $isTest       =   $this->getRequest()->getParam('test');

        if($isTest == 1 && empty($formData)){
          $formData     =   array(
                            "email" =>  "stephen.kidwell@gmail.com",  
                            "institution" =>  "ClassWallet School", 
                            "username" => "Stephen Kidwell",  
                            "shipping" => array(
                                            "address"   =>  "19802 Echo Drive",
                                            "city"    =>  "Strongsville", 
                                            "state"   =>  "Ohio", 
                                            "zip"     =>  "44149",
                                            "country" =>  'US',
					    "phone"   =>  '444-444-4444',
					    'state_id'=>  '47'
                                          )
                          );  
        }		  

        try{

          	if(empty($formData) || !is_array($formData)){
              	throw new \Exception("Please provide valid data.");
          	}

          	if(!isset($formData['email']) || empty($formData['email'])){
              	throw new \Exception("Please provide valid customer data.");
          	}
          
	        $customer 	=	$this->ifEmailExist($formData['email']);

	        if(empty($customer) || empty($customer->getData())){
	        	$response       =   $this->createCustomer($formData);	
	        	if(is_numeric($response)){
	        		$customerId      	=   $response;
	        		$customer        	=   $this->customerFactory->create()->load($customerId);
	              	if(isset($formData['shipping']) && !empty($formData['shipping'])){
	              		$addressRes 	=	$this->createCustomerAddress($customerId, $formData);	
	              	}
	        	}else{
              		throw new \Exception($response);
          		}
	        }

          $customerSession =   $this->customerSessionFactory->create();
          $customerSession->setCustomerAsLoggedIn($customer);

          if(!$customerSession->isLoggedIn()) {
              throw new \Exception("Unable to login customer");
          }

          $this->catalogSession->setIsClasswalletLogin(true);

        }catch(\Exception $e){
            $this->_messageManager->addErrorMessage($e->getMessage());
        }

        $this->_redirect('customer/account');  

	}

    protected function createCustomer($customerData){
      	try{
      		$nameArr 	=	explode(' ', $customerData['username']);
      		$fName 		=	$lName 		=	$customerData['username'];

      		if(isset($nameArr[0])){
				$fName 		=	$nameArr[0];      			
      		}

      		if(isset($nameArr[1])){
				$lName 		=	$nameArr[1];      			
      		}

      		$defaultPassword 	=	'User@123#';

          	$websiteId  = $this->storeManager->getWebsite()->getWebsiteId();
          	$customer   = $this->customerFactory->create();
          	$customer->setWebsiteId($websiteId);
          	$customer->setEmail($customerData['email']); 
          	$customer->setFirstname($fName);
         	$customer->setLastname($lName);
          	$customer->setPassword($defaultPassword);
          	$customer->save();
          	return $customer->getId();
      	}catch(\Exception $e){
          	return $e->getMessage();        
      	}
    }

    protected function createCustomerAddress($customerId, $customerData){
      	try{
      		$nameArr 	=	explode(' ', $customerData['username']);
      		$fName 		=	$lName 		=	$customerData['username'];

      		if(isset($nameArr[0])){
				    $fName 		=	$nameArr[0];      			
      		}

      		if(isset($nameArr[1])){
				    $lName 		=	$nameArr[1];      			
      		}

          $address = $this->addressFactory->create();
          $address->setCustomerId($customerId)
                  ->setFirstname($fName)
                  ->setLastname($lName)
                  ->setPostcode($customerData['shipping']['zip'])
                  ->setCity($customerData['shipping']['city'])
                  ->setRegion($customerData['shipping']['state'])
                  ->setCompany('')
                  ->setStreet($customerData['shipping']['address'])
                  ->setIsDefaultBilling(false)
                  ->setIsDefaultShipping('1')
                  ->setSaveInAddressBook('1');

          if(isset($customerData['shipping']['country'])){
              $address->setCountryId($customerData['shipping']['country']);
          }

          if(isset($customerData['shipping']['phone'])){
              $address->setTelephone($customerData['shipping']['phone']);
	  }          

	  if(isset($customerData['shipping']['state_id'])){
              $address->setRegionId($customerData['shipping']['state_id']);
          }

      		$address->save();

      	}catch(\Exception $e){
            $this->logger->info("Error in creating address. Error is {$e->getMessage()}");
          	return $e->getMessage();        
      	}
    }

    protected function ifEmailExist($email){
    	$websiteID 		 = 	 $this->storeManager->getStore()->getWebsiteId();
	    $customer        =   $this->customerFactory->create()->setWebsiteId($websiteID)->loadByEmail($email);
	    return $customer;
    }

}
