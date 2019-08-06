<?php
namespace Solteq\Enterpay\Helper;

use Magento\Sales\Model\Order\Payment;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\CalculationFactory;
use Solteq\Enterpay\Model\FeeFactory;
use Solteq\Enterpay\Model\Fee;
use Solteq\Enterpay\Model\ConfigProvider;
use Solteq\Enterpay\Model\Enterpay;

class FeeHelper
{
    /**
     * @var FeeFactory
     */
    protected $feeFactory;

    /**
     * @var CalculationFactory
     */
    protected $taxCalculationFactory;

    /**
     * @var Calculation
     */
    protected $taxCalculation;

    /**
     * @param FeeFactory $feeFactory
     * @param CalculationFactory $calculationFactory
     */
    public function __construct(
      FeeFactory $feeFactory,
      CalculationFactory $calculationFactory
    ) {
      $this->feeFactory = $feeFactory;
      $this->taxCalculationFactory = $calculationFactory;
    }

    /**
     * Get the payment fee from payment
     *
     * @param $payment
     * @param bool $createNewFee
     * @return mixed|Fee|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPaymentFee($payment, $createNewFee = false)
    {
      if ($payment instanceof Payment) {
        // Get fee from payment object
        $fee = $payment->getData(ConfigProvider::PAYMENT_FEE_KEY);
        if ($fee && $fee instanceof Fee) {
          return $fee;
        }

        // Get earlier created fee
        $newFee = $this->feeFactory->create();
        if ($payment->getId()) {
          /** @var Fee $fee */
          $fee = $newFee->getCollection()->addFieldToFilter('payment_id', $payment->getId())->getFirstItem();
          if ($fee && $fee->getData('entity_id')) {
            $payment->setData(ConfigProvider::PAYMENT_FEE_KEY, $fee);
            return $fee;
          }
        }

        if ($payment->getMethod() == Enterpay::PAYMENT_METHOD_CODE && $createNewFee) {
          $method = $payment->getMethodInstance();
          if ($method->getConfigData('fee_enabled')) {
            $newFee->setData('payment_id', $payment->getId());
            $newFee->setData('product_id', ConfigProvider::SKU_PAYMENT_FEE);
            $newFee->setData('description', $this->_getFeeDescription($payment));
            $newFee->setData('base_amount', round($this->_getFeeValue($payment), 2));
            $newFee->setData('tax_percent', round($this->_getPaymentFeeTaxPercent($payment)));
            $payment->setData(ConfigProvider::PAYMENT_FEE_KEY, $newFee);
            $newFee->save();
            return $newFee;
          }
        }
      }
      return null;
    }

    /**
     * Append payment fee to items
     *
     * @param $result
     * @param $fee
     * @param $amount
     * @return array
     */
    public function appendPaymentFee($result, $fee, $amount)
    {
      if ($fee && $amount > ConfigProvider::PRICE_TOLERANCE_LEVEL) {
        $itemData = [
          'identifier' => $fee->getData('product_id'),
          'name' => $fee->getData('description'),
          'quantity' => 1,
          'unit_price_including_tax' => intval(floatval($amount) * 100),
          'tax_rate' => round(floatval($fee->getData('tax_percent') / 100), 2),
        ];
        $result[] = $itemData;
      }
      return $result;
    }

    /**
     * Get fee value
     *
     * @param Payment $payment
     * @return float|int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getFeeValue(Payment $payment)
    {
      $feeValue = (float)$payment->getMethodInstance()->getConfigData('fee_value');
      return $feeValue > 0 ? $feeValue : 0;
    }

    /**
     * Get fee description
     *
     * @param Payment $payment
     * @return mixed|string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getFeeDescription(Payment $payment)
    {
      $description = $payment->getMethodInstance()->getConfigData('fee_description');
      return !empty($description) ? $description : (string)__('Invoicing Fee');
    }

    /**
     * Get fee enabled
     *
     * @param Payment $payment
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getFeeEnabled(Payment $payment)
    {
      $enabled = $payment->getMethodInstance()->getConfigData('fee_enabled');
      return $enabled;
    }

    /**
     * Get fee tax percent
     *
     * @param Payment $payment
     * @return float
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getPaymentFeeTaxPercent(Payment $payment)
    {
      if (!($this->taxCalculation)) {
        $this->taxCalculation = $this->taxCalculationFactory->create();
      }

      $order = $payment->getOrder();
      $request = $this->taxCalculation->getRateRequest(
        $order->getShippingAddress(),
        $order->getBillingAddress(),
        null,
        null,
        $order->getCustomerId()
      );

      $request->setProductClassId($payment->getMethodInstance()->getConfigData('fee_tax_class'));
      return $this->taxCalculation->getRate($request);
    }
}
