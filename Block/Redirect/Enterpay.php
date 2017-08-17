<?php
namespace Solteq\Enterpay\Block\Redirect;

class Enterpay extends \Magento\Framework\View\Element\AbstractBlock {
  protected $_url;
  protected $_params;
  protected $_form;
  protected $_formId = 'enterpay_form';

  public function __construct(
    \Magento\Framework\Data\Form $form,
    \Magento\Framework\View\Element\Context $context,
    array $data = []
  ) {
    $this->_form = $form;
    parent::__construct($context, $data);
  }

  public function setUrl($url)
  {
    $this->_url = $url;
    return $this;
  }

  public function setParams($params)
  {
    $this->_params = $params;
    return $this;
  }

  protected function _toHtml()
  {
    $this->_form->setAction($this->_url)
      ->setId($this->_formId)
      ->setName($this->_formId)
      ->setMethod('POST')
      ->setUseContainer(true);

    foreach ($this->_params as $key => $value) {
      $this->_form->addField($key, 'text', [
        'name' => $key,
        'value' => $value
      ]);
    }

    return $this->_form->toHtml() . $this->_jsSubmit();
  }

  protected function _jsSubmit()
  {
    return '<script type="text/javascript">document.getElementById("' . $this->_formId . '").submit();</script>';
  }
}
