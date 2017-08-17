<?php
namespace Solteq\Enterpay\Controller\Redirect;

use \Solteq\Enterpay\Model\Enterpay;
use \Magento\Framework\Exception\LocalizedException;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_jsonFactory;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_pageFactory;

    /**
     * @var \Solteq\Enterpay\Model\Enterpay
     */
    protected $_enterpay;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        Enterpay $enterpay
    )
    {
        $this->urlBuilder = $context->getUrl();
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_jsonFactory = $jsonFactory;
        $this->_pageFactory = $pageFactory;
        $this->_enterpay = $enterpay;

        parent::__construct($context);
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
      $order = NULL;

      try {
        if ($this->getRequest()->getParam('is_ajax')) {
          // Get order
          $order = $this->_orderFactory->create();
          $order = $order->loadByIncrementId($this->_checkoutSession->getLastRealOrderId());

          // Get form data
          $formData = $this->_enterpay->getFormData($order);
          $formUrl = $this->_enterpay->getPaymentUrl();

          // Create block containing form data
          $block = $this->_pageFactory
            ->create()
            ->getLayout()
            ->createBlock('Solteq\Enterpay\Block\Redirect\Enterpay')
            ->setUrl($formUrl)
            ->setParams($formData);

          $resultJson = $this->_jsonFactory->create();

          return $resultJson->setData([
            'success' => true,
            'data' => $block->toHtml()
          ]);
        }
      } catch (\Exception $e) {
        // Error will be handled below
      }

      // Something went wrong, cancel order
      if ($order) {
        $order->cancel();
        $order->addStatusHistoryComment(__('Order canceled. Failed to redirect to Enterpay.'));
        $order->save();
      }

      // Restore the quote
      $this->_checkoutSession->restoreQuote();

      return $resultJson->setData([
        'success' => false
      ]);
    }
}
