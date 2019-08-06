<?php
namespace Solteq\Enterpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    const PAYMENT_FEE_KEY = 'enterpay_invoice_fee';
    const SKU_PAYMENT_FEE = 'INVOICE_FEE';
    const PRICE_TOLERANCE_LEVEL = 0.01;

    /**
     * @var string[]
     */
    const PAYMENT_METHODS = [
        Enterpay::PAYMENT_METHOD_CODE,
    ];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper
    ) {
      $this->escaper = $escaper;
      foreach (self::PAYMENT_METHODS as $code) {
        $this->methods[$code] = $paymentHelper->getMethodInstance($code);
      }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
      $config = [];
      foreach (self::PAYMENT_METHODS as $code) {
        $config['payment']['instructions'][$code] = $this->getInstructions($code);
        $config['payment']['payment_redirect_url'][$code] = $this->getPaymentRedirectUrl($code);
      }
      return $config;
    }

    /**
     * Get payment redirect page URL
     *
     * @param string $code
     * @return string
     */
    protected function getPaymentRedirectUrl($code)
    {
      return $this->methods[$code]->getPaymentRedirectUrl();
    }

    /**
     * Get instructions text with fee value from config
     *
     * @param string $code
     * @return string
     */
    protected function getInstructions($code)
    {
      $instructionsString = $this->escaper->escapeHtml($this->methods[$code]->getInstructions());
      $feeValue = (float) $this->escaper->escapeHtml($this->methods[$code]->getFeeValue());
      $instructionsString = sprintf($instructionsString, $feeValue);
      return nl2br($instructionsString);
    }
}
