<?php

namespace Solteq\Enterpay\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Payment method model
 */
class Enterpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_CODE = 'enterpay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_CODE;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $_httpClientFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    protected $_orderStatusCollectionFactory;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $_taxHelper;

    /**
     * Constructor
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\HTTP\ZendClientFactory $httpClientFactory,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
      parent::__construct(
          $context,
          $registry,
          $extensionFactory,
          $customAttributeFactory,
          $paymentData,
          $scopeConfig,
          $logger,
          $resource,
          $resourceCollection,
          $data
      );

      $this->_orderStatusCollectionFactory = $orderStatusCollectionFactory;
      $this->_session = $session;
      $this->_httpClientFactory = $httpClientFactory;
      $this->_urlBuilder = $urlBuilder;
      $this->_request = $request;
      $this->_taxHelper = $taxHelper;
    }

    public function initialize($paymentAction, $stateObject)
    {
      /** @var \Magento\Quote\Model\Quote\Payment $info */
      $info = $this->getInfoInstance();

      /** @var \Magento\Sales\Model\Order $order */
      $order = $info->getOrder();

      // Prevent sending order confirmation email before payment has been done
      $order->setCanSendNewEmailFlag(false);

      $order->addStatusHistoryComment(__('The customer was redirected to Enterpay.'), \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
      $order->save();

      $status = $this->getState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
      $stateObject->setState($status->getState());
      $stateObject->setStatus($status->getStatus());
      $stateObject->setIsNotified(false);

      return $this;
    }

    /**
     * Refund a transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
      // Check amount
      if ($amount <= 0) {
        throw new LocalizedException(__('Invalid amount for refund.'));
      }

      // Check transaction ID
      if (!$payment->getTransactionId()) {
        throw new LocalizedException(__('Invalid transaction ID.'));
      }

      // Check tax rates
      // We don't have access to refunded items and arbitrary amount may be refunded
      // using "Adjustment fee" so we don't know which VAT/TAX rates to refund. Therefore
      // we need to force manual refunding of orders with more than one tax rate.
      if (count($this->_getTaxRates($payment->getOrder())) !== 1) {
        throw new LocalizedException(__('Cannot refund order with multiple tax rates. Please refund offline.'));
      }

      $body = $this->_buildRefundRequest($payment, $amount);
      if ($this->_postRefundRequest($body)) {
        return $this;
      }

      throw new LocalizedException(__('Error refunding invoice. Please try again or refund offline.'));
    }

    /**
     * Build refund request
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return array
     */
    protected function _buildRefundRequest(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
      $data = [
        'merchant' => $this->_getMerchantId(),
        'merchant_key_version' => intval($this->_getMerchantSecretVersion()),
        'identifier_merchant' => $payment->getOrder()->getIncrementId(),
        'refund' => [
          'vat_bases_to_refund' => [],
          'invoicing_date' => date('Y-m-d'),
        ]
      ];

      $taxRates = $this->_getTaxRates($payment->getOrder());
      $data['refund']['vat_bases_to_refund'][] = [
        'vat_base' => reset($taxRates),
        'currency' => $payment->getOrder()->getOrderCurrencyCode(),
        'refunded_amount' => intval(floatval($amount) * 100),
      ];

      $data['hmac'] = $this->_calcInvoiceApiHmac($data);

      return $data;
    }

    /**
     * Post refund request
     *
     * @param array $data
     * @return boolean
     */
    protected function _postRefundRequest($data)
    {
      $client = $this->_httpClientFactory->create();
      $client->setUri($this->_apiRefundUrl());
      $client->setConfig(['maxredirects' => 5, 'timeout' => 30]);
      $client->setMethod(\Zend_Http_Client::POST);
      $client->setHeaders(\Zend_Http_Client::CONTENT_TYPE, 'application/json');
      $client->setRawData(json_encode($data), 'application/json');

      try {
        $response = $client->request();
      } catch (\Exception $e) {
        throw new LocalizedException(__('Invoice API connection error. Error: %1', [$e->getMessage()]));
      }

      $body = json_decode($response->getBody());

      if ($response->isSuccessful()) {
        return TRUE;
      } else {
        // Single error as a string
        if (isset($body->error)) {
          throw new LocalizedException(__('Invoice API refund error. Error: %1', [$body->error]));
        }
        // Multiple errors in an array
        else if (isset($body->errors)) {
          throw new LocalizedException(__('Invoice API refund error. Errors: %1', [implode(', ', $body->errors)]));
        }
        // Error not available
        else {
          throw new LocalizedException(__('Invoice API refund error. Errors: Unknown error'));
        }
      }

      return FALSE;
    }

    /**
     * Get tax rates for order
     */
    protected function _getTaxRates($order)
    {
      $items = $this->_itemArgs($order);
      $rates = [];

      foreach ($items as $item) {
        $rates[] = $item['tax_rate'];
      }

      return array_unique($rates, SORT_NUMERIC);
    }

    /**
     * Get API URL for refunds
     *
     * @return string
     */
    protected function _apiRefundUrl()
    {
      if ( ! $this->_getTestMode() ) {
        return 'https://laskuyritykselle.fi/api/merchant/invoices/refund';
      }

      return 'https://test.laskuyritykselle.fi/api/merchant/invoices/refund';
    }

    /**
     * Calculate invoice API HMAC
     *
     * @param $params array
     * @return string
     */
    protected function _calcInvoiceApiHmac($params)
    {
      $params = $this->_flattenArray($params);
      $keys = array_keys($params);
      sort($keys);

      $values = array();
      foreach ($keys as $key) {
        $value = $params[$key];
        if ($value !== null && $value !== "") {
          $encoded_value = urlencode($value);
          array_push($values, $encoded_value);
        }
      }

      return hash_hmac("sha512", implode("&", $values), $this->_getMerchantSecret());
    }

    /**
     * Flatten array
     *
     * @param $params array
     * @param $base_key string
     * @return array
     */
    protected function _flattenArray($params, $base_key = '')
    {
      $result = array();
      foreach ($params as $key => $value) {
        $new_key = $base_key . $key;
        if (is_array($value)) {
          $result = array_merge($result, $this->_flattenArray($value, $new_key));
        }
        else {
          $result[$new_key] = $value;
        }
      }

      return $result;
    }

    /**
     * Get order state by status
     *
     * @param $status string
     * @return object
     */
    public function getState($status)
    {
      $collection = $this->_orderStatusCollectionFactory->create()->joinStates();
      $status = $collection->addAttributeToFilter('main_table.status', $status)->getFirstItem();
      return $status;
    }

    /**
     * Get form data which will be sent to Enterpay when paying.
     *
     * @param Order $order
     * @return array
     */
    public function getFormData($order)
    {
      $billingAddress = $order->getBillingAddress();
      $shippingAddress = $order->getShippingAddress();

      $receiptUrl = $this->_urlBuilder->getUrl('enterpay/receipt', [
        '_secure' => $this->_request->isSecure()
      ]);

      // Add basic info
      $data = [
        'version' => '1',
        'merchant' => $this->_getMerchantId(),
        'key_version' => $this->_getMerchantSecretVersion(),
        'identifier_merchant' => $order->getIncrementId(),
        'reference' => $this->_calculateInvoiceRef($order->getIncrementId()),
        'locale' => $this->_getLocale(),
        'currency' => $order->getOrderCurrencyCode(),
        'total_price_including_tax' => intval(floatval($order->getGrandTotal()) * 100),
        'url_return' => $receiptUrl,
        'billing_address[street]' => $billingAddress->getStreetLine(1),
        'billing_address[streetSecondRow]' => $billingAddress->getStreetLine(2),
        'billing_address[postalCode]' => $billingAddress->getPostcode(),
        'billing_address[city]' => $billingAddress->getCity(),
        'billing_address[countryCode]' => $billingAddress->getCountryId(),
        'delivery_address[street]' => $shippingAddress->getStreetLine(1),
        'delivery_address[streetSecondRow]' => $shippingAddress->getStreetLine(2),
        'delivery_address[postalCode]' => $shippingAddress->getPostcode(),
        'delivery_address[city]' => $shippingAddress->getCity(),
        'delivery_address[countryCode]' => $shippingAddress->getCountryId(),
      ];

      // Add cart items
      foreach ($this->_itemArgs($order) as $index => $values) {
        foreach ($values as $key => $value) {
          $dataKey = "cart_items[{$index}][{$key}]";
          $data[$dataKey] = $value;
        }
      }

      // Calculate hmac
      $data['hmac'] = $this->_calculateHmac($data);

      return $data;
    }

    /**
     * Calculate HMAC
     *
     * @return string
     */
    private function _calculateHmac($params)
    {
      // Sort the pairs by key name
      ksort($params);

      foreach ($params as $key => $value) {
        // Remove keys with empty or missing value
        if ($value === null || $value === '') {
          unset($params[$key]);
        } else {
          // URL-encode each key and value
          // Convert each key-value pair into the string (key=value)
          $params[$key] = urlencode($key) . '=' . urlencode($value);
        }
      }

      // Concatenate strings with &
      $str = implode('&', $params);

      // Run HMAC and return
      return strtoupper(hash_hmac('sha512', $str, $this->_getMerchantSecret()));
    }

    /**
     * Calculate invoice reference
     *
     * @return string
     */
    private function _calculateInvoiceRef($orderId)
    {
      $weights = [7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7, 3, 1, 7];
      $base = trim(strval($this->_getInvoiceRefPrefix()) . strval($orderId));
      $baseArr = str_split( $base );
      $reversedBase = array_reverse( $baseArr );

      $sum = 0;
      for ( $i = 0; $i < count($reversedBase); $i++ ) {
        $coefficient = array_shift($weights);
        $sum += $reversedBase[$i] * $coefficient;
      }

      $checksum = ( $sum % 10 == 0 ) ? 0 : ( 10 - $sum % 10 );

      return $base . $checksum;
    }

    /**
     * Get invoice reference prefix
     *
     * @return string
     */
    private function _getInvoiceRefPrefix()
    {
      return $this->getConfigData('invoice_ref_prefix');
    }

    /**
     * Get merchant ID
     *
     * @return string
     */
    private function _getMerchantId()
    {
      return $this->getConfigData('merchant_id');
    }

    /**
     * Get merchant secret
     *
     * @return string
     */
    private function _getMerchantSecret()
    {
      return $this->getConfigData('merchant_secret');
    }

    /**
     * Get merchant secret version
     *
     * @return string
     */
    private function _getMerchantSecretVersion()
    {
      return $this->getConfigData('merchant_secret_version');
    }

    /**
     * Get test mode
     *
     * @return boolean
     */
    private function _getTestMode()
    {
      return (bool) $this->getConfigData('test');
    }

    /**
     * Get locale
     */
    private function _getLocale() {
      return $this->_scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Enterpay payment URL
     *
     * @return string
     */
    public function getPaymentUrl()
    {
      if ( ! $this->_getTestMode() ) {
        return 'https://laskuyritykselle.fi/api/payment/start';
      }

      return 'https://test.laskuyritykselle.fi/api/payment/start';
    }

    /**
     * Get instructions
     *
     * @return string
     */
    public function getInstructions()
    {
      return trim($this->getConfigData('instructions'));
    }

    /**
     * Get payment page redirect URL
     */
    public function getPaymentRedirectUrl()
    {
      return 'enterpay/redirect';
    }

    /**
     * Create an array of order items (products, shipping, discounts) to be sent
     * to Enterpay when paying
     *
     * @param Order $order
     * @return array
     */
    private function _itemArgs($order)
    {
      $items = array();

      # Add line items
      foreach ($order->getAllVisibleItems() as $key => $item) {
        $items[] = array(
          'identifier' => $item->getSku(),
          'name' => $item->getName(),
          'quantity' => floatval($item->getQtyOrdered()),
          'unit_price_including_tax' => intval(floatval($item->getPriceInclTax()) * 100),
          'tax_rate' => round(floatval($item->getTaxPercent() / 100), 2),
        );
      }

      # Add shipping
      if ( ! $order->getIsVirtual()) {
        $shippingExclTax = $order->getShippingAmount();
        $shippingInclTax = $order->getShippingInclTax();
        $shippingTaxPct = 0;
        if ($shippingExclTax > 0) {
          $shippingTaxPct = ($shippingInclTax - $shippingExclTax) / $shippingExclTax;
        }

        $items[] = array(
          'identifier' => 'SHIP',
          'name' => $order->getShippingDescription(),
          'quantity' => 1,
          'unit_price_including_tax' => intval(floatval($order->getShippingInclTax()) * 100),
          'tax_rate' => round($shippingTaxPct, 2),
        );
      }

      # Add discount
      if (abs($order->getDiscountAmount()) > 0) {
        $discountData = $this->_getDiscountData($order);
        $discountInclTax = $discountData->getDiscountInclTax();
        $discountExclTax = $discountData->getDiscountExclTax();
        $discountTaxPct = 0;
        if ($discountExclTax > 0) {
          $discountTaxPct = ($discountInclTax - $discountExclTax) / $discountExclTax;
        }

        $items[] = array(
          'identifier' => 'DIS',
          'name' => $order->getDiscountDescription(),
          'quantity' => 1,
          'unit_price_including_tax' => intval(floatval($discountData->getDiscountInclTax()) * -100),
          'tax_rate' => round($discountTaxPct, 2),
        );
      }

      return $items;
    }

    /**
     * Check that payment is pending or successful and that HMAC matches
     *
     * @return boolean
     */
    public function verifyPayment($version, $status, $pendingReasons, $identifierValueBuy, $identifierMerchant, $keyVersion, $hmac)
    {
      // Check status
      if ($status != 'successful' && $status != 'pending') {
        return FALSE;
      }

      // Check hmac
      $calculatedHmac = $this->_calculateHmac([
        'version' => $version,
        'status' => $status,
        'pending_reasons' => $pendingReasons,
        'identifier_valuebuy' => $identifierValueBuy,
        'identifier_merchant' => $identifierMerchant,
        'key_version' => $keyVersion
      ]);

      return strtoupper($calculatedHmac) === strtoupper($hmac);
    }

    /**
     * Add transaction
     * @param \Magento\Sales\Model\Order $order
     * @param array $details
     * @return Transaction
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addPaymentTransaction(\Magento\Sales\Model\Order $order, array $details = [])
    {
      /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
      $transaction = null;

      $transaction = $order->getPayment()->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
      $transaction->isFailsafe(true)->close(false);
      $transaction->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, $details);
      $transaction->save();

      return $transaction;
    }


    /**
     * Gets the total discount for order
     */
    private function _getDiscountData(\Magento\Sales\Model\Order $order)
    {
      $discountIncl = 0;
      $discountExcl = 0;

      // Get product discount amounts
      foreach ($order->getAllVisibleItems() as $item) {
        if (!$this->_taxHelper->priceIncludesTax()) {
          $discountExcl += $item->getDiscountAmount();
          $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
        } else {
          $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
          $discountIncl += $item->getDiscountAmount();
        }
      }

      // Get shipping tax rate
      if ((float) $order->getShippingInclTax() && (float) $order->getShippingAmount()) {
        $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
      } else {
        $shippingTaxRate = 1;
      }

      // Add / exclude shipping tax
      $shippingDiscount = (float) $order->getShippingDiscountAmount();
      if (!$this->_taxHelper->priceIncludesTax()) {
        $discountIncl += $shippingDiscount * $shippingTaxRate;
        $discountExcl += $shippingDiscount;
      } else {
        $discountIncl += $shippingDiscount;
        $discountExcl += $shippingDiscount / $shippingTaxRate;
      }

      $return = new \Magento\Framework\DataObject;
      return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }
}
