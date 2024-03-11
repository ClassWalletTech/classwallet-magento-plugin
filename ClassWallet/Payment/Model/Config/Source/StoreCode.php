<?php
namespace ClassWallet\Payment\Model\Config\Source;

class StoreCode implements \Magento\Framework\Data\OptionSourceInterface { 
	public function toOptionArray() {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->create('\Magento\Store\Model\StoreManagerInterface');
		$storeCodes[] = ['value'=>'default', 'label'=>'default'];
		$stores = $storeManager->getStores(true, true);
		foreach($stores as $store) {
			$storeCodes[] = ['value'=>$store->getCode(), 'label'=>$store->getName()];
		}
		return $storeCodes;
	}
}

