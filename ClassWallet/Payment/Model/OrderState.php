<?php

namespace ClassWallet\Payment\Model;

class OrderState extends \Magento\Sales\Model\ResourceModel\Order\Handler\State {
	public function check(\Magento\Sales\Model\Order $order) {
		if($order->getPayment() && $order->getPayment()->getMethod() === 'classwallet') {
			if(!$order->canShip()) {
				return $this;
			}
		}
		return parent::check($order);
	}	
}
