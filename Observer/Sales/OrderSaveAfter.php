<?php


namespace OrviSoft\Cloudburst\Observer\Sales;

/**
 * Class OrderSaveAfter
 *
 * @package OrviSoft\Cloudburst\Observer\Sales
 */
class OrderSaveAfter implements \Magento\Framework\Event\ObserverInterface
{

    protected $postHelper;

    public function __construct(\OrviSoft\Cloudburst\Helper\Post $postHelper)
    {
        $this->postHelper = $postHelper;
    }
    
    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $order = $observer->getEvent()->getOrder();
        if ($order->dataHasChangedFor('status') !== true) { // validate order if status is not changed than do nothing and ignore the observer.
            return $this;
        }
        $items = [];
        foreach($order->getAllVisibleItems() as $_item){
            $items[] = $_item->getData();
        }
        $order = ['order' => $order->getData(), 'billing_address' => $order->getBillingAddress()->getData(), 'shipping_address' => $order->getShippingAddress()->getData(), 'order_items' => $items];
        $url = 'https://fivetech.web-api.retaildirectgroup.com/api/order_magento.html';
        return $this->postHelper->pushData($url, ['increment_id' => $order['order']['increment_id'], 'order_data' => json_encode($order, true)]);
    }
}

