<?php

namespace OrviSoft\Cloudburst\Helper;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Api\Data\OptionInterface;
use Magento\ConfigurableProduct\Api\OptionRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\Store\Model\ScopeInterface;
use Mothercloud\Bridge\Plugin\Exception;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\CategoryManagement;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\Product\Action;
use Magento\Catalog\Model\Product\AttributeSet\Options;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Model\Currency;
use Magento\Eav\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use OrviSoft\Cloudburst\Plugin\Functions;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadata;
use Magento\Tax\Model\TaxClass\Source\Product as TaxClassSourceProduct;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Indexer\Product\Flat\Processor as ProductFlatProcessor;
use Magento\Eav\Model\AttributeFactory;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Catalog\Model\Product\Gallery\EntryFactory;
use Magento\Catalog\Model\Product\Gallery\GalleryManagement;
use Magento\Framework\Api\ImageContentFactory;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

class Product extends Data
{
    protected $productFactory;
    protected $productAction;
    protected $stockRegistryInterface;
    protected $searchCriteriaBuilder;
    protected $productFlatIndexerProcessor;
    protected $mediaGalleryEntryFactory;
    protected $mediaGalleryManagement;
    protected $imageContentFactory;
    protected $productUrlRewriteGenerator;
    protected $urlPersist;

    public function __construct(
        Context $context,
        ResourceConnection $resourceConnection,
        ProductRepositoryInterface $catalogProductRepositoryInterface,
        StoreManagerInterface $storeManagerInterface,
        Options $catalogProductAttributeSetOptions,
        ProductModel $catalogProduct,
        CategoryLinkManagementInterface $catalogCategoryLinkManagementInterface,
        CategoryFactory $catalogCategoryFactory,
        CategoryManagement $catalogCategoryManagementInterface,
        CategoryRepositoryInterface $categoryRepositoryInterface,
        Currency $currencyModel,
        Config $eavConfig,
        ProductLinkInterfaceFactory $productLinkFactory,
        DirectoryList $directoryList,
        ProductMetadata $metadata,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductFactory $productFactory,
        Action $productAction,
        StockRegistryInterface $stockRegistryInterface,
        TaxClassSourceProduct $taxClassSourceProduct,
        ProductFlatProcessor $productFlatIndexerProcessor,
        AttributeManagementInterface $attributeManagement,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeFactory $attributeFactory,
        AttributeRepository $attributeRepository,
        AttributeOptionInterfaceFactory $attributeOptionFactory,
        AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory,
        EntryFactory $mediaGalleryEntryFactory,
        GalleryManagement $mediaGalleryManagement,
        ImageContentFactory $imageContentFactory,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        UrlPersistInterface $urlPersist
    ) {
        parent::__construct(
            $context,
            $resourceConnection,
            $catalogProductRepositoryInterface,
            $storeManagerInterface,
            $catalogProductAttributeSetOptions,
            $catalogProduct,
            $catalogCategoryLinkManagementInterface,
            $catalogCategoryFactory,
            $catalogCategoryManagementInterface,
            $categoryRepositoryInterface,
            $currencyModel,
            $eavConfig,
            $productLinkFactory,
            $directoryList,
            $metadata,
            $taxClassSourceProduct,
            $attributeManagement,
            $attributeOptionManagement,
            $attributeFactory,
            $attributeRepository,
            $attributeOptionFactory,
            $attributeOptionLabelFactory
        );
        $this->productFactory         = $productFactory;
        $this->productAction          = $productAction;
        $this->stockRegistryInterface = $stockRegistryInterface;
        $this->searchCriteriaBuilder  = $searchCriteriaBuilder;
        $this->productFlatIndexerProcessor = $productFlatIndexerProcessor;
        $this->mediaGalleryEntryFactory = $this->getObjectManager()->create(EntryFactory::class);
        $this->mediaGalleryManagement = $this->getObjectManager()->create(GalleryManagement::class);
        $this->imageContentFactory = $this->getObjectManager()->create(ImageContentFactory::class);
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
    }

    public function createProduct(array $product)
    {
        $productGroup = isset($product['productGroup']) ? $product['productGroup'] : null;
        $urlKey = strtolower(preg_replace('#[^0-9a-zA-Z]+#i', '-', $product['name']));
        $urlKeySearchCriteria = $this->searchCriteriaBuilder->addFilter('url_key', $urlKey)
                                                            ->create();
        if ($keys = $this->getProductRepository()
                         ->getList($urlKeySearchCriteria)
                         ->getItems()
        ) {
            $so = (new SortOrder())
                ->setField('entity_id')
                ->setDirection('desc');

            $maxIdSearchCriteria = $this->searchCriteriaBuilder->addSortOrder($so)
                                                               ->setPageSize(1)
                                                               ->create();
            $maxIdProducts = $this->getProductRepository()
                                  ->getList($maxIdSearchCriteria)
                                  ->getItems();

            if (count($maxIdProducts) != 1) {
                throw new \Exception('Last Product ID not found while looking for duplicate URL key');
            }

            $maxId  = array_keys($maxIdProducts)[0];
            $urlKey = $urlKey . '-' . ($maxId + 1);
        }

        $newProductData = [
            'sku'              => $product['sku'],
            'name'             => $product['name'],
            'type_id'          => 'simple',
            'attribute_set_id' => $this->getAttributeSetId($productGroup),
            'created_at'       => date('Y-m-d h:i:s'),
            'price'            => 0,
            'visibility'       => Visibility::VISIBILITY_BOTH,
            'url_key'          => $urlKey,
        ];

        if (!isset($newProductData['attribute_set_id']{0})) {
            throw new \Exception("Product group: '${productGroup}' does not exist");
        }

        $mProduct = $this->productFactory->create();

        foreach ($newProductData as $attribute => $value) {
            $mProduct->setData($attribute, $value);
        }

        $mProduct->setStockData(
            array(
                'is_in_stock' => 0,
                'qty' => 0
            )
        );

        $newProduct = $this->getProductRepository()
                          ->save($mProduct);
        $newProductId = $newProduct->getId();
        if (!$newProductId) {
             throw new \Exception('Product could not be created');
        }

        $this->productFlatIndexerProcessor->reindexRow($newProductId, true);

        return $newProductId;
    }

    public function getProduct($sku) {
        return $this->getProductRepository()->get($sku, true, 0);
    }

    public function updateProduct($productId, $updateData)
    {
        $updated = false;
        $websiteMappings = $this->getWebsiteStoreViewMappings();

        if (isset($updateData['0']['update']) && array_key_exists('attribute_set_id', $updateData['0']['update'])) {
            $newAttributeSetId = $updateData['0']['update']['attribute_set_id'];
            unset($updateData['0']['update']['attribute_set_id']);

            if (isset($newAttributeSetId{0})) {
                $mProduct = $this->getProductRepository()
                                 ->getById($productId, true, 0);

                $mProduct->setAttributeSetId($newAttributeSetId);

                $this->getProductRepository()->save($mProduct);
                $updated = true;
            }
        }

        foreach ($updateData as $websiteId => $updates) {
            if (isset($updates[self::FUNC_UPDATE]) && $updates[self::FUNC_UPDATE]){
                $updates[self::FUNC_SAVE][self::FUNC_UPDATE] = $updates[self::FUNC_UPDATE];
            }

            if (isset($updates[self::FUNC_SAVE]) && $updates[self::FUNC_SAVE]) {
                if (isset($updates[self::FUNC_UPDATE])) {
                    $updates[self::FUNC_SAVE]   = array_merge($updates[self::FUNC_SAVE], $updates[self::FUNC_UPDATE]);
                    $updates[self::FUNC_UPDATE] = [];
                }

                $mProduct = $this->getProductRepository()
                                 ->getById($productId, true, $websiteMappings[$websiteId]);

                $mProduct->getData();
                foreach ($updates[self::FUNC_SAVE] as $attribute => $value) {
                    $mProduct->setData($attribute, $value);
                }

                $mProduct->save();
                $updated = true;
            }

            if (isset($updates[self::FUNC_UPDATE]) && $updates[self::FUNC_UPDATE]) {
                $this->productAction->updateAttributes([$productId],
                                                       $updates[self::FUNC_UPDATE],
                                                       $websiteMappings[$websiteId]);

                $updated = true;
            }
        }

        return $updated;
    }

    public function syncUrlRewrites($product) {
        $linkedWebsites = [];
        if (isset($product['linked'])) {
            $linkedWebsites = (array)$product['linked'];
        } else {
            $linkedWebsites = $this->getProductWebsites($sku);
        }

        if (!isset($linkedWebsites['0'])) {
            $linkedWebsites['0'] = true;
        }

        foreach ($linkedWebsites as $key => $value) {
            $linkedWebsites[$key] = $value === "Yes" || $value === true;
        }

        $websiteMappings = $this->getWebsiteStoreViewMappings();

        foreach ($linkedWebsites as $key => $value) {
            if ($value !== true) {
                continue;
            }

            $mProduct = $this->getProductRepository()
                             ->getById($product['id'], true, $websiteMappings[$key], true);

            $this->urlPersist->replace(
                $this->productUrlRewriteGenerator->generate($mProduct)
            );
        }
    }

    public function setProductStock($sku, $product)
    {
        if (!isset($product['stock'])) {
            return ['stock' => 'key_not_exists'];
        }

        try {
            $stockItem = $this->stockRegistryInterface->getStockItemBySku($sku);
        }
        catch (NoSuchEntityException $e) {
            return ['numfound' => 0];
        }

        $total_qty = 0;
        if (!is_array($product['stock'])) {
            $total_qty = $product['stock'];
        } else {
            foreach ($product['stock'] as $channel_qty) {
                $total_qty += $channel_qty;
            }
        }

        $newStockData = [];
        if (!Functions::float_equals((float) $stockItem->getQty(), (float) $total_qty) || is_null($stockItem->getQty())) {
            $newStockData['qty'] = $total_qty;
        }
        if (isset($product['attributes']['ManageStock'])) {
            $manageStockMap = [
                'yes' => '1',
                'no'  => '0',
            ];
            if (in_array(strtolower($product['attributes']['ManageStock']), array_keys($manageStockMap))) {
                $newStockData['manage_stock']            =
                    $manageStockMap[strtolower($product['attributes']['ManageStock'])];
                $useConfigManageStock = '0';
            } else {
                $useConfigManageStock = '1';
            }
            if($stockItem->getUseConfigManageStock() != $useConfigManageStock){
                $newStockData['use_config_manage_stock'] = $useConfigManageStock;
            }
        }
        if (isset($product['attributes']['Backorders'])) {
            $backordersMap = [
                'no backorders'                         => '0',
                'allow qty below 0'                     => '1',
                'allow qty below 0 and notify customer' => '2',
            ];
            if (in_array(strtolower($product['attributes']['Backorders']), array_keys($backordersMap))) {
                $newStockData['backorders'] =
                    $backordersMap[strtolower($product['attributes']['Backorders'])];
                $useConfigBackorders        = '0';
            } else {
                $useConfigBackorders = '1';
            }
            if ($stockItem->getUseConfigBackorders() != $useConfigBackorders) {
                $newStockData['use_config_backorders'] = $useConfigBackorders;
            }

        }
        $configBackorders = (int) $this->scopeConfig->getValue('cataloginventory/item_options/backorders');
        $isInStock        = (
            $total_qty > 0
            ||
            (isset($newStockData['backorders']) && $newStockData['backorders'])
            ||
            ((isset($useConfigBackorders) && $useConfigBackorders) &&
             $configBackorders)) ? 1 : 0;

        //Configurable products as always in stock
        $mgProduct = $this->getProduct($product['sku']);
        if ($mgProduct->getTypeId() === 'configurable') {
            $isInStock = 1;
        }

        if ($stockItem->getIsInStock() != $isInStock) {
            $newStockData['is_in_stock'] = $isInStock;
        }
        if ($newStockData) {
            foreach ($newStockData as $label => $value) {
                $stockItem->setData($label, $value);
            }
            $this->stockRegistryInterface->updateStockItemBySku($sku, $stockItem);
        }
        return [];
    }

    public function getProductAttributeSetId($product, $mProduct) {
        if (isset($product['productGroup']{0})) {
            $newProductGroup = $this->getAttributeSetId($product['productGroup']);

            if (isset($newProductGroup{0})) {
                return $newProductGroup;
            }
        }

        return $mProduct->getAttributeSetId();
    }

    public function getProductWebsites($sku) {
        $mProduct = $this->getProductRepository()
                         ->get($sku, false, 0);

        $ret = [];
        foreach ($mProduct->getWebsiteIds() as $websiteId) {
            $ret[$websiteId] = true;
        }

        return $ret;
    }

    public function getPriceUpdate($product)
    {
        if (!isset($product['prices']) || !is_array($product['prices'])) {
            return [];
        }

        if (isset($product['type']) && $product['type'] === 'variant-master') {
            return [];
        }

        $sku               = $product['sku'];
        $prices            = $product['prices'];
        $priceLabelMapping = [
            'retail' => 'price',
            'rrp'    => 'msrp',
            'cost'   => 'cost',
            'sale'   => 'special_price',
            'start'  => 'special_from_date',
            'end'    => 'special_to_date',
            'msrp'    => 'msrp',
        ];
        $regularPrices     = [];
        $newTierPrices     = [];

        $linkedWebsites = [];
        if (isset($product['linked'])) {
            $linkedWebsites = (array)$product['linked'];
        } else {
            $linkedWebsites = $this->getProductWebsites($sku);
        }

        if (!isset($linkedWebsites['0'])) {
            $linkedWebsites['0'] = true;
        }

        foreach ($linkedWebsites as $key => $value) {
            $linkedWebsites[$key] = $value === "Yes" || $value === true;
        }

        $mProduct = $this->getProductRepository()
                         ->get($sku, true, 0);

        $productAttributeSetId = $this->getProductAttributeSetId($product, $mProduct);
        $priceAttributes = $this->getPriceAttributes($productAttributeSetId);

        foreach ($prices as $label => $value) {
            preg_match("/^(?'attribute'[^-]+)(?:-)(?'website'[0-9]+)/", $label, $matches);
            if ($matches) {
                $attribute = $matches['attribute'];
                $website = $matches['website'];
            } else {
                $attribute = $label;
                $website = '0';
            }

            if (!isset($priceLabelMapping[$attribute])) {
                continue;
            }

            if (!isset($linkedWebsites[$website]) || $linkedWebsites[$website] !== true) {
                continue;
            }

            if (!isset($priceAttributes[$priceLabelMapping[$attribute]])) {
                continue;
            }

            if ($attribute === 'sale' && isset($value['price'])) {
                $regularPrices[$website][$priceLabelMapping['sale']] = $value['price'];
                if (isset($value['start'])) {
                    $regularPrices[$website][$priceLabelMapping['start']] = $value['start'];
                }
                if (isset($value['end'])) {
                    $regularPrices[$website][$priceLabelMapping['end']] = $value['end'];
                }
            } elseif (is_array($value) && isset($value['1'])) {
                $regularPrices[$website][$priceLabelMapping[$attribute]] = $value['1'];
            } else {
                $regularPrices[$website][$priceLabelMapping[$attribute]] = $value;
            }
            if ($attribute === 'retail' && is_array($value)) {
                foreach ($value as $qty => $price) {
                    if ($qty != 1) {
                        $newTierPrices[$website][] = [
                            'website_id' => $website,
                            'all_groups' => '1',
                            'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                            'price'      => $price,
                            'price_qty'  => $qty,
                        ];
                    }
                }
            }
        }
        $regularPriceUpdate = [];
        $websiteMappings = $this->getWebsiteStoreViewMappings();
        foreach ($regularPrices as $websiteId => $attributes) {
            if (isset($attributes['special_price'])) {
                if (!isset($attributes['special_from_date'])) {
                    $attributes['special_from_date'] = '';
                }
                if (!isset($attributes['special_to_date'])) {
                    $attributes['special_to_date'] = '';
                }
                if ($attributes['special_price'] === false) {
                    $attributes['special_price'] = '';
                }
                $regularPrices[$websiteId] = $attributes;
            }
            $mProduct = $this->getProductRepository()
                             ->get($sku, false, $websiteMappings[$websiteId])
                             ->getData();
            foreach ($attributes as $attribute => $value) {
                $currentValue = isset($mProduct[$attribute]) ? $mProduct[$attribute] : "";
                if (is_numeric($currentValue) && is_numeric($value)) {
                    if (!$this->floatEquals($currentValue, $value)) {
                        $regularPriceUpdate[$websiteId][self::FUNC_UPDATE][$attribute] = $value;
                    }
                } elseif ($attribute == 'special_from_date' || $attribute == 'special_to_date') {
                    if ($attribute == 'special_from_date' && substr($currentValue, 0, 10) <= date('Y-m-d') && !$value) {
                        continue;
                    } elseif (substr($currentValue, 0, 10) != $value) {
                        $regularPriceUpdate[$websiteId][self::FUNC_UPDATE][$attribute] = $value;
                    }
                } elseif ($currentValue != $value) {
                    $regularPriceUpdate[$websiteId][self::FUNC_UPDATE][$attribute] = $value;
                }
            }
        }
        if ($this->getScopeConfig()
                 ->getValue('catalog/price/scope', ScopeInterface::SCOPE_WEBSITE)
        ) {
            $currentTierPricesByWebsite = $this->getTierPricesBySku($sku);

            if ($newTierPrices) {
                $mergedTierPrices        = [];
                $tierPriceUpdateRequired = false;

                foreach ($currentTierPricesByWebsite as $websiteId => $currentTierPrices) {
                    if (isset($newTierPrices[$websiteId]) &&
                        !$this->tierPricesMatch($currentTierPrices, $newTierPrices[$websiteId])
                    ) {
                        $tierPriceGroup          = $newTierPrices[$websiteId];
                        $tierPriceUpdateRequired = true;
                    } else {
                        $tierPriceGroup = $currentTierPrices;
                    }
                    foreach ($tierPriceGroup as $tierPrice) {
                        $mergedTierPrices[] = $tierPrice;
                    }
                }
                if (!$tierPriceUpdateRequired) {
                    $tierPriceUpdate = [];
                } else {
                    $tierPriceUpdate['0'][self::FUNC_SAVE]['tier_price'] = $mergedTierPrices;
                }
            } elseif ($currentTierPricesByWebsite) {
                $tierPriceUpdate['0'][self::FUNC_SAVE]['tier_price'] = [];
            } else {
                $tierPriceUpdate = [];
            }
        }
        $priceUpdate = [];
        if (isset($tierPriceUpdate) && $tierPriceUpdate) {
            $priceUpdate = $tierPriceUpdate;
        }
        foreach ($regularPriceUpdate as $websiteId => $data) {
            $priceUpdate[$websiteId] = $data;
        }

        return $priceUpdate;
    }

    public function getCategoryUpdate($product)
    {
        $updateData   = [];
        $categoryData = $this->getCategoryIds($product);
        $mProduct     = $this->getProductRepository()
                             ->get($product['sku'], false, 0);
        $mCategoryIds = $mProduct->getCategoryIds();

        $mCategories  = [];
        foreach ($mCategoryIds as $mCategoryId) {
            try {
                $mCategory                      = $this->getCategoryRepositoryInterface()
                                                       ->get($mCategoryId);
            }
            catch (NoSuchEntityException $exception) {
                continue;
            }
            $rootCategoryId                 = explode('/', $mCategory->getPath())[1];
            $mCategories[$rootCategoryId][] = $mCategoryId;
        }

        foreach ($categoryData as $rootCategoryId => $categoriesIds) {
            if (!isset($mCategories[$rootCategoryId])) {
                $mCategories[$rootCategoryId] = $categoriesIds;
            } else {
                asort($mCategories[$rootCategoryId]);
                asort($categoriesIds);
                if ($mCategories[$rootCategoryId] != $categoriesIds) {
                    $mCategories[$rootCategoryId] = $categoriesIds;
                }
            }
        }
        $newCategoryIds = [];
        foreach ($mCategories as $rootCategoryId => $categoryIds) {
            foreach ($categoryIds as $categoryId) {
                $newCategoryIds[] = $categoryId;
            }
        }
        asort($mCategoryIds);
        asort($newCategoryIds);
        if ($mCategoryIds != $newCategoryIds) {
            $updateData['0'][self::FUNC_SAVE]['category_ids'] = $newCategoryIds;
        }
        return $updateData;
    }

    public function getWebsitesUpdate($product)
    {
        $websitesUpdate = [];
        $mProduct       = $this->getProductRepository()
                               ->get($product['sku'], false, 0);
        $mWebsites      = $mProduct->getWebsiteIds();

        $newWebsites    = array_flip($mWebsites);

        foreach ($product['linked'] as $websiteId => $websiteLinked) {
            if ($websiteId === 0) {
                continue;
            }

            if ($websiteLinked !== true) {
                if (!isset($newWebsites[$websiteId])) {
                    continue;
                }

                unset($newWebsites[$websiteId]);
            } else {
                $newWebsites[$websiteId] = 1;
            }
        }

        $newWebsites = array_keys($newWebsites);

        if ($mWebsites != $newWebsites) {
            $websitesUpdate['0'][self::FUNC_SAVE]['website_ids'] = $newWebsites;
        }
        return $websitesUpdate;
    }

    public function createAttributeValue($attributeCode, $value) {
        $entityTypeCode = \Magento\Catalog\Model\Product::ENTITY;

        $attribute = $this->getAttributeRepository()->get($entityTypeCode, $attributeCode);

        $optionLabel = $this->getAttributeOptionLabelFactory()->create();
        $optionLabel->setStoreId(0);
        $optionLabel->setLabel($value);

        $option = $this->getAttributeOptionFactory()->create();
        $option->setLabel($optionLabel);
        $option->setStoreLabels([$optionLabel]);
        $option->setSortOrder(0);
        $option->setIsDefault(false);

        try{
            $this->getAttributeOptionManagement()->add(
                $entityTypeCode,
                $attribute->getAttributeId(),
                $option
            );
        } catch (Exception $e){
            $this->getAttributeOptionManagement()->add(
                $entityTypeCode,
                $attribute->getAttributeId(),
                $optionLabel
            );
        }
    }

    public function getAttributesUpdate($product)
    {
        $updateData = [];
        $attributes = [];
        if (isset($product['name']{0})) {
            $namePerWebsite = false;
            foreach (array_keys($product['attributes']) as $k) {
                if (preg_match('/^(name)-\d+$/', $k)) {
                    $namePerWebsite = true;
                    break;
                }
            }
            if (!$namePerWebsite) {
                $attributes['name'] = $product['name'];
            }
        }
        if (isset($product['description']['text'])) {
            $attributes['description'] = $product['description']['text'];
        }
        if (isset($product['shortDescription']['text'])) {
            $attributes['shortDescription'] = $product['shortDescription']['text'];
        }
        if (isset($product['metaDescription']{0})) {
            $metaDescriptionPerWebsite = false;
            foreach (array_keys($product['attributes']) as $k) {
                if (preg_match('/^(metaDescription)-\d+$/', $k)) {
                    $metaDescriptionPerWebsite = true;
                    break;
                }
            }
            if (!$metaDescriptionPerWebsite) {
                $attributes['metaDescription'] = $product['metaDescription'];
            }
        }
        if (isset($product['metaKeywords']{0})) {
            $metaKeywordsPerWebsite = false;
            foreach (array_keys($product['attributes']) as $k) {
                if (preg_match('/^(metaKeywords)-\d+$/', $k)) {
                    $metaKeywordsPerWebsite = true;
                    break;
                }
            }
            if (!$metaKeywordsPerWebsite) {
                $attributes['metaKeywords'] = $product['metaKeywords'];
            }
        }

        if (isset($product['condition'])) {
            $attributes['condition'] = $product['condition'];
        }
        if (isset($product['status'])) {
            if (is_array($product['status'])) {
                foreach ($product['status'] as $websiteId => $value) {
                    $attributes['status-' . $websiteId] = $value;
                }
            } else {
                $attributes['status'] = $product['status'];
            }
        }
        $attributeLocationsKey = ['identity', 'options', 'physical', 'attributes'];
        $attributeLocations = [];
        foreach ($attributeLocationsKey as $attributeLocation) {
            if (!isset($product[$attributeLocation])) {
                continue;
            }
            $attributeLocations[] = $product[$attributeLocation];
        }

        $attributeReplaceMap = [
            'length' => 'depth',
            'status' => 'enable product',
            'tax code' => 'tax class'
        ];
        $valueReplaceMap = [
            'enable product' => [
                'enabled'  => Status::STATUS_ENABLED,
                'disabled' => Status::STATUS_DISABLED,
            ],
        ];

        foreach ($attributeLocations as $location) {
            if (!is_array($location)) {
                continue;
            }

            foreach ($location as $label => $value) {
                $mappedLabel = $label;
                $mappedValue = $value;
                if (in_array($label, array_keys($attributeReplaceMap))) {
                    $mappedLabel = $attributeReplaceMap[$label];
                }
                if (in_array($label, array_keys($valueReplaceMap))) {
                    $mappedValue = $valueReplaceMap[$label][$value];
                }
                $attributes[$mappedLabel] = $mappedValue;
            }
        }

        $attributes = $this->unCamelCaseArrayKeys($attributes);
        unset($attributes['manage stock']);
        unset($attributes['backorders']);
        $mProduct = $this->getProductRepository()
                         ->get($product['sku'], false, 0);

        $productAttributeSetId = $this->getProductAttributeSetId($product, $mProduct);
        $mAttributes = $this->getAttributeSetAttributes($productAttributeSetId);

        unset($attributes['sku']);
        $attributes      = $this->splitAttributesByChannel($attributes);
        $websiteMappings = $this->getWebsiteStoreViewMappings();

        $linked_websites = $product['linked'];
        $linked_websites['0'] = true; //Add default too

        foreach ($attributes as $websiteId => $websiteAttributes) {
            if (!isset($linked_websites[$websiteId]) || $linked_websites[$websiteId] !== true) {
                continue;
            }
            $mProductData = $this->getProductRepository()
                                 ->get($product['sku'], false, $websiteMappings[$websiteId])
                                 ->getData();
            foreach ($websiteAttributes as $label => $value) {
                if (in_array($label, array_keys($attributeReplaceMap))) {
                    $label = $attributeReplaceMap[$label];
                }
                if (in_array($label, array_keys($valueReplaceMap))) {
                    if (in_array($value, array_keys($valueReplaceMap[$label]))) {
                        $value = $valueReplaceMap[$label][$value];
                    }
                }

                if ($label === 'name') {
                    $attributeCode = 'name';
                    $mValue        = isset($mProductData[$label]) ? $mProductData[$label] : null;
                    if ($value != $mValue) {
                        $updateData[$websiteId][self::FUNC_UPDATE][$label] = $value;
                    }
                }
                else if ($label === 'tax class') {
                    $updateData[$websiteId][self::FUNC_UPDATE]['tax_class_id'] = $this->getTaxClassId($value);
                }
                else if (key_exists($label, $mAttributes)) {
                    $attributeCode = $mAttributes[$label]['attribute_code'];
                    $mValue        = isset($mProductData[$attributeCode]) ? $mProductData[$attributeCode] : null;

                    if ($mAttributes[$label]['backend_type'] === 'int') {
                        if ($value !== null && $value !== '') {
                            if (isset($mAttributes[$label]['options'][$value])) {
                                 $value = $mAttributes[$label]['options'][$value];
                            } else {
                                if ($mAttributes[$label]['is_user_defined'] === '1') {
                                    $this->createAttributeValue($attributeCode, $value);
                                    $mAttributes = $this->getAttributeSetAttributes($mProduct->getAttributeSetId());
                                    $value = $mAttributes[$label]['options'][$value];
                                }
                             }
                        } else {
                            $value = null;
                        }
                    }

                    if ($value != $mValue) {
                        $updateData[$websiteId][self::FUNC_UPDATE][$attributeCode] = $value;
                    }
                }
            }
        }

        foreach ($updateData as $websiteId => $packets) {
            foreach ($packets as $packetId => $packet) {
                if (empty($packet)) {
                    unset($packets[$packetId]);
                }
            }
            if (empty($packets)) {
                unset($updateData[$websiteId]);
            }
        }

        return $updateData;
    }

    public function getGlobalAttributesUpdate($product)
    {
        $globalUpdate = [];
        $mProduct     = $this->getProductRepository()
                             ->get($product['sku'], false, 0);
        $mProductData = $mProduct->getData();
        $globalData   = [];
        if (isset($product['productGroup'])) {
            $productGroup                   = isset($product['productGroup']) ? $product['productGroup'] : null;
            $attributeSetId                 = $this->getAttributeSetId($productGroup);
            $globalData['attribute_set_id'] = $attributeSetId;
        }

        if (isset($product['taxCode'])) {
            $taxClassId                 = $this->getTaxClassId($product['taxCode']);
            $globalData['tax_class_id'] = $taxClassId;
        }
        foreach ($globalData as $attribute => $value) {
            if (isset($mProductData[$attribute]) && $mProductData[$attribute] != $value) {
                $globalUpdate['0'][self::FUNC_UPDATE][$attribute] = $value;
            }
        }
        return $globalUpdate;
    }

    public function syncProductOther($product) {
        $mgProduct = $this->getProduct($product['sku']);

        $this->setVariationUpdate($product, $mgProduct);

        $imageSaved = $this->setProductImages($product, $mgProduct);
        if ($imageSaved !== true) {
            $mgProduct = $mgProduct->save();
        }

        $this->relatedProductsUpdate($product, $mgProduct);
        $mgProduct->save();
    }

    public function syncProductImages($product) {
        $mgProduct = $this->getProduct($product['sku']);

        $imageSaved = $this->setProductImages($product, $mgProduct);
        if ($imageSaved !== true) {
            $mgProduct = $mgProduct->save();
        }

        $this->relatedProductsUpdate($product, $mgProduct);
        $mgProduct->save();
    }

    public function setVariationUpdate($product, $mgProduct) {
        $this->setVariationUpdateMaster($product, $mgProduct);
        $this->setVariationUpdateChild($product, $mgProduct);
    }

    public function setVariationUpdateMaster($product, $mgProduct) {
        if (!isset($product['type']{0}) || $product['type'] !== 'variant-master') {
            return false;
        }

        if (!isset($product['associations']['children'])) {
            return false;
        }

        if (!isset($product['options'])) {
            return false;
        }

        $childrenIds = $this->getProductIdsBySkus($product['associations']['children']);

        if (isset($product['variations'])) {
            foreach ($product['variations'] as $childSku => $childVariations) {
                if (!isset($childrenIds[$childSku])) {
                    continue;
                }

                $childProduct = [
                    'sku' => $childSku,
                    'options' => $childVariations['options'],
                    'linked' => ["0" => "Yes"]
                ];

                $childAttributeUpdate = $this->getAttributesUpdate($childProduct);
                $this->updateProduct($childrenIds[$childSku], $childAttributeUpdate);
            }
        }

        if ($mgProduct->getTypeId() !== 'configurable') {
            $mgProduct->setTypeId('configurable');
        }

        $childProducts = [];
        foreach ($childrenIds as $childSku => $childId) {
            $childProducts[$childSku] = $this->getProductRepository()->getById($childId, true, 0);
        }

        $ignoreAttributes  = [
            'Visibility',
            'ManageStock',
        ];

        if (count($product['options']) === 0) {
            $productExistingOptions = $mgProduct->getTypeInstance()->getConfigurableAttributes($mgProduct);
            foreach ($productExistingOptions as $row) {
                $product['options'][$row->getLabel()] = null;
            }
        }

        $attributes = array();
        foreach ($product['options'] as $optionKey => $optionValue) {
            if (isset($ignoreAttributes[$optionKey])) {
                continue;
            }

            $attributeCode = strtolower($this->unCamelCase($optionKey));
            $attributeCode = preg_replace('/\s+/', '_', $attributeCode);

            try {
                $attribute = $this->getAttributeRepository()->get(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
            }
            catch (NoSuchEntityException $exception) {
                continue;
            }
            $attributes[$attribute->getId()] = $attribute;
        }

        $attributeValues = [];
        foreach ($childProducts as $childSku => $childProduct) {
            $childProductAttributeValues = [];
            foreach ($attributes as $attribute) {
                $childLabel = $childProduct->getAttributeText($attribute->getAttributeCode());
                $childValueIndex = $childProduct->getData($attribute->getAttributeCode());
                if ($childLabel === null || $childValueIndex === null) {
                    break;
                }

                $childProductAttributeValues[$attribute->getId()] = [
                    'label' => $childLabel,
                    'attribute_id' => $attribute->getId(),
                    'value_index' => $childValueIndex
                ];
            }

            if (count($childProductAttributeValues) === count($attributes)) { //All attributes matched
                foreach ($childProductAttributeValues as $attributeId => $childProductAttributeValue) {
                    $attributeValues[$attributeId][] = $childProductAttributeValue;
                }
            }
            else { //Remove child
                unset($childrenIds[$childSku]);
                unset($childProducts[$childSku]);
            }
        }

        $optionsFactory = $this->getObjectManager()->create(\Magento\ConfigurableProduct\Helper\Product\Options\Factory::class);
        $configurableAttributesData = [];

        foreach ($attributes as $attribute) {
            if (!isset($attributeValues[$attribute->getId()])) {
                continue;
            }

            $configurableAttributesData[] = [
                'attribute_id' => $attribute->getId(),
                'code' => $attribute->getAttributeCode(),
                'label' => $attribute->getStoreLabel(),
                'position' => '0',
                'values' => $attributeValues[$attribute->getId()]
            ];
        }

        if (count($configurableAttributesData) === 0) {
            $childrenIds = array();
        }

        $mgProduct->setCanSaveConfigurableAttributes(true);

        $configurableOptions = $optionsFactory->create($configurableAttributesData);
        $extensionConfigurableAttributes = $mgProduct->getExtensionAttributes();
        $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
        $extensionConfigurableAttributes->setConfigurableProductLinks(array_values($childrenIds));
        $mgProduct->setExtensionAttributes($extensionConfigurableAttributes);

        return true;
    }

    public function setVariationUpdateChild($product, $mgProduct) {
        if (!isset($product['type']{0}) || $product['type'] !== 'variant') {
            return false;
        }

        if (!isset($product['associations']['parent'])) {
            return false;
        }

        //Load parent and read the children skus
        $product['associations']['parent'] = (array)$product['associations']['parent'];
        if (count($product['associations']['parent']) === 0) {
            return false;
        }
        $parentSku = $product['associations']['parent'][0];

        try {
            $mgParentProduct = $this->getProductRepository()
                                    ->get($parentSku, true, 0, true);
        }
        catch (NoSuchEntityException $exception) {
            return false;
        }

        if ($mgParentProduct->getTypeId() === 'configurable') {
            $childrenId = $mgParentProduct->getTypeInstance()->getUsedProductIds($mgParentProduct);
        }
        else {
            $childrenId = [];
        }

        if (!in_array($product['id'], $childrenId)) {
            $childrenId[] = $product['id'];
        }

        $childrenSkus = [];
        foreach ($childrenId as $childId) {
            if ($childId === $product['id']) {
                $childrenSkus[$childId] = $product['sku'];
            } else {
                $childrenSkus[$childId] = $this->getProductRepository()->getById($childId)->getSku();
            }
        }

        $parentProduct = [
            'sku' => $parentSku,
            'type' => 'variant-master',
            'associations' => [
                'children' => array_values($childrenSkus)
            ]
        ];

        if (isset($product['options'])) {
            $parentProduct['options'] = $product['options'];
        }

        $masterUpdated = $this->setVariationUpdateMaster($parentProduct, $mgParentProduct);
        if ($masterUpdated === true) {
            $mgParentProduct->save();
        }

        return $masterUpdated;
    }

    public function setProductImages($product, $mProduct)
    {
        if (!isset($product['images'])) {
            return false;
        }

        $mineTypes         = [
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/png'  => 'png',
        ];
        $existingImagesDir = $this->getDirectory('media') . '/catalog/product';
        $tempDir           = $this->getDirectory('tmp');
        $images            = $product['images'];
        $mImages           = $mProduct->getMediaGalleryEntries() ?: [];
        $hasBaseImage      = false;
        $hasSmallImage     = false;
        $hasThumbnailImage = false;
        $existingImages    = [];
        foreach ($mImages as $mImage) {
            $existingImages[\md5_file($existingImagesDir . $mImage->getFile())] = 1;
            foreach ($mImage->getTypes() as $type) {
                if (!$hasBaseImage) {
                    if ($type == 'image') {
                        $hasBaseImage = true;
                    }
                }
                if (!$hasSmallImage) {
                    if ($type == 'small_image') {
                        $hasSmallImage = true;
                    }
                }
                if (!$hasThumbnailImage) {
                    if ($type == 'thumbnail') {
                        $hasThumbnailImage = true;
                    }
                }
            }
        }

        //Generate md5 for all images
        foreach ($images as $key => $image) {
            if (isset($image['md5']{0})) {
                continue;
            }

            if (isset($image['contents_decoded']{0})) {
                $images[$key]['md5'] = md5($image['contents_decoded']);
            } elseif (isset($image['contents']{0})) {
                $images[$key]['contents_decoded'] = \base64_decode((string) $image['contents'], true);
                $images[$key]['md5'] = md5($image['contents_decoded']);
            } elseif (isset($image['url']{0})) {
                $images[$key]['contents_decoded'] = $this->getFileContents($image['url']);
                $images[$key]['md5'] = md5($image['contents_decoded']);
            }
        }

        //Do not recreate images
        $nonExistingImages = [];
        foreach ($images as $image) {
            if (isset($existingImages[$image['md5']])) {
                continue;
            }

            $nonExistingImages[] = $image;
        }

        $imagesToCreate = [];
        foreach ($nonExistingImages as $image) {
            $imageContents = null;
            if (isset($image['contents_decoded'])) {
                $imageContents = $image['contents_decoded'];
            }
            elseif (isset($image['contents'])) {
                $imageContents = \base64_decode((string)$image['contents'], true);
            } elseif (isset($image['url'])) {
                $imageContents = $this->getFileContents($image['url']);
            }

            if (!$imageContents) {
                continue;
            }

            $filename = $image['md5'];
            if (isset($image['filename'])) {
                $filename = $image['filename'];
            } elseif (isset($image['url'])) {
                $filename = \str_replace('%20', '', \basename($image['url']));
            }

            $tempFilename = \tempnam($tempDir, 'prefix');
            \file_put_contents($tempFilename, $imageContents);
            $imageMimeType = \mime_content_type($tempFilename);
            \unlink($tempFilename);

            if (!isset($mineTypes[$imageMimeType])) {
                continue;
            }

            $imageExtension = $mineTypes[$imageMimeType];
            $filename = \preg_replace('/\.[^\.]+$/', '', $filename);
            $label = $filename;

            //We need to check for duplicate filenames
            if (isset($imagesToCreate[$filename])) {
                $filename = $filename . '_' . $image['md5'];
            }

            $imagesToCreate[$filename] = [
                'filename' => $filename . '.' . $imageExtension,
                'contents' => $imageContents,
                'label'    => $label,
                'md5'      => $image['md5']
            ];
        }

        //No images to create
        if (count($imagesToCreate) == 0) {
            return false;
        }

        //We need to make sure that we only create new images, and non duplicate
        foreach ($imagesToCreate as $key => $imageToCreate) {
            if (isset($existingImages[$imageToCreate['md5']])) {
                unset($imagesToCreate[$key]);
                continue;
            }
            $existingImages[$imageToCreate['md5']] = 1;
        }

        $tempDirectory = $this->getUniqueDirectory('media');
        \mkdir($tempDirectory);
        foreach ($imagesToCreate as $imageToCreate) {
            $types = [];
            if (!$hasBaseImage) {
                $types[]      = 'image';
                $hasBaseImage = true;
            }
            if (!$hasSmallImage) {
                $types[]           = 'small_image';
                $hasSmallImage = true;
            }
            if (!$hasThumbnailImage) {
                $types[]           = 'thumbnail';
                $hasThumbnailImage = true;
            }
            $tempFilename = $tempDirectory . DIRECTORY_SEPARATOR . $imageToCreate['filename'];
            \file_put_contents($tempFilename, $imageToCreate['contents']);
            $mProduct->addImageToMediaGallery($tempFilename, $types, false, false);
            \unlink($tempFilename);
        }

        $mProduct->save();
        \rmdir($tempDirectory);

        return true;
    }

    public function relatedProductsUpdate($product, $mgProduct)
    {
        $existingProductLinks = [];
        foreach ($mgProduct->getProductLinks() as $productLink) {
            $existingProductLinks[$productLink->getLinkType()][$productLink->getLinkedProductSku()] = $productLink;
        }

        $mappings = [
            'related' => 'related',
            'up_sell' => 'upsell',
            'cross_sell' => 'crosssell',
        ];

        $productLinks = [];

        foreach ($mappings as $key => $mappedKey) {
            if (isset($product['associations'][$key])) {
                $newLinkedSkus = array_keys($this->getProductIdsBySkus($product['associations'][$key]));

                $existingLinkedSkus = [];
                if (isset($existingProductLinks[$mappedKey])) {
                    foreach ($existingProductLinks[$mappedKey] as $linkedSku => $linkData) {
                        $existingLinkedSkus[$linkData->getPosition()] = $linkedSku;
                    }
                }

                sort($newLinkedSkus);
                sort($existingLinkedSkus);

                $position = 0;
                foreach ($newLinkedSkus as $linkedSku) {
                    if (isset($existingProductLinks[$mappedKey][$linkedSku])) {
                        $existingLink = $existingProductLinks[$mappedKey][$linkedSku];
                        $existingLink->setPosition($position);
                        $productLinks[] = $existingLink;
                    } else {
                        $productLinks[] = $this->getLinkObject($product['sku'], $linkedSku, $mappedKey, $position);
                    }

                    $position++;
                }
            } else {
                if (isset($existingProductLinks[$mappedKey])) {
                    $position = 0;
                    foreach ($existingProductLinks[$mappedKey] as $productLink) {
                        $productLink->setPosition($position);
                        $productLinks[] = $productLink;
                    }
                    $position++;
                }
            }
        }

        $mgProduct->setProductLinks($productLinks);
    }
}
