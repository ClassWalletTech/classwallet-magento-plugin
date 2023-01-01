<?php
namespace ClassWallet\Payment\Model\Config\Source;

use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Framework\App\Helper\Context;

class CustomerGroup implements \Magento\Framework\Data\OptionSourceInterface
{
	protected $customerGroup;

	public function __construct(
		Context $context,
		CustomerGroupCollection $customerGroup
	) {
		$this->customerGroup = $customerGroup;
	}

	public function toOptionArray()
	{
		return $this->customerGroup->toOptionArray();
	}
}
?>
