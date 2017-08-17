<?php
namespace Solteq\Enterpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
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
      foreach ($this->methodCodes as $code) {
        $this->methods[$code] = $paymentHelper->getMethodInstance($code);
      }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
      $config = [];
      foreach ($this->methodCodes as $code) {
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
     * Get instructions text from config
     *
     * @param string $code
     * @return string
     */
    protected function getInstructions($code)
    {
      return nl2br($this->escaper->escapeHtml($this->methods[$code]->getInstructions()));
    }
}
