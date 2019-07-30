<?php

namespace Solteq\Enterpay\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
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

    protected $transactionRepository;

    /**
     * InvoicePay constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param Enterpay $enterpay
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
      ScopeConfigInterface $scopeConfig,
      Enterpay $enterpay,
      TransactionRepositoryInterface $transactionRepository
    ) {
      $this->scopeConfig = $scopeConfig;
      $this->enterpay = $enterpay;
      $this->transactionRepository = $transactionRepository;
    }

    /**
     * Activate invoice on 'sales_order_invoice_pay' event if payment method is Enterpay and the setting 'Activate invoice
     * automatically' is enabled
     *
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
      $invoice = $observer->getInvoice();
      $order = $invoice->getOrder();

      if ($order->getPayment()->getMethod() == Enterpay::PAYMENT_METHOD_CODE) {

        // Add transaction ID for invoice so we can make online refunds
        if (!$invoice->getTransactionId()) {
          $captureTransaction = $this->getCaptureTransaction($order);
          $transactionId = $captureTransaction->getTxnId();
          $invoice->setTransactionId($transactionId);
          $invoice->save();
        }

        // Activate invoice
        $activateInvoice = $this->getActivateInvoice($order);
        if ($activateInvoice) {
          $this->enterpay->activate($invoice);
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

    protected function getCaptureTransaction($order)
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
