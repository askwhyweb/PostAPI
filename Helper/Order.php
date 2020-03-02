<?php

namespace OrviSoft\Cloudburst\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Paypal\Model\Info;
use Magento\Sales\Model\Service\InvoiceService;
use OrviSoft\Cloudburst\Plugin\Exception;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\ObjectManager;
use OrviSoft\Cloudburst\Helper\Data as Functions;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Directory\Model\Currency;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Shipping\Model\ShipmentNotifier;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Framework\Registry;
use OrviSoft\Cloudburst\Plugin\Functions as PluginFunctions;
use Magento\Weee\Helper\Data as Weee;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Convert\Order as ConvertOrder;

class Order extends AbstractHelper
{
    private $functions;
    private $storeManagerInterface;
    private $productRepositoryInterface;
    private $orderRepository;
    private $currencyModel;
    private $objectManager;
    private $shipmentFactory;
    private $shipmentNotifier;
    private $trackFactory;
    private $registry;
    private $weee;
    private $invoiceService;
    private $transaction;
    private $convertOrder;

    public function __construct(
        Context $context,
        Functions $functions,
        StoreManagerInterface $storeManagerInterface,
        ProductRepositoryInterface $productRepositoryInterface,
        OrderRepository $orderRepository,
        Currency $currencyModel,
        ShipmentFactory $shipmentFactory,
        ShipmentNotifier $shipmentNotifier,
        TrackFactory $trackFactory,
        Registry $registry,
        Weee $weee,
        InvoiceService $invoiceService,
        Transaction $transaction,
        ConvertOrder $convertOrder
    ) {
        parent::__construct($context);

        $this->functions                  = $functions;
        $this->storeManagerInterface      = $storeManagerInterface;
        $this->productRepositoryInterface = $productRepositoryInterface;
        $this->orderRepository            = $orderRepository;
        $this->currencyModel              = $currencyModel;
        $this->shipmentFactory            = $shipmentFactory;
        $this->shipmentNotifier           = $shipmentNotifier;
        $this->trackFactory               = $trackFactory;
        $this->registry                   = $registry;
        $this->weee                       = $weee;
        $this->invoiceService             = $invoiceService;
        $this->transaction                = $transaction;
        $this->objectManager              = ObjectManager::getInstance();
        $this->convertOrder               = $convertOrder;
    }

    public function setOrderStatus($order)
    {
        $mOrder = $this->orderRepository->get($order['id']);
        $status = isset($order['statusId']) ? $order['statusId'] : $order['status_id'];
        switch ($status) {
            case 'cancelled':
                if (!$mOrder->canCancel()) {
                    throw new \Exception('Order cannot be cancelled');
                }
                $mOrder->cancel();
                break;
            case 'complete':

                if ($mOrder->canUnhold()) {
                    $mOrder->unhold();
                }

                if ($mOrder->canInvoice()) {
                    $invoice = $this->invoiceService->prepareInvoice($mOrder);
                    $invoice->register();
                    $invoice->save();
                    $transaction = $this->transaction->addObject($invoice)
                                                     ->addObject($invoice->getOrder())
                                                     ->save();
                }

                $mOrder->setStatus($status);
                $mOrder->setState($status);

                break;
            case 'closed':
                break;
            default:
                $mOrder->setState($status)->setStatus($status);
                break;
        }
        $this->orderRepository->save($mOrder);
    }

    protected function getHash($value)
    {
        return sha1(serialize($value));
    }

    protected function getShipmentByHash($mOrder, $shipmentHash)
    {
        $mShipments = $mOrder->getShipmentsCollection();
        foreach ($mShipments as $shipment) {
            $mItems = $shipment->getItemsCollection();
            $items  = [];
            foreach ($mItems as $item) {
                $items[] = [
                    'sku' => $item->getSku(),
                    'qty' => (int) $item->getQtyShipped(),
                ];
            }
            $mShipmentHash = $this->getHash([
                                                'rows'      => $items,
                                                'shippedOn' => $shipment->getCreatedAt(),
                                            ]
            );
            if ($shipmentHash == $mShipmentHash) {
                return $shipment;
            }
        }
        return null;
    }

    public function updateShipments($order)
    {
        $mOrder = $this->orderRepository->get($order['id']);

        if ($order['status_id'] === 'complete') {
            if (!isset($order['shipments']) || count($order['shipments']) === 0) {
                $order['shipments'][] = [
                    'shippedOn' => $mOrder->getCreatedAt(),
                    'reference' => '',
                    'shippingMethod' => 'Custom',
                    'rows' => [],
                    'all_shipped' => true
                ];
            }
        }

        if (!isset($order['shipments']) || !is_array($order['shipments'])) {
            return [];
        }
        $mOrderItems = $mOrder->getAllItems();

        if (!$mOrder->canShip()) {
            return [];
        }

        $shipments = [];
        foreach ($order['shipments'] as $shipment) {
            if (isset($shipment['shippedOn'])) {
                if(strpos($shipment['shippedOn'], '/')){
                    if(strlen($shipment['shippedOn']) > 10) {
                        $d = date_create_from_format('d/m/Y H:i:s', $shipment['shippedOn']);
                    } else {
                        $d = date_create_from_format('d/m/Y', $shipment['shippedOn']);
                    }
                } else {
                    $d = date_create($shipment['shippedOn']);
                }
                $shipment['shippedOn']             = date_format($d, 'Y-m-d h:i:s');
                $shipments[$shipment['shippedOn']] = [
                    'rows'           => [],
                    'shippedOn'      => $shipment['shippedOn'],
                    'reference'      => isset($shipment['reference']) ? $shipment['reference'] : '',
                    'shippingMethod' => isset($shipment['shippingMethod']) ? $shipment['shippingMethod'] : '',
                ];
                $allShipped                        = isset($shipment['all_shipped'])
                    ? $shipment['all_shipped']
                    : false;
                foreach ($mOrderItems as $mItem) {
                    if ($allShipped) {
                        if ($mItem->getQtyToShip()) {
                            $shipments[$shipment['shippedOn']]['rows'][$mItem->getSku()] = [
                                'item' => $mItem,
                                'qty'  => $mItem->getQtyToShip(),
                            ];
                        }
                    } else {
                        foreach ($shipment['rows'] as $item) {
                            if ($item['sku'] == $mItem->getSku()) {
                                $shipments[$shipment['shippedOn']]['rows'][$mItem->getSku()] = [
                                    'item' => $mItem,
                                    'qty'  => isset($item['qty']) ? $item['qty'] : $item['quantity'],
                                ];
                            }
                        }
                    }
                }
            }
        }

        $mShipments = $mOrder->getShipmentsCollection() ?: [];
        foreach ($mShipments as $mShipment) {
            if (isset($shipments[$mShipment->getCreatedAt()])) {
                $shipmentItems = [];
                $mItems        = $mShipment->getItemsCollection();
                foreach ($mItems as $mItem) {
                    if ($mItem->getQty() != 0) {
                        $shipmentItems[$mItem->getSku()] = $mItem->getQty();
                    }
                }
                if (isset($shipments[$mShipment->getCreatedAt()])) {
                    $shipment = $shipments[$mShipment->getCreatedAt()];
                    $match    = true;
                    foreach ($shipmentItems as $sku => $qty) {
                        if (!isset($shipment['rows'][$sku])) {
                            $match = false;
                        } elseif ($shipment['rows'][$sku]['qty'] != $qty) {
                            $match = false;
                        }
                    }
                    if ($match) {
                        unset($shipments[$mShipment->getCreatedAt()]);
                    }
                }
            }
        }

        foreach ($shipments as $createdAt => $shipment) {
            $mShipment = $this->convertOrder->toShipment($mOrder);
            $mShipment->setCreatedAt($createdAt);

            foreach ($mOrder->getAllItems() AS $orderItem) {
                if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }

                $qtyShipped = $orderItem->getQtyToShip();
                $shipmentItem = $this->convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                $itemMatched = false;
                foreach ($shipment['rows'] as $row_sku => $row) {
                    if ($shipmentItem->getSku() === (string)$row_sku) {
                        $shipmentItem->setQty($row['qty']);
                        $itemMatched = true;
                        break;
                    }
                }

                if ($itemMatched !== true) {
                    continue;
                }

                $mShipment->addItem($shipmentItem);
            }

            if (isset($shipment['shippingMethod']{0}) && isset($shipment['reference']{0})) {
                $track = $this->trackFactory->create();
                $track->addData([
                                    'carrier_code' => 'custom',
                                    'title'        => $shipment['shippingMethod'],
                                    'number'       => $shipment['reference'],
                                ]
                );
                $mShipment->addTrack($track);
            }

            $mShipment->register();
            $mShipment->getOrder()->setIsInProcess(true);
            $mShipment->save();
            $mShipment->getOrder()->save();

            $this->shipmentNotifier->notify($mShipment);

            $mShipment->save();
        }
        return [];
    }

    public function sendOrder($mOrder)
    {
        if (!$this->functions->isEnabled()) {
            return null;
        }
        if ($this->registry->registry('Mothercloud_Bridge_Observer_SalesOrderSaveAfter_Disable')) {
            return null;
        }
        if (!$mOrder->getId()) {
            return null;
        }
        $currentStoreId = $this->storeManagerInterface->getStore()
                                                      ->getId();
        $orderStoreId   = $mOrder->getStoreId();
        $storeChange    = ($currentStoreId != $orderStoreId);
        if ($storeChange) {
            $this->storeManagerInterface->setCurrentStore($mOrder->getStore());
        }
        $bridge              = $this->functions->initBridge();
        $orderDTO            = $bridge->createOrder()
                                      ->setId($mOrder->getId())
                                      ->setPublicId($mOrder->getIncrementId())
                                      ->setChannelId($mOrder->getStoreId())
                                      ->setOrderStatus($mOrder->getStatus())
                                      ->setTotal((float) $mOrder->getGrandTotal())
                                      ->setDatePlaced(strtotime($mOrder->getCreatedAt()));
        $mOrderBillingData   = $mOrder->getBillingAddress()
                                      ->getData();
        $mOrderBillingStreet = preg_split('/\n/', $mOrderBillingData['street']);
        $orderDTO->addBilling()
                 ->setFirstname(isset($mOrderBillingData['firstname']{0})
                                    ? $mOrderBillingData['firstname']
                                    : $mOrder->getCustomerFirstname()
                 )
                 ->setLastname(isset($mOrderBillingData['lastname']{0})
                                   ? $mOrderBillingData['lastname']
                                   : $mOrder->getCustomerLastname()
                 )
                 ->setCompany($mOrderBillingData['company'])
                 ->setStreet($mOrderBillingStreet[0])
                 ->setSuburb(implode("\n",
                                     array_slice($mOrderBillingStreet,
                                                 1,
                                                 count($mOrderBillingStreet)
                                     )
                             )
                 )
                 ->setCity($mOrderBillingData['city'])
                 ->setCounty($mOrderBillingData['region'])
                 ->setPostcode($mOrderBillingData['postcode'])
                 ->setCountryIsoCode($mOrderBillingData['country_id'])
                 ->setTelephone($mOrderBillingData['telephone'])
                 ->setEmailAddress(isset($mOrderBillingData['email']{0})
                                       ? $mOrderBillingData['email']
                                       : $mOrder->getCustomerEmail()
                 );
        $mOrderShipping       = $mOrder->getShippingAddress();
        $mOrderShippingData   = $mOrderShipping !== false ? $mOrderShipping->getData() : $mOrderBillingData;
        $mOrderShippingStreet = preg_split('/\n/', $mOrderShippingData['street']);
        $orderDTO->addDelivery()
                 ->setFirstname($mOrderShippingData['firstname'])
                 ->setLastname($mOrderShippingData['lastname'])
                 ->setCompany($mOrderShippingData['company'])
                 ->setStreet($mOrderShippingStreet[0])
                 ->setSuburb(implode("\n",
                                     array_slice($mOrderShippingStreet,
                                                 1,
                                                 count($mOrderShippingStreet)
                                     )
                             )
                 )
                 ->setCity($mOrderShippingData['city'])
                 ->setCounty($mOrderShippingData['region'])
                 ->setPostcode($mOrderShippingData['postcode'])
                 ->setCountryIsoCode($mOrderShippingData['country_id'])
                 ->setTelephone($mOrderShippingData['telephone'])
                 ->setEmailAddress(isset($mOrderShippingData['email']{0})
                                       ? $mOrderShippingData['email']
                                       : $mOrder->getCustomerEmail()
                 );
        $mOrderCustomerFirstname = $mOrder->getCustomerFirstname();
        $mOrderCustomerLastname  = $mOrder->getCustomerLastname();
        $orderDTO->addCustomer()
                 ->setId((int) $mOrder->getCustomerId())
                 ->setFirstname(isset($mOrderCustomerFirstname{0})
                                    ? $mOrderCustomerFirstname
                                    : $mOrderBillingData['firstname']
                 )
                 ->setLastname(isset($mOrderCustomerLastname{0})
                                   ? $mOrderCustomerLastname
                                   : $mOrderBillingData['lastname']
                 )
                 ->setEmailAddress($mOrder->getCustomerEmail())
                 ->setTelephone($mOrderBillingData['telephone'])
                 ->setCompany($mOrderBillingData['company'])
                 ->setStreet($mOrderBillingStreet[0])
                 ->setSuburb(implode("\n",
                                     array_slice($mOrderBillingStreet,
                                                 1,
                                                 count($mOrderBillingStreet)
                                     )
                             )
                 )
                 ->setCity($mOrderBillingData['city'])
                 ->setCounty($mOrderBillingData['region'])
                 ->setPostcode($mOrderBillingData['postcode'])
                 ->setCountryIsoCode($mOrderBillingData['country_id']);
        $mOrderPayment     = $mOrder->getPayment();
        $mOrderPaymentData = $mOrderPayment->getData();
        $paymentDTO        = $orderDTO->addPayment()
                                      ->setMethod($mOrderPaymentData['method'])
                                      ->setBaseCurrency($mOrder->getBaseCurrencyCode())
                                      ->setCurrency($mOrder->getOrderCurrencyCode());
        switch ($mOrderPaymentData['method']) {
            case 'banktransfer':
                $paymentDTO
                    ->setMethod('banktransfer')
                    ->setAmount((float) $mOrder->getGrandTotal())
                    ->setBaseAmount((float) $mOrder->getBaseGrandTotal());
                $orderDTO
//                    ->setIsPaid(true);                              // TESTING
                    ->setIsPaid($mOrder->getBaseTotalDue() == 0); // PRODUCTTION
                break;
            case 'free':
                $paymentDTO
                    ->setMethod('free')
                    ->setAmount(0)
                    ->setBaseAmount(0);
                $orderDTO
                    ->setIsPaid(true);
                break;
            case 'paypal_direct':
            case 'paypal_express':
            case 'paypal_standard':
                $paymentDTO
                    ->setMethod('paypal');
                $paypalDTO = $paymentDTO
                    ->addPaypalDetails()
                    ->setPayerId($mOrderPaymentData['additional_information']['paypal_payer_id'])
                    ->setPayerEmailAddress($mOrderPaymentData['additional_information']['paypal_payer_email']
                    );
                if (Info::isPaymentSuccessful($mOrderPayment)) {
                    $paypalDTO
                        ->setStatus('OK')
                        ->setStatusLabel('Payment Received')
                        ->setTxId($mOrderPayment->getLastTransId() ?: null);
                    $paymentDTO
                        ->setAmount((float) $mOrderPayment->getAmountPaid())
                        ->setBaseAmount((float) $mOrderPayment->getBaseAmountPaid());
                    $orderDTO
                        ->setIsPaid(true);
                }
                break;
            case 'sagepaydirectpro_moto':
            case 'sagepaydirectpro':
            case 'sagepayserver':
            case 'sagepayserver_moto':
            case 'sagepaypaypal':
            case 'sagepayform':
                $paymentDTO
                    ->setMethod('sagepay')
                    ->setAmount((float) $mOrderPayment->getAmountPaid())
                    ->setBaseAmount((float) $mOrderPayment->getBaseAmountPaid());
                $sagepayData = $this->objectManager->get('\Ebizmarts\Sagepaysuite2\Model\Sagepaysuite_transaction')
                                                   ->getCollection()
                                                   ->addFieldToFilter('order_id', $mOrder->getId())
                                                   ->getFirstItem()
                                                   ->getData();
                if (isset($sagepayData['id']) && isset($sagepayData['vps_tx_id'])) {
                    $paymentDTO->addSagepayDetails()
                               ->setTxId($sagepayData['vps_tx_id'])
                               ->setStatus('OK')
                               ->setStatusLabel('Payment success')
                               ->setCv2Result($sagepayData['cv2result'])
                               ->setAddressResult($sagepayData['address_result'])
                               ->setPostcodeResult($sagepayData['postcode_result'])
                               ->setAvsCv2Check($sagepayData['avscv2'])
                               ->setAuthCode($sagepayData['tx_auth_no'])
                               ->setThreeDSecureStatus($sagepayData['threed_secure_status']);
                    $orderDTO
                        ->setIsPaid(true);
                } else {
                    $orderDTO
                        ->setIsPaid(
                            PluginFunctions::float_equals($mOrder->getGrandTotal(),
                                                          $mOrderPayment->getAmountPaid()
                            )
                        );
                }
                break;
            case 'kibo_gateway':
                $paymentDTO
                    ->setAmount((float) $mOrder->getGrandTotal())
                    ->setBaseAmount((float)  $mOrder->getBaseGrandTotal());
                $orderDTO
                    ->setIsPaid(true);
                break;
            default:
                $paymentDTO
                    ->setAmount((float) $mOrderPayment->getAmountPaid())
                    ->setBaseAmount((float) $mOrderPayment->getBaseAmountPaid());
                $orderDTO
                    ->setIsPaid($mOrder->getBaseTotalDue() == 0);
                break;
        }
        if ($mOrder->getExtensionAttributes()) {
            $mOrderAppliedTaxes = $mOrder->getExtensionAttributes()
                                         ->getAppliedTaxes() ?: [];
        } else {
            $mOrderAppliedTaxes = [];
        }
        $appliedTaxes = [];
        foreach ($mOrderAppliedTaxes as $mOrderAppliedTax) {
            $appliedTaxes[$mOrderAppliedTax->getId()] = $mOrderAppliedTax->getData();
        }
        unset($mOrderAppliedTaxes);
        $shippingTaxCode = 'TAX_CODE_NOT_SET';
        if ($mOrder->getExtensionAttributes()) {
            $mOrderItemAppliedTaxes = $mOrder->getExtensionAttributes()
                                             ->getItemAppliedTaxes() ?: [];
        } else {
            $mOrderItemAppliedTaxes = [];
        }
        $itemAppliedTaxes = [];
        foreach ($mOrderItemAppliedTaxes as $mOrderItemAppliedTax) {
            $mOrderItemAppliedTaxData                             = $mOrderItemAppliedTax->getData();
            $itemAppliedTaxes[$mOrderItemAppliedTax->getItemId()] = $mOrderItemAppliedTaxData;
            if ($mOrderItemAppliedTaxData['type'] == 'shipping') {
                $shippingTaxCode = $mOrderItemAppliedTaxData['applied_taxes'][0]->getTaxId();
            }
        }
        unset($mOrderItemAppliedTaxes);
        $orderDTO->addShipping()
                 ->setGross($mOrder->getShippingInclTax())
                 ->setNet($mOrder->getShippingAmount())
                 ->setTax($mOrder->getShippingTaxAmount())
                 ->setMethod($mOrder->getShippingDescription())
                 ->setMethodLabel($mOrder->getShippingDescription())
                 ->setTaxCode($shippingTaxCode);
        $orderDiscountsItems = [];
        $mOrderItems    = $mOrder->getAllItems();
        foreach ($mOrderItems as $mItem) {
            if ($mItem->hasParentItem()) {
                continue;
            }
            $mProduct    = $this->productRepositoryInterface->getById($mItem->getProductId());
            $itemTaxCode = 'TAX_CODE_NOT_SET';
            if (isset($itemAppliedTaxes[$mItem->getQuoteItemId()])) {
                $itemTaxCode = $itemAppliedTaxes[$mItem->getQuoteItemId()]['applied_taxes']{0}
                                   ->getExtensionAttributes()
                                   ->getRates()[0]
                    ->getCode();
            } else {
                foreach ($appliedTaxes as $appliedTax) {
                    if (PluginFunctions::float_equals($mItem->getTaxPercent(), $appliedTax['percent'])) {
                        $itemTaxCode = $appliedTax['extension_attributes']->getRates()[0]->getCode();
                        break;
                    }
                }
            }
            $itemProductOptions = $mItem->getProductOptions();
            switch ($mItem->getProductType()) {
                case 'configurable' :
                    $itemSku  = $itemProductOptions['simple_sku'];
                    $itemName = $itemProductOptions['simple_name'];
                    break;
                default:
                    $itemSku  = $mItem->getSku();
                    $itemName = $mItem->getName();
            }
            $itemDTO = $orderDTO->addLineItem()
                                ->setProductId($mItem->getProductId())
                                ->setName($itemName)
                                ->setSku($itemSku)
                                ->setQuantity($mItem->getQtyOrdered())
                                ->setRowNet($mItem->getRowTotal())
                                ->setRowGross($mItem->getRowTotalInclTax())
                                ->setRowTax($mItem->getRowTotalInclTax() - $mItem->getRowTotal())
                                ->setTaxCode($itemTaxCode)
                                ->settype($mProduct->getTypeId());
            if (isset($itemProductOptions['options'])) {
                foreach ($itemProductOptions['options'] as $itemProductOption) {
                    $itemDTO->addOption($itemProductOption['label'], $itemProductOption['print_value']);
                }
            }
            $itemDiscountAmount = $mItem->getDiscountAmount();
            if ($itemDiscountAmount) {
                $itemTotal              = 0
                                          + $mItem->getRowTotal()
                                          + $mItem->getTaxAmount()
                                          + $mItem->getHiddenTaxAmount()
                                          - $mItem->getDiscountAmount()
                                          + (method_exists($this->weee, 'getRowWeeeAmountAfterDiscount')
                        ? $this->weee->getRowWeeeAmountAfterDiscount($mItem)
                        : $mItem->getWeeeTaxAppliedRowAmount()
                                          );
                $itemDicountContainsTax =
                    round($mItem->getRowTotalInclTax() - $itemDiscountAmount,
                          2
                    ) === round($itemTotal, 2);
                if ($itemDicountContainsTax === true) {
                    $netDiscount = $itemDiscountAmount / (1.0 + (float) $mItem->getTaxPercent() / 100.0);
                    $taxDiscount = $itemDiscountAmount - $netDiscount;
                } else {
                    $netDiscount = $itemDiscountAmount;
                    $taxDiscount = $itemDiscountAmount * ((float) $mItem->getTaxPercent() / 100.0);
                }

                $orderDiscountsItems[$itemTaxCode][$mItem->getSku()][] = [
                    'net' => $netDiscount,
                    'tax' => $taxDiscount,
                ];
            }
            if ($mItem->getProductType() === ProductType::TYPE_BUNDLE) {
                $optionProductLinks = [];
                foreach ($mProduct->getExtensionAttributes()
                                  ->getBundleProductOptions() as $mProductOption) {
                    $mProductOptionId = $mProductOption->getOptionId();
                    foreach ($mProductOption->getProductLinks() as $mProductOptionLink) {
                        $optionProductLinks[$mProductOptionId][$mProductOptionLink->getId()] =
                            $mProductOptionLink->getData();
                    }
                }
                $bundleKeys = array_keys($mItem->getProductOptions()['info_buyRequest']['bundle_option']);
                $bundleData = [];
                foreach ($bundleKeys as $bundleOptionId) {
                    $bundleData[$bundleOptionId] = [
                        'choice'       => $mItem->getProductOptions()
                                          ['info_buyRequest']['bundle_option'][$bundleOptionId],
                        'qty'          => $mItem->getProductOptions()
                                          ['info_buyRequest']['bundle_option_qty'][$bundleOptionId],
                        'name'         => $mItem->getProductOptions()
                                          ['bundle_options'][$bundleOptionId]['value'][0]['title'],
                        'price'        => $mItem->getProductOptions()
                                          ['bundle_options'][$bundleOptionId]['value'][0]['price'],
                        'product_link' => $optionProductLinks[$bundleOptionId]
                        [$mItem->getProductOptions()['info_buyRequest']['bundle_option'][$bundleOptionId]],
                    ];
                }
                foreach ($bundleData as $bundleItem) {
                    $mBundleProduct     =
                        $this->productRepositoryInterface->getById($bundleItem['product_link']['entity_id']);
                    $bundleItemType     = $mBundleProduct->getTypeId();
                    $bundleItemTaxClass = $mBundleProduct->getTaxClassId();
                    $bundleItemPrice    = $this->currencyModel
                        ->formatTxt($bundleItem['price'] / $bundleItem['qty']);
                    $bundleItemName     = $bundleItem['name'] . ' (' . $bundleItemPrice . ')';
                    $orderDTO->addLineItem()
                             ->setProductId($bundleItem['product_link']['entity_id'])
                             ->setName($bundleItemName)
                             ->setSku($bundleItem['product_link']['sku'])
                             ->setQuantity($bundleItem['qty'])
                             ->setRowNet(0.00)
                             ->setRowGross(0.00)
                             ->setRowTax(0.00)
                             ->setType($mProduct->getTypeId() . ' > ' . $bundleItemType)
                             ->setTaxCode($bundleItemTaxClass);
                }
            }
        }

        $shippingDiscountAmount = $mOrder->getShippingDiscountAmount();
        if ($shippingDiscountAmount > 0) {
            $shippingDiscountTaxCode = isset($shippingTaxCode) ? $shippingTaxCode : 'TAX_CODE_NOT_SET';

            $orderDiscountsItems[$shippingTaxCode]['shipping_cost'][] = [
                'net' => $mOrder->getShippingDiscountAmount(),
                'tax' => ($mOrder->getShippingInclTax() - $mOrder->getShippingAmount()) - $mOrder->getShippingTaxAmount(),
            ];
        }

        $orderDiscounts = [];
        foreach ($orderDiscountsItems as $orderDiscountTaxCode => $orderDiscountSku) {
            foreach ($orderDiscountSku as $orderDiscountSkuRows) {
                $orderDiscountLabel = $mOrder->getDiscountDescription();
                if ($orderDiscountLabel === '') {
                    $orderDiscountLabel = 'Order discount';
                }

                $net = 0.0;
                $tax = 0.0;
                foreach ($orderDiscountSkuRows as $row) {
                    $net += $row['net'];
                    $tax += $row['tax'];
                }

                if (!isset($orderDiscounts[$orderDiscountTaxCode])) {
                    $orderDiscounts[$orderDiscountTaxCode] = [
                        'label' => $orderDiscountLabel,
                        'net'   => 0.00,
                        'tax'   => 0.00,
                        'gross' => 0.00,
                    ];
                }

                $orderDiscounts[$orderDiscountTaxCode]['net']   += $net;
                $orderDiscounts[$orderDiscountTaxCode]['tax']   += $tax;
                $orderDiscounts[$orderDiscountTaxCode]['gross'] += $net + $tax;
                if ($orderDiscounts[$orderDiscountTaxCode]['label'] !== $orderDiscountLabel) {
                    $orderDiscounts[$orderDiscountTaxCode]['label'] .= ', ' . $orderDiscountLabel;
                }
            }
        }

        foreach ($orderDiscounts as $orderDiscountTaxCode => $orderDiscount) {
            $discount = $orderDTO->addDiscount();
            $discount->setTaxCode($orderDiscountTaxCode);
            $discount->setLabel($orderDiscount['label']);
            $discount->setNet($orderDiscount['net'])
                     ->setTax($orderDiscount['tax'])
                     ->setGross($orderDiscount['gross']);
        }

        $orderSent = $bridge->sendOrder($orderDTO) === true;
        if ($storeChange) {
            $this->storeManagerInterface->setCurrentStore($currentStoreId);
        }
        return $orderSent;
    }
}
