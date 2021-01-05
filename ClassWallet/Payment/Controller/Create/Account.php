<?php
namespace ClassWallet\Payment\Controller\Create;

use Magento\Customer\Api\AccountManagementInterface;
use ClassWallet\Payment\Logger\Logger;

class Account extends \Magento\Framework\App\Action\Action
{

  CONST DEFAULT_PHONE   =   'XXX-XXX-XXXX';

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
    \Magento\Customer\Model\AddressFactory $addressFactory,
    \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionColl,
    \Magento\Framework\Data\Form\FormKey $formKey,
    \Magento\Framework\App\Request\Http $request
  )
  {
      $this->storeManager         =   $storeManager;
      $this->customerFactory      =   $customerFactory;
      $this->customerSessionFactory    =   $customerSessionFactory;   
      $this->_messageManager    =   $messageManager;
      $this->addressRepository  =   $addressRepository;
      $this->addressDataFactory =   $addressDataFactory;
      $this->catalogSession     =   $catalogSession;
      $this->logger             =   $logger;
      $this->addressFactory     =   $addressFactory;
      $this->regionColl         =   $regionColl;
      $this->formKey            =   $formKey;
      $this->request            =   $request;
      $this->request->setParam('form_key', $this->formKey->getFormKey());
      return parent::__construct($context);
  }

  public function execute()
  {
        $formSData = $this->getRequest()->getPostValue(); 
        $inputData = file_get_contents('php://input');

		$this->logger->info("Got POST " . print_r($inputData, true));
        try{
			$this->logger->info('here');
            if(!empty($formSData) && isset($formSData['data'])){
              	$jsonData   = $formSData['data'];
              	$formData   = json_decode($jsonData, true);
            }elseif(!empty($formSData) && isset($formSData['request'])){
              	$jsonData   = $formSData['request'];
              	$formData   = json_decode($jsonData, true);
            }else{
                throw new \Exception("Please provide valid data.");
            }
            if(empty($formData) || !is_array($formData)){
                throw new \Exception("Please provide valid data.");
            }

            if(!isset($formData['email']) || empty($formData['email'])){
                throw new \Exception("Please provide valid customer data.");
            }
         	$this->logger->info('Checking customer exisitence'); 
          	$customer   = $this->ifEmailExist($formData['email']);	

          	if(empty($customer) || empty($customer->getData())){
				$this->logger->info('Creating customer with email ' . $formData['email']);
            	$regionData   = $this->getRegionId($formData['shipping']['state']);

				if(!empty($regionData) && $regionData->getRegionId()){
					$formData['shipping']['state_id']      =   $regionData->getRegionId();
					$formData['shipping']['state']         =   $regionData->getCode();
					$formData['shipping']['country']       =   $regionData->getCountryId();
				}

				$response       =   $this->createCustomer($formData); 

				if(is_numeric($response)){
					$customerId       =   $response;
				  	$customer         =   $this->customerFactory->create()->load($customerId);
					if(isset($formData['shipping']) && !empty($formData['shipping'])){
						$addressRes   = $this->createCustomerAddress($customerId, $formData); 
					}
				}else{
					throw new \Exception($response);
			 	}
          } else {
		      $this->logger->info('Customer exists with email '. $formData['email']);
		  }

          $customerSession =   $this->customerSessionFactory->create();
          $customerSession->setCustomerAsLoggedIn($customer);

          if(!$customerSession->isLoggedIn()) {
			  $this->logger->info('Could not login customer. Throwing exception');
              throw new \Exception("Unable to login customer");
          }

          $this->catalogSession->setIsClasswalletLogin(true);

        }catch(\Exception $e){
			$this->logger->info('Caught exception ' . $e->getMessage());
            $this->logger->info(var_export($inputData, true));
            $this->logger->info(var_export($formSData, true));
            $this->_messageManager->addErrorMessage($e->getMessage());
        }

        $this->_redirect('customer/account');  

  }

    protected function createCustomer($customerData){
        try{
          $nameArr  = explode(' ', $customerData['username']);
          $fName    = $lName    = $customerData['username'];

          if(isset($nameArr[0])){
        $fName    = $nameArr[0];            
          }

          if(isset($nameArr[1])){
        $lName    = $nameArr[1];            
          }

          $defaultPassword  = 'User@123#';

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
          $nameArr  = explode(' ', $customerData['username']);
          $fName    = $lName    = $customerData['username'];

          if(isset($nameArr[0])){
            $fName    = $nameArr[0];            
          }

          if(isset($nameArr[1])){
            $lName    = $nameArr[1];            
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

          if(isset($customerData['shipping']['state_id'])){
              $address->setRegionId($customerData['shipping']['state_id']);
          }          

          if(isset($customerData['shipping']['phone'])){
              $address->setTelephone($customerData['shipping']['phone']);
          }else{
              $address->setTelephone(SELF::DEFAULT_PHONE);
          }

          $address->save();

        }catch(\Exception $e){
            $this->logger->info("Error in creating address. Error is {$e->getMessage()}");
            return $e->getMessage();        
        }
    }

    protected function ifEmailExist($email){
      $websiteID     =   $this->storeManager->getStore()->getWebsiteId();
      $customer        =   $this->customerFactory->create()->setWebsiteId($websiteID)->loadByEmail($email);
      return $customer;
    }

    protected function getRegionId($regionCode){
      try{
          $region   =   $this->regionColl->create()->addFieldToFilter(['code', 'default_name'],
                        [
                            ['eq' => $regionCode],
                            ['eq' => $regionCode]
                        ])->getFirstItem();


          return $region;
      }catch(\Exception $e){
          
      }    
  }

}
