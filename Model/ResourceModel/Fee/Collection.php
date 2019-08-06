<?php
namespace Solteq\Enterpay\Model\ResourceModel\Fee;

use Magento\Sales\Model\ResourceModel\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @return void
     */
    protected function _construct()
    {
      $this->_init('Solteq\Enterpay\Model\Fee', 'Solteq\Enterpay\Model\ResourceModel\Fee');
    }
}
