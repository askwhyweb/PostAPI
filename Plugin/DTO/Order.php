<?php

namespace OrviSoft\Cloudburst\Plugin\DTO;

class Order extends AbstractDTO
{
    private $id;
    private $publicId;
    private $comment;
    private $channelId;
    private $lineItems = [];

    /* @var $customer \OrviSoft\Cloudburst\Plugin\DTO\Customer */
    private $customer;

    /* @var $delivery \OrviSoft\Cloudburst\Plugin\DTO\Address */
    private $delivery;

    /* @var $billing \OrviSoft\Cloudburst\Plugin\DTO\Address */
    private $billing;

    /* @var $shipping \OrviSoft\Cloudburst\Plugin\DTO\Address */
    private $shipping;

    /* @var $payment \OrviSoft\Cloudburst\Plugin\DTO\Payment */
    private $payment;


    private $discounts = [];
    private $leadSource;
    private $datePlaced;
    private $orderStatus;
    private $total;
    private $isPaid;

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setPublicId($publicId)
    {
        $this->publicId = $publicId;
        return $this;
    }

    public function getPublicId()
    {
        return $this->publicId;
    }

    function addLineItem()
    {
        $lineItem          = new LineItem();
        $this->lineItems[] = $lineItem;
        return $lineItem;
    }

    public function getLineItems()
    {
        return $this->lineItems;
    }

    public function addCustomer()
    {
        $this->customer = new Customer();
        return $this->customer;
    }

    public function getCustomer()
    {
        return $this->customer;
    }

    public function addDelivery()
    {
        $this->delivery = new Address();
        return $this->delivery;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function addBilling()
    {
        $this->billing = new Address();
        return $this->billing;
    }

    public function getBilling()
    {
        return $this->billing;
    }

    public function setComment($comment)
    {
        $this->comment = $this->enforceUnicode($comment);
        return $this;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setChannelId($channelId)
    {
        $this->channelId = $this->enforceUnicode($channelId);
        return $this;
    }

    public function getChannelId()
    {
        return $this->channelId;
    }

    public function setOrderStatus($orderStatus)
    {
        $this->orderStatus = $this->enforceUnicode($orderStatus);
        return $this;
    }

    public function getOrderStatus()
    {
        return $this->orderStatus;
    }

    public function setLeadSource($leadSource)
    {
        $this->leadSource = $this->enforceUnicode($leadSource);
        return $this;
    }

    public function getLeadSource()
    {
        return $this->leadSource;
    }

    public function setDatePlaced($datePlaced)
    {
        $this->datePlaced = $datePlaced;
        return $this;
    }

    public function getDatePlaced()
    {
        return $this->datePlaced;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setTotal($total)
    {
        $this->total = $total;
        return $this;
    }

    public function setIsPaid($isPaid)
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    public function getIsPaid()
    {
        return $this->isPaid;
    }

    public function addShipping()
    {
        $this->shipping = new Shipping();
        return $this->shipping;
    }

    public function getShipping()
    {
        return $this->shipping;
    }

    public function addPayment()
    {
        $this->payment = new Payment();
        return $this->payment;
    }

    public function addDiscount()
    {
        $discount          = new Discount();
        $this->discounts[] = $discount;
        return $discount;
    }

    public function getDiscounts()
    {
        return $this->discounts;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function toArray()
    {
        $data = \get_object_vars($this);
        if ($this->customer !== null) {
            $data['customer'] = $this->customer->toArray();
        }
        if ($this->delivery !== null) {
            $data['delivery'] = $this->delivery->toArray();
        }
        if ($this->billing !== null) {
            $data['billing'] = $this->billing->toArray();
        }
        if ($this->shipping !== null) {
            $data['shipping'] = $this->shipping->toArray();
        }
        if ($this->payment !== null) {
            $data['payment'] = $this->payment->toArray();
        }
        $data['discounts'] = [];
        foreach ($this->discounts as $key => $discount) {
            $data['discounts'][$key] = $discount->toArray();
        }
        $data['lineItems'] = [];
        foreach ($this->lineItems as $key => $lineItem) {
            $data['lineItems'][$key] = $lineItem->toArray();
        }
        return $data;
    }
}
