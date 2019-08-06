<?php
namespace Solteq\Enterpay\Model;

use Magento\Framework\Model\AbstractModel;

class Fee extends AbstractModel
{
    /**
     * Constructor
     */
    protected function _construct()
    {
      $this->_init('Solteq\Enterpay\Model\ResourceModel\Fee');
    }
}
