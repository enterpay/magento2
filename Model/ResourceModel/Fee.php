<?php
namespace Solteq\Enterpay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Fee extends AbstractDb
{
    /**
     * Constructor
     */
    protected function _construct()
    {
      $this->_init('enterpay_invoice_fee', 'entity_id');
    }
}
