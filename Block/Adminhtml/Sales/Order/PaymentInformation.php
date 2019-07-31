<?php

namespace Solteq\Enterpay\Block\Adminhtml\Sales\Order;

use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Solteq\Enterpay\Helper\FeeHelper;

class PaymentInformation extends Template
{
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var OrderInterface|null
     */
    protected $order;

    /**
     * @var FeeHelper
     */
    protected $feeHelper;

    /**
     * @var CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * PaymentInformation constructor.
     *
     * @param Template\Context $context
     * @param OrderRepositoryInterface $orderRepository
     * @param FeeHelper $feeHelper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        OrderRepositoryInterface $orderRepository,
        FeeHelper $feeHelper,
        CurrencyFactory $currencyFactory,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->feeHelper = $feeHelper;
        $this->currencyFactory = $currencyFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get is invoice fee order. Returns true if order has invoice fee
     *
     * @return bool
     * @throws LocalizedException
     */
    public function isInvoiceFeeOrder()
    {
      if ($this->getRequest()->getParam('order_id')) {
        $order = $this->getOrder($this->getRequest()->getParam('order_id'));
        if ($this->getFeeValue($order) > 0) {
          return true;
        }
      }
      return false;
    }

    /**
     * Get Order
     *
     * @param int $orderId
     * @return OrderInterface
     */
    public function getOrder(int $orderId)
    {
      if (empty($this->order)) {
        $this->order = $this->orderRepository->get($orderId);
      }
      return $this->order;
    }

    /**
     * Get Fee value
     *
     * @param OrderInterface $order
     * @return int|mixed
     */
    protected function getFeeValue(OrderInterface $order)
    {
      $value = 0;
      try {
        $payment = $order->getPayment();
        if ($fee = $this->feeHelper->getPaymentFee($payment)) {
          $value = $fee->getData('base_amount_authorized');
        }
      } catch (LocalizedException $e) {
        return 0;
      }
      return $value;
    }

    /**
     * Get the fee information string with fee value.
     *
     * @return string
     */
    public function getFeeInformation()
    {
      if ($this->getRequest()->getParam('order_id')) {
        $order = $this->getOrder($this->getRequest()->getParam('order_id'));
        $currency = $this->currencyFactory->create()->load($order->getOrderCurrencyCode());
        $currencySymbol = $currency->getCurrencySymbol();
        $feeValue = $this->getFeeValue($order);
        return sprintf(__('Invoice fee of %.2f%s was added to order'), $feeValue, $currencySymbol);
      }
    }
}
