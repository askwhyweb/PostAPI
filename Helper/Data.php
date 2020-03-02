<?php


namespace OrviSoft\Cloudburst\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\AttributeSet\Options;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Model\CategoryManagement;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Directory\Model\Currency;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ProductMetadata;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Api\Data\ProductLinkInterfaceFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\ConfigurableProduct\Api\Data\OptionValueInterface;
use Magento\Tax\Model\TaxClass\Source\Product as TaxClassSourceProduct;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Eav\Model\AttributeFactory;
use Magento\Eav\Model\AttributeRepository;
use Magento\Eav\Api\AttributeManagementInterface;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;

class Data extends AbstractHelper
{
    private $storeManagerInterface;
    private $productModel;
    private $productAttributeSetOptions;
    private $categoryFactory;
    private $categoryManagementInterface;
    private $productLinkFactory;
    private $directoryList;
    private $metadata;
    private $cache;
    private $objectManager;
    private $db;
    private $productRepositoryInterface;
    private $eavConfig;
    private $categoryRepositoryInterface;
    private $taxClassSourceProduct;
    private $attributeManagement;
    private $attributeFactory;
    private $attributeRepository;
    private $attributeOptionManagement;
    private $attributeOptionFactory;
    private $attributeOptionLabelFactory;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        ResourceConnection $resourceConnection,
        ProductRepositoryInterface $catalogProductRepositoryInterface,
        StoreManagerInterface $storeManagerInterface,
        Options $catalogProductAttributeSetOptions,
        Product $catalogProduct,
        CategoryLinkManagementInterface $catalogCategoryLinkManagementInterface,
        CategoryFactory $catalogCategoryFactory,
        CategoryManagement $catalogCategoryManagementInterface,
        CategoryRepositoryInterface $categoryRepositoryInterface,
        Currency $currencyModel,
        Config $eavConfig,
        ProductLinkInterfaceFactory $productLinkFactory,
        DirectoryList $directoryList,
        ProductMetadata $metadata,
        TaxClassSourceProduct $taxClassSourceProduct,
        AttributeManagementInterface $attributeManagement,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeFactory $attributeFactory,
        AttributeRepository $attributeRepository,
        AttributeOptionInterfaceFactory $attributeOptionFactory,
        AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory
    ) {
        parent::__construct($context);
        $this->db                           =
            $resourceConnection->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->objectManager                = ObjectManager::getInstance();
        $this->productRepositoryInterface   = $catalogProductRepositoryInterface;
        $this->storeManagerInterface        = $storeManagerInterface;
        $this->productAttributeSetOptions   = $catalogProductAttributeSetOptions;
        $this->productModel                 = $catalogProduct;
        $this->categoryFactory              = $catalogCategoryFactory;
        $this->categoryManagementInterface  = $catalogCategoryManagementInterface;
        $this->categoryRepositoryInterface  = $categoryRepositoryInterface;
        $this->eavConfig                    = $eavConfig;
        $this->productLinkFactory           = $productLinkFactory;
        $this->directoryList                = $directoryList;
        $this->metadata                     = $metadata;
        $this->cache                        = [];
        $this->taxClassSourceProduct        = $taxClassSourceProduct;
        $this->attributeManagement          = $attributeManagement;
        $this->attributeOptionManagement    = $attributeOptionManagement;
        $this->attributefactory             = $attributeFactory;
        $this->attributeRepository          = $attributeRepository;
        $this->attributeOptionFactory       = $attributeOptionFactory;
        $this->attributeOptionLabelFactory  = $attributeOptionLabelFactory;
    }

    public function getHostVersion()
    {
        return $this->metadata->getVersion();
    }

    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag('cloudburst/options/is_active',
                                             ScopeInterface::SCOPE_STORE
        );
    }
    public function getScopeConfig()
    {
        return $this->scopeConfig;
    }

    public function getAllWebsites()
    {
        if (!isset($this->cache['allWebsites'])) {
            $websites   = $this->storeManagerInterface->getWebsites();
            $stores     = $this->storeManagerInterface->getGroups();
            $storeViews = $this->storeManagerInterface->getStores();
            foreach ($websites as $id => $object) {
                $websites[$id] = $object->getData();
            }
            foreach ($stores as $id => $object) {
                $stores[$id] = $object->getData();
            }
            foreach ($storeViews as $id => $object) {
                $storeViews[$id] = $object->getData();
            }
            foreach ($storeViews as $storeView) {
                $stores[$storeView['group_id']]['storeViews'][$storeView['store_id']] = $storeView;
            }
            foreach ($stores as $store) {
                $websites[$store['website_id']]['stores'][$store['group_id']] = $store;
            }
            $this->cache['allWebsites'] = $websites;
        }
        return $this->cache['allWebsites'];
    }

    public function getObjectManager()
    {
        return $this->objectManager;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getProductRepository()
    {
        return $this->productRepositoryInterface;
    }

    public function getEavConfig()
    {
        return $this->eavConfig;
    }

    public function getWebsiteStoreViewMappings()
    {
        if (!isset($this->cache['websiteStoreViewMappings'])) {
            $websites                                     = $this->getAllWebsites();
            $this->cache['websiteStoreViewMappings']['0'] = 0;
            foreach ($websites as $websiteId => $websitesData) {
                $this->cache['websiteStoreViewMappings'][$websiteId] =
                    $websitesData['stores'][$websitesData['default_group_id']]['default_store_id'];
            }
        }
        return $this->cache['websiteStoreViewMappings'];
    }

    public function getCategoryRepositoryInterface()
    {
        return $this->categoryRepositoryInterface;
    }

    public function getAttributeManagement() {
        return $this->attributeManagement;
    }

    public function getAttributeOptionManagement() {
        return $this->attributeOptionManagement;
    }

    public function getAttributeRepository() {
        return $this->attributeRepository;
    }

    public function getAttributeFactory() {
        return $this->attributeFactory;
    }

    public function getAttributeOptionFactory() {
        return $this->attributeOptionFactory;
    }

    public function getAttributeOptionLabelFactory() {
        return $this->attributeOptionLabelFactory;
    }

    protected function splitAttributesByChannel($attributes)
    {
        $splitAttributes = [];
        foreach ($attributes as $label => $value) {
            preg_match("/^(?'attribute'[^-]+)(?:-)(?'website'[0-9]+)/", $label, $matches);
            if ($matches) {
                $trimmedLabel = $matches['attribute'];
                $website      = $matches['website'];
            } else {
                $trimmedLabel = $label;
                $website      = 0;
            }
            $splitAttributes[$website][$trimmedLabel] = $value;
        }
        return $splitAttributes;
    }

    protected function getProductCategoryTree()
    {
        if (!isset($this->cache['productCategoryTree'])) {
            $rootCategories = $this->categoryManagementInterface->getTree(1)
                                                                ->getChildrenData();
            $categoryTree = [];
            foreach ($rootCategories as $rootCategory) {
                $categoryTree[$rootCategory->getId()] = $this->formatProductCategoryNode($rootCategory->getData());
            }

            $this->cache['productCategoryTree'] = $categoryTree;
        }

        return $this->cache['productCategoryTree'];
    }

    protected function clearCache($key = null)
    {
        if ($key) {
            if (isset($this->cache[$key])) {
                unset($this->cache[$key]);
            }
        } else {
            $this->cache = [];
        }
    }

    private function formatProductCategoryNode($data)
    {
        $children = [];
        foreach ($data['children_data'] as $child) {
            $childData                   = $child->getData();
            $categoryName = $this->categoryFactory->create()->load($child->getId())->getName();
            $children[$categoryName] = $this->formatProductCategoryNode($childData);
        }
        unset($data['children_data']);
        $data['children'] = $children;
        return $data;
    }

    protected function getPriceAttributes($attributeSetId)
    {
        if ($attributeSetId === '') {
            return [];
        }

        $mAttributes = $this->attributeManagement->getAttributes(
            ProductAttributeInterface::ENTITY_TYPE_CODE,
            $attributeSetId
        );
        $attributes  = [];
        foreach ($mAttributes as $mAttribute) {
            $mAttributeData = $mAttribute->getData();
            $mAttributeName = $mAttributeData['frontend_label'];
            if (!$mAttributeName) {
                continue;
            }

            if ($mAttributeData['frontend_input'] !== 'price') {
                continue;
            }

            $attributes[$mAttributeData['attribute_code']] = $mAttributeData;
        }
        ksort($attributes);
        return $attributes;
    }

    protected function getAttributeSetAttributes($attributeSetId)
    {
        $mAttributes = $this->attributeManagement->getAttributes(
            ProductAttributeInterface::ENTITY_TYPE_CODE,
            $attributeSetId
        );
        $attributes  = [];
        foreach ($mAttributes as $mAttribute) {
            $mAttributeData = $mAttribute->getData();
            $mAttributeName = $mAttributeData['frontend_label'];
            if ($mAttributeName) {
                $mOptions = $this->eavConfig->getAttribute('catalog_product',
                                                           $mAttributeData['attribute_code']
                )
                                            ->getOptions();
                $options  = [];
                foreach ($mOptions as $mOption) {
                    $mOptionData = $mOption->getData();
                    $value       = $mOptionData['value'];
                    $label       = $mOptionData['label'];
                    if (!is_string($label)) {
                        $label = $label->getText();
                    }
                    $options[$label] = $value;
                }
                $mAttributeData['options']               = $options;
                $attributes[strtolower($mAttributeName)] = $mAttributeData;
            }
        }
        ksort($attributes);
        return $attributes;
    }

    protected function unCamelCase($string)
    {
        $re = '/(?#! splitCamelCase Rev:20140412)
    # Split camelCase "words". Two global alternatives. Either g1of2:
      (?<=[a-z])      # Position is after a lowercase,
      (?=[A-Z])       # and before an uppercase letter.
    | (?<=[A-Z])      # Or g2of2; Position is after uppercase,
      (?=[A-Z][a-z])  # and before upper-then-lower case.
    /x';
        $a  = preg_split($re, $string);
        return implode(' ', $a);
    }

    protected function unCamelCaseArrayKeys($original)
    {
        $new = [];
        foreach ($original as $key => $value) {
            $newKey                   = $this->unCamelCase($key);
            $new[strtolower($newKey)] = $value;
        }
        return $new;
    }

    protected function getTaxClassId($taxClassName = null)
    {
        if(!isset($this->cache['getTaxClassId'])){
            foreach ($this->taxClassSourceProduct->getAllOptions() ?: [] as $taxClassData){
                $taxClassDataName = is_string($taxClassData['label'])
                    ? $taxClassData['label']
                    : $taxClassData['label']->getText();
                $this->cache['getTaxClassId'][$taxClassDataName] = $taxClassData['value'];
            }
        }
        if(isset($this->cache['getTaxClassId'][$taxClassName])) {
            return $this->cache['getTaxClassId'][$taxClassName];
        }
        return 2;
    }

    public function packageUpdate($parts)
    {
        $updateData = [];
        foreach ($parts as $part) {
            $updateData = $this->arrayMerge($updateData, $part);
        }
        return $updateData;
    }

    public function arrayMerge($a, $b)
    {
        foreach ($b as $websiteId => $data) {
            if (!isset($a[$websiteId])) {
                $a[$websiteId] = $data;
            } else {
                $a[$websiteId] = array_merge_recursive($a[$websiteId], $data);
            }
        }
        return $a;
    }

    public function validateLinkedWebsites($product)
    {
        $allWebsites = [0 => 1];
        foreach ($this->getAllWebsites() as $websiteId => $websiteData) {
            $allWebsites[$websiteId] = 1;
        }

        $linkedWebsites = [];
        if (!isset($product['linked'])) { //All websites are linked
            foreach ($allWebsites as $websiteId => $websiteData) {
                $linkedWebsites[$websiteId] = true;
            }
            return $linkedWebsites;
        }

        foreach ($product['linked'] as $productLinkedWebsiteId => $value) {
            if (isset($allWebsites[$productLinkedWebsiteId])) {
                $linkedWebsites[$productLinkedWebsiteId] = $value === 'Yes';
            }
        }

        return $linkedWebsites;
    }

    protected function floatEquals($a, $b, $margin = 0)
    {
        if (is_numeric($a) && is_numeric($b)) {
            $aFloat = (float) $a;
            $bFloat = (float) $b;
            $diff   = abs($aFloat - $bFloat);
            if ($diff <= $margin) {
                return true;
            }
        }
        return false;
    }

    protected function tierPricesMatch($a, $b)
    {
        if (count($a) != count($b)) {
            return false;
        }
        $attributesToMatch = [
            'website_id',
            'price',
            'price_qty',
            'cust_group',
        ];
        for ($i = 0; $i < count($a); $i++) {
            for ($j = $i; $j < count($b); $j++) {
                foreach ($attributesToMatch as $attribute) {
                    if ($a[$i][$attribute] != $b[$j][$attribute]) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public function getProductIdsBySkus($skus)
     {
        $skus = (array)$skus;
        $ids = [];
        foreach ($skus as $sku) {
            try {
                $product = $this->productRepositoryInterface->get($sku, false, 0);
            }
            catch (NoSuchEntityException $exception) {
                continue;
            }
            $ids[$sku] = $product->getId();
         }

         return $ids;

    }

    public function getSingleProductId($sku)
    {
        $ids = $this->getProductIdsBySkus($sku);
        if (count($ids) === 1) {
            return $ids[$sku];
        } else {
            return ['numfound' => count($ids)];
        }
    }

    protected function getTierPricesBySku($sku)
    {
        $mProduct     = $this->productRepositoryInterface->get($sku, false, 0);
        $mProductData = $mProduct->getData();
        if (!isset($mProductData['tier_price'])) {
            return [];
        }
        $mTierPrices                = $mProductData['tier_price'];
        $currentTierPricesByWebsite = [];
        foreach ($mTierPrices as $currentTierPrice) {
            $currentTierPricesByWebsite[$currentTierPrice['website_id']][] = $currentTierPrice;
        }
        return $currentTierPricesByWebsite;
    }

    protected function getCategoryIds($product)
    {
        if (!isset($product['categories']) || !is_array($product['categories'])) {
            return [];
        }

        $productCategories = $product['categories'];

        $addAllParent = false;
        if ($addAllParent === true) {
            foreach ($productCategories as $websiteId => $websiteCategories) {
                foreach ($websiteCategories as $categoryPath => $categoryData) {
                    $categoryParts = preg_split('/\s*>\s*/', $categoryPath);

                    $parentCategoryParts = [];
                    foreach ($categoryParts as $categoryPart) {
                        $parentCategoryParts[] = $categoryPart;

                        $parentCategory = implode(' > ', $parentCategoryParts);

                        if (!isset($websiteCategories[$parentCategory])) {
                            $productCategories[$websiteId][$parentCategory] = [];
                        }
                    }
                }
            }
        }

        $currentStoreId = $this->storeManagerInterface->getStore()
                                                      ->getId();
        $categories  = $productCategories;
        $allWebsites = $this->getAllWebsites();
        $categoryIds = [];

        $categoryTree = $this->getProductCategoryTree();

        foreach ($categories as $websiteId => $category) {
            $websiteDefaultGroupId = $allWebsites[$websiteId]['default_group_id'];
            $websiteDefaultStoreId = $allWebsites[$websiteId]['stores'][$websiteDefaultGroupId]['default_store_id'];

            $this->storeManagerInterface->setCurrentStore($websiteDefaultStoreId);

            $rootCategoryId = $allWebsites[$websiteId]['stores'][$websiteDefaultGroupId]['root_category_id'];

            if (!isset($categoryTree[$rootCategoryId])) {
                continue;
            }

            $categoryIds[$rootCategoryId] = [];

            foreach ($category as $categoryPath => $categoryData) {
                $categoryStack = explode(' > ', $categoryPath);

                $pointer = &$categoryTree[$rootCategoryId];
                foreach ($categoryStack as $stackItem) {
                    if (!isset($pointer['children'][$stackItem])) {
                        $parentId        = $pointer['entity_id'];
                        $newCategoryData = $this->createCategory($stackItem, $parentId);
                        $pointer['children'][$stackItem]         = $newCategoryData;
                        $pointer = &$pointer['children'][$stackItem];

                        $this->cache['productCategoryTree'] = $categoryTree;
                    } else {
                        $pointer = &$pointer['children'][$stackItem];
                    }
                }
                $categoryIds[$rootCategoryId][] = $pointer['entity_id'];

            }

        }

        $this->storeManagerInterface->setCurrentStore($currentStoreId);

        return $categoryIds;
    }

    protected function getAttributeSets($attributeSetId = null)
    {
        return $this->productAttributeSetOptions->toOptionArray();
    }

    protected function getAttributeSetId($attributeSetName = null)
    {
        if (!$attributeSetName) {
            return $this->productModel->getDefaultAttributeSetId();
        }
        $attributeSets = $this->getAttributeSets();
        foreach ($attributeSets as $attributeSet) {
            if ($attributeSetName == $attributeSet['label']) {
                return $attributeSet['value'];
            }
        }
        return null;
    }

    private function createCategory($name, $parentId = 1)
    {
        $category = $this->categoryFactory->create();

        $category->setData([
                               "parent_id" => $parentId,
                               "name"      => $name,
                               "is_active" => true,
                           ]
        );
        $category->setCustomAttributes([
                                           "display_mode" => "PRODUCTS",
                                       ]
        );
        $categoryId    = $this->categoryRepositoryInterface->save($category)
                                                           ->getId();
        $mCategoryData = $this->categoryRepositoryInterface->get($categoryId)
                                                           ->getData();
        return $mCategoryData;
    }

    protected function createConfigurableAttributeOption($valueLabel, $valueIndex)
    {
        $opValue = $this->objectManager->create(OptionValueInterface::class);
        $opValue->setValueIndex($valueIndex);
        $opValue->setLabel($valueLabel);
        return $opValue;
    }

    protected function getDirectory($dir)
    {
        return $this->directoryList->getPath($dir);
    }

    protected function getUniqueDirectory($dir)
    {
        $tempDirectory = $this->getDirectory($dir);
        while (true) {
            $dir = uniqid((string) time(), true);
            if (!file_exists($tempDirectory . DIRECTORY_SEPARATOR . $dir)) {
                break;
            }
        }
        return $tempDirectory . DIRECTORY_SEPARATOR . $dir;
    }

    protected function getFileContents($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, str_replace(' ', '%20', $url));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $contents = curl_exec($curl);
        curl_close($curl);
        return $contents;
    }

    protected function getLinkObject($parentSku, $linkedSku, $linkType, $position)
    {
        $linkedObject = $this->productLinkFactory->create();

        $linkedObject->setSku($parentSku)
                     ->setLinkedProductSku($linkedSku)
                     ->setPosition($position)
                     ->setLinkType($linkType);
        return $linkedObject;
    }
}
