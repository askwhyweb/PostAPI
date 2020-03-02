<?php

namespace OrviSoft\Cloudburst\Controller\Index;

use \Magento\Framework\App\Action\Context;
use \Magento\Backend\Model\UrlInterface;
use \Magento\Sales\Model\Order\Status;
use \Magento\Tax\Model\Calculation\Rate;
use \OrviSoft\Cloudburst\Helper\Action as DataHelper;
use \OrviSoft\Cloudburst\Helper\Product as productHelper;
use \OrviSoft\Cloudburst\Helper\Order as OrderHelper;
use \Magento\Framework\Registry;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;


class Api extends \Magento\Framework\App\Action\Action
{
    protected $dataHelper;
    protected $productHelper;
    protected $orderHelper;
    protected $backendUrl;
    protected $salesOrderStatus;
    protected $taxCalculationRate;
    protected $registry;

    public function __construct(
        Context $context,
        UrlInterface $backendUrl,
        Status $salesOrderStatus,
        Rate $taxCalculationRate,
        DataHelper $dataHelper,
        ProductHelper $productHelper,
        OrderHelper $orderHelper,
        Registry $registry
    ) {
        $this->backendUrl         = $backendUrl;
        $this->salesOrderStatus   = $salesOrderStatus;
        $this->taxCalculationRate = $taxCalculationRate;
        $this->dataHelper         = $dataHelper;
        $this->productHelper      = $productHelper;
        $this->orderHelper        = $orderHelper;
        $this->registry           = $registry;
        parent::__construct($context);
    }

    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        if (!$this->dataHelper->isEnabled()) {
            $error = json_encode(['errors' => 'Plugin not enabled']);
            echo ($error);
            return;
        }
        return parent::dispatch($request);
    }

    public function execute()
    {
        if (!$this->dataHelper->isEnabled()) {
            $error = json_encode(['errors' => 'Plugin not enabled']);
            print_r($error);
            return null;
        }
        $post = $this->getRequest()->getPostValue();
        if(count($post) > 0){
            return $this->dataHelper->process($post);
        }
        echo json_encode(["error" => "unknown request."]);
    }

}