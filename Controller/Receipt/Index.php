<?php
namespace Solteq\Enterpay\Controller\Receipt;

use Magento\Sales\Model\Order\Payment\Transaction;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
      // Check order number
      $orderNo = $this->getRequest()->getParam('identifier_merchant');
      if (empty($orderNo)) {
        $this->session->restoreQuote();
        $this->messageManager->addError(__('Order number is empty'));
        $this->_redirect('checkout/cart');
        return;
      }

      // Get order
      // Instead of loading order by using session->getLastRealOrderId(), we load by
      // Enterpay supplied order ID. This is because we do not have cookies
      // available if Enterpay server requests this page instead of the customer.
      // This will also mitigate order ID tampering attempts.
      $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
      $order = $orderFactory->create()->loadByIncrementId($orderNo);
      if (!$order->getId()) {
        $this->session->restoreQuote();
        $this->messageManager->addError(__('No order for processing found'));
        $this->_redirect('checkout/cart');
        return;
      }

      /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
      $method = $order->getPayment()->getMethodInstance();

      $verifiedPayment = $method->verifyPayment(
        $this->getRequest()->getParam('version'),
        $this->getRequest()->getParam('status'),
        $this->getRequest()->getParam('pending_reasons'),
        $this->getRequest()->getParam('identifier_valuebuy'),
        $this->getRequest()->getParam('identifier_merchant'),
        $this->getRequest()->getParam('key_version'),
        $this->getRequest()->getParam('hmac')
      );

      if (!$verifiedPayment) {
        // Cancel order
        $order->cancel();
        $order->addStatusHistoryComment(__('Order canceled. Failed to complete the payment.'));
        $order->save();

        // Restore the quote
        $this->session->restoreQuote();

        $this->messageManager->addError(__('Failed to complete the payment. Please try again or contact the customer service.'));

        $this->_redirect('checkout/cart');
        return;
      }

      // Transaction ID is suffixed with status. If the payment is first marked as pending
      // and then Enterpay marks it successful and calls the URL, a new transaction
      // will be created for successful payment.
      $transactionId = $this->getRequest()->getParam('identifier_valuebuy') . '-' . $this->getRequest()->getParam('status');

      // Check if transaction is already registered
      $transaction = $this->transactionRepository->getByTransactionId(
        $transactionId,
        $order->getPayment()->getId(),
        $order->getId()
      );

      if ($transaction) {
        $details = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($details)) {
          // Redirect to Success Page
          $this->session->getQuote()->setIsActive(false)->save();
          $this->_redirect('checkout/onepage/success');
          return;
        }

        // Restore the quote
        $this->session->restoreQuote();

        $this->messageManager->addError(__('Payment failed'));
        $this->_redirect('checkout/cart');
      }

      // Register transaction
      $order->getPayment()->setTransactionId($transactionId);
      $details = [
        'version' => $this->getRequest()->getParam('version'),
        'status' => $this->getRequest()->getParam('status'),
        'pending_reasons' => $this->getRequest()->getParam('pending_reasons'),
        'identifier_valuebuy' => $this->getRequest()->getParam('identifier_valuebuy'),
        'identifier_merchant' => $this->getRequest()->getParam('identifier_merchant'),
        'key_version' => $this->getRequest()->getParam('key_version'),
        'hmac' => $this->getRequest()->getParam('hmac'),
      ];
      $transaction = $method->addPaymentTransaction($order, $details);

      // Set last transaction ID
      $order->getPayment()->setLastTransId($transactionId)->save();

      // Create invoice
      if ($order->canInvoice()) {
        $method->getInfoInstance()->capture();

        // Add transaction ID for invoice so we can make online refunds
        $invoice = $method->getInfoInstance()->getCreatedInvoice();
        if ($invoice) {
          $invoice->setTransactionId($order->getPayment()->getLastTransId());
          $invoice->save();
        }
      }

      // Change order status
      $paymentStatus = $this->getRequest()->getParam('status');
      if ($paymentStatus == 'successful') {
        $statusComment = __('Payment has been completed.');
        $newStatus = $method->getConfigData('approved_order_status');
      } else {
        $statusComment = __('Payment is pending. Please confirm the payment before delivery.');
        $newStatus = $method->getConfigData('order_status');
      }
      $status = $method->getState($newStatus);
      $order->setData('state', $status->getState());
      $order->setStatus($status->getStatus());
      $order->addStatusHistoryComment($statusComment);
      $order->save();

      // Send order notification
      try {
        $this->orderSender->send($order);
      } catch (\Exception $e) {
        $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
      }

      // Redirect to success page
      $this->session->getQuote()->setIsActive(false)->save();
      $this->_redirect('checkout/onepage/success');
    }
}
