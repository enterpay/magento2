<?php

namespace Solteq\Enterpay\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Solteq\Enterpay\Helper\FeeHelper;
use Solteq\Enterpay\Model\ConfigProvider;
use Solteq\Enterpay\Model\Enterpay;
use Magento\Sales\Model\Order\Payment\Transaction;

class InvoicePay implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Enterpay
     */
    protected $enterpay;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var FeeHelper
     */
    protected $feeHelper;

    /**
     * InvoicePay constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param Enterpay $enterpay
     * @param TransactionRepositoryInterface $transactionRepository
     * @param FeeHelper $feeHelper
     */
    public function __construct(
      ScopeConfigInterface $scopeConfig,
      Enterpay $enterpay,
      TransactionRepositoryInterface $transactionRepository,
      FeeHelper $feeHelper
    ) {
      $this->scopeConfig = $scopeConfig;
      $this->enterpay = $enterpay;
      $this->transactionRepository = $transactionRepository;
      $this->feeHelper = $feeHelper;
    }

    /**
     * Activate invoice on 'sales_order_invoice_pay' event if payment method is Enterpay and the setting 'Activate
     * invoice automatically' is enabled
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws \Zend_Http_Client_Exception
     */
    public function execute(Observer $observer)
    {
      $invoice = $observer->getInvoice();
      $order = $invoice->getOrder();

      if ($order->getPayment()->getMethod() == Enterpay::PAYMENT_METHOD_CODE) {

        // Add transaction ID for invoice so we can make online refunds
        $this->addTransactionIdToInvoice($invoice, $order);

        // set fee amount captured
        $this->updateFeeModel($order);

        // Activate invoice
        $activateInvoice = $this->getActivateInvoice($order);
        if ($activateInvoice) {
          $this->enterpay->activate($invoice);
        }
      }
    }

    /**
     * Add transaction ID for invoice so we can make online refunds
     *
     * @param $invoice
     * @param $order
     */
    protected function addTransactionIdToInvoice($invoice, $order)
    {
      if (!$invoice->getTransactionId()) {
        $captureTransaction = $this->getCaptureTransaction($order);
        $transactionId = $captureTransaction->getTxnId();
        $invoice->setTransactionId($transactionId);
        $invoice->save();
      }
    }

    /**
     * Set the amount captured of fee
     *
     * @param $order
     * @throws LocalizedException
     */
    protected function updateFeeModel($order)
    {
      $fee = $this->feeHelper->getPaymentFee($order->getPayment());

      if($fee){
        $amountToCapture = $fee->getBaseAmountAuthorized() - $fee->getBaseAmountCanceled() -
          $fee->getBaseAmountCaptured();

        if($amountToCapture >= ConfigProvider::PRICE_TOLERANCE_LEVEL){
          $fee->setBaseAmountCaptured(round($fee->getBaseAmountCaptured() + $amountToCapture, 2));
          $fee->save();
        }
      }
    }

    /**
     * Get activate invoice
     *
     * @param $order
     * @return mixed
     */
    protected function getActivateInvoice($order)
    {
      $storeId = $order->getStore()->getStoreId();
      $activateInvoice = $this->scopeConfig->getValue(
        'payment/enterpay/activate_invoice',
        ScopeInterface::SCOPE_STORE,
        $storeId
      );

      return $activateInvoice;
    }

    /**
     * Get the capture transaction
     *
     * @param OrderInterface $order
     * @return mixed
     */
    protected function getCaptureTransaction(OrderInterface $order)
    {
      $payment = $order->getPayment();
      $captureTransaction = $this->transactionRepository->getByTransactionType(
        Transaction::TYPE_CAPTURE,
        $payment->getId(),
        $payment->getOrder()->getId()
      );
      return $captureTransaction;
    }
}
