<?php

namespace Solteq\Enterpay\Block\Sales;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\ScopeInterface;
use Solteq\Enterpay\Helper\FeeHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Totals extends Template
{
    /**
     * @var FeeHelper
     */
    protected $feeHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
      Template\Context $context,
      FeeHelper $feeHelper,
      ScopeConfigInterface $scopeConfig,
      array $data = []
    ) {
      parent::__construct($context, $data);
      $this->feeHelper = $feeHelper;
      $this->scopeConfig = $scopeConfig;
    }

    /**
     * Add invoice fee to email totals
     */
    public function initTotals()
    {
      $parent = $this->getParentBlock();
      $source = $parent->getSource();

      if ($this->getShowFee($source->getStoreId()) && $fee = $this->getFee($source)) {
        $feeValue = $this->getFeeValueToShow($source, $fee);

        if ($feeValue > 0) {
          $parent->addTotal(new \Magento\Framework\DataObject([
            'code' => $fee->getData('product_id'),
            'label' => __('+ ' . $fee->getData('description')),
            'value' => $feeValue,
            'base_value' => $fee->getData('base_amount'),
          ]), 'grand_total');
        }
      }
    }

    /**
     * Get show fee in emails setting
     *
     * @param $storeId
     * @return mixed
     */
    protected function getShowFee($storeId)
    {
      $showFee = $this->scopeConfig->getValue(
        'payment/enterpay/show_fee_in_emails',
        ScopeInterface::SCOPE_STORE,
        $storeId
      );

      return $showFee;
    }

    /**
     * Get the Payment from source
     *
     * @param $source
     * @return \Magento\Sales\Api\Data\OrderPaymentInterface|null
     */
    protected function getPayment($source)
    {
      $payment = null;
      if ($source instanceof OrderInterface) {
        $payment = $source->getPayment();
      } elseif ($source instanceof InvoiceInterface || $source instanceof CreditmemoInterface) {
        $payment = $source->getOrder()->getPayment();
      }
      return $payment;
    }

    /**
     * Get the fee model
     *
     * @param $source
     * @return mixed|\Solteq\Enterpay\Model\Fee|null
     */
    protected function getFee($source)
    {
      try {
        $payment = $this->getPayment($source);
        $fee = $this->feeHelper->getPaymentFee($payment);
        return $fee;
      } catch (LocalizedException $e) {
        return null;
      }
    }

    /**
     * Get the fee value to show in email
     *
     * @param $source
     * @param $fee
     * @return int|float|string
     */
    protected function getFeeValueToShow($source, $fee)
    {
      $value = 0;
      if ($source instanceof Order) {
        $value = $fee->getData('base_amount');
      } elseif ($source instanceof Creditmemo) {
        $value = $fee->getData('base_amount_refunded');
      } elseif ($source instanceof Invoice) {
        $value = $fee->getData('base_amount_authorized');
      }
      return $value;
    }
}
