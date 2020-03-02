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
use OrviSoft\Cloudburst\Helper\Data as DataHelper;

class Action extends AbstractHelper
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
    private $_helper;
    protected $_productRepository;
    protected $productFactory;
    protected $stockRegistry;
    protected $attribute_helper;
    protected $_category;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    
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
        AttributeOptionLabelInterfaceFactory $attributeOptionLabelFactory,
        DataHelper $helperData,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \OrviSoft\Cloudburst\Helper\Attributes $attribute_helper,
        \OrviSoft\Cloudburst\Helper\Category $category
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
        $this->_helper                      = $helperData;
        $this->_productRepository           = $productRepository;
        $this->productFactory               = $productFactory;
        $this->stockRegistry                = $stockRegistry;
        $this->searchCriteriaBuilder        = $searchCriteriaBuilder;
        $this->attribute_helper             = $attribute_helper;
        $this->_category                    = $category;
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

    public function process($post, $escape=false){
        if(!$escape){
            if(!isset($post['secret'])){
                $valid = false;
            }else{
                $valid = $this->validateSecret($post['secret']);
            }
        }
        if(!$valid){
            echo json_encode(['errors' => 'Plugin secret misconfigured.']);
            return;
        }
        
        $type = '';

        if(isset($post['type'])){
            $type = $post['type'];
        }

        switch ($type) {
            case "product":
                $output = $this->processProduct($post);
                break;
            case "order":
                $output = $this->processOrder($post);
                break;
            case "image":
                $output = $this->processProductImages($post);
                break;
            case "stock":
                $output = $this->processProductStock($post);
                break;
            default:
                $output = ['errors' => 'Data type ('.$type.') is not defined.'];
        }
        $output = json_encode($output);
        echo $output;
        //print_r($post);
    }

    public function validateSecret($secret){
        $internal_secret = $this->scopeConfig->getValue('cloudburst/options/burst_secret',ScopeInterface::SCOPE_STORE);
        if(trim($secret) == trim($internal_secret)){
            return true;
        }
        return false;
    }
    
    public function processProduct($post){
        if(!isset($post['data']) || (isset($post['data']) && !strlen($post['data']))){
            return ['error' => "invalid data."];
        }
        $productData = json_decode($post['data'], true);
        $_product = $this->loadProductBySku($productData['sku']);
        if($_product){
            // update product
            $this->updateProduct($_product, $productData);
        }else{
            $this->createNewProduct($productData['sku'], $productData);
        }
        
        return ['status' => 'processing product'];
    }

    public function loadProductBySku($sku){
        $product = $this->productFactory->create();
        if((int)$product->getIdBySku($sku) == 0){
            return false;
        }
        try {
            return $this->productRepositoryInterface->get($sku, true, 0, true);
        }
        catch (NoSuchEntityException $exception) {
            print_r($exception);
            return false;
        }
        catch(Exception $e){
            echo $e->getMessage();
            return false;
        }
    }

    public function getProductBySku($sku)
	{
        try {
            return $this->_productRepository->get($sku);
        }catch (NoSuchEntityException $exception) {
            return false;
        }
    }
    protected $attribute_collection;
    public function getProductAttributes($attribute = ''){
        if(!isset($this->attribute_collection)){
            // lets fetch and set the attribute_collection for once. // cache mechanism.
            $output = [];
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('frontend_input', ['in' => ['select', 'multiselect', 'swatch_visual', 'swatch_text']], 'in' )->create();
            $attributeRepository = $this->attributeRepository->getList(
                'catalog_product',
                $searchCriteria
            );
        
            foreach ($attributeRepository->getItems() as $items) {
                $values = array();
                foreach ($items->getOptions() as $options) { 
                   //$manufacturerOption->getValue();  // Value
                    $values[(string)$options->getValue()] = (string)$options->getLabel();  // Label
                }
                //print_r($values);
                //exit;
                $output[$items->getAttributeCode()] =  array_merge($items->getData(), ['options' => $values]); //['frontend_input' => $items->getFrontendIinput()];
            }

            $this->attribute_collection = $output;
        }

        if($attribute == ''){
            return $this->attribute_collection;
        }

        if(strlen($attribute) > 0 && isset($this->attribute_collection[$attribute])){
            return $this->attribute_collection[$attribute];
        }

        return ['frontend_input' => 'undefined'];
    }


    public function createNewProduct($sku, $data){
        if(!strlen($sku)){
            echo "SKU $sku seems incomplete. A";
            print_r($data);
            return;
        }
        try{
            $_product = $this->productModel;
            $_product->setTypeId('simple');
            $_product->setAttributeSetId(12); // hard coded.
            $_product->setSku($sku);
            $_product->setWebsiteIds(array(1));
            $_product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
            $_product->setPrice(array(0));
            $_product->setStatus(1);
            $_product->setStockData(array(
                    'use_config_manage_stock' => 0, //'Use config settings' checkbox
                    'manage_stock' => 1, //manage stock
                    'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
                    'is_in_stock' => 0, //Stock Availability
                    'qty' => 0 //qty
                    )
                );
            if(is_array($data)):
                if(!isset($data['weight'])){
                    $data['weight'] = 500;
                }
                foreach($data as $attribute => $value){
                    if($attribute == 'name'){
                        $urlKey = $this->createUrlKey($value, $sku);
                        $_product->setUrlKey($urlKey); // url key of the product
                    }
                    if($attribute == 'qty'){
                        $_product->setStockData(['qty' => $value, 'is_in_stock' =>(int)$value > 0 ? 1 : 0]);
                        $_product->setQuantityAndStockStatus(['qty' => $value, 'is_in_stock' => (int)$value > 0 ? 1 : 0]);
                    }else{
                        $_product->setData($attribute,$value);
                    }
                }
            endif;
            $_product->save();
        } catch (Exception $e){
            return var_dump($e);
        }
        $this->updateProduct($_product, $data);
        return true;
    }

    public function updateProduct($product, $data){
        foreach($data as $attribute => $value){
            if($attribute == 'categories'){
                $category_ids = [];
                foreach($value as $_categories){
                    unset($_categories[0]);
                    $root_category = $this->storeManagerInterface->getStore()->getRootCategoryId();
                    $category_ids[] = $root_category;
                    foreach($_categories as $key => $_category){
                        $root_category = $this->_category->validateCategory($_category, $root_category);
                        $category_ids[] = $root_category;
                    }
                }
                $product->setCategoryIds($category_ids);
                continue 1;
            }
            
            if($attribute == 'qty'){
                $product->setStockData(['qty' => $value, 'is_in_stock' =>(int)$value > 0 ? 1 : 0]);
                $product->setQuantityAndStockStatus(['qty' => $value, 'is_in_stock' => (int)$value > 0 ? 1 : 0]);
                continue 1;   
            }

            if($attribute == 'images'){
                $this->processProductGallery($product, $value);
                continue 1;
            }

            if($attribute == 'special_price'){
                if((float)$value > 0){
                    $product->setData($attribute,$value);
                }else{
                    $product->setData($attribute, null);
                }
                continue 1;
            }

            try{
                $temp = $this->getProductAttributes($attribute);
                if(in_array($temp['frontend_input'], ['select', 'multiselect', 'swatch_visual', 'swatch_text'])){
                    
                    $product->addAttributeUpdate($attribute, $value, 0);
                    $product->getResource()->saveAttribute($product,$attribute);
                    $product->save($product);

                    $attr = $product->getResource()->getAttribute($attribute);
                    $option_id = $attr->getSource()->getOptionId($value);
                    if((int)$option_id == 0){
                        $option_id = $this->attribute_helper->createOrGetId($attribute, $value);
                    }
                    
                    $product->setData($attribute,$option_id);
                }else{
                    $product->setData($attribute,$value);
                }
            }catch (\Exception $e) {
                $message = $e->getMessage();
                echo $message;
            }
        }
        $product->save($product);
        return true;
    }

    public function processProductImages($post){
        if(!isset($post['data']) || (isset($post['data']) && !strlen($post['data']))){
            return ['error' => "invalid data."];
        }
        $productData = json_decode($post['data'], true);
        if(!isset($productData['images'])){
            return ['status' => 'No images for processing against SKU ('.$productData['sku'].')'];
        }
        $_product = $this->loadProductBySku($productData['sku']);
        if($_product){
            // update product images
            $images = $productData['images'];
            $this->processProductGallery($_product, $images);
            $_product->save();
        }else{
            return ['error'=> true, 'status' => 'SKU ('.$productData['sku'].') not found.'];
        }
		return ['status' => 'processing images'];
    }

    public function processProductStock($post){
        if(!isset($post['data']) || (isset($post['data']) && !strlen($post['data']))){
            return ['error' => "invalid data."];
        }
        $productData = json_decode($post['data'], true);
        $_product = $this->loadProductBySku($productData['sku']);
        if($_product){
            // update product stock
            // $value = $productData['qty'];
            // $_product->setStockData(['qty' => $value, 'is_in_stock' =>(int)$value > 0 ? 1 : 0]);
            // $_product->setQuantityAndStockStatus(['qty' => $value, 'is_in_stock' => (int)$value > 0 ? 1 : 0]);
            // $_product->save();
            $this->updateProduct($_product, $productData);
        }else{
            return ['error'=> true, 'status' => 'SKU ('.$productData['sku'].') not found.'];
        }
		return ['status' => 'processing stock'];
    }

    public function processOrder($post){
		return ['status' => 'processing order'];
    }

    public function processProductGallery($_product, $images, $old = true){
        if(is_array($images) && count($images) > 0){
            $first = true;
            $old_images = [];
            if($old){
                $old_images = $this->getPreviousImages($_product);
            }
            $directory = $this->getUniqueDirectory('media');
            mkdir($directory);
            foreach($images as $_image_url){
                $types = [];
                if($first){
                    $types = ['image', 'small_image', 'thumbnail'];
                    $first = false;
                }
                $imageContent = $this->getImage($_image_url);
                $fileName = md5($_image_url);
                $tempFile = $this->saveImageTemporary($directory, $imageContent, $fileName, $old_images);
                if($tempFile != false){
                    $_product->addImageToMediaGallery($tempFile, $types, false, false);
                    unlink($tempFile);
                }
            }
            rmdir($directory);
        }
    }

    function getPreviousImages($_product){
        $images = $_product->getMediaGalleryEntries() ?: [];
        $output = [];
        foreach($images as $image){
            $output[]= $this->getDirectory('media'). DIRECTORY_SEPARATOR . 'catalog' . DIRECTORY_SEPARATOR . 'product' . $image->getData('file');
        }
        return $output;
    }

    function validateImage($image, $local_images){
        $image = md5(file_get_contents($image));
        foreach($local_images as $_image){
            $md5image2 = md5(file_get_contents($_image));
            if ($image == $md5image2) {
                return true;
            }
        }
        return false;
    }

    function saveImageTemporary($directory, $content, $fileName, $old_images = []){
        // save the content downloaded in the given directory above.
        $mineTypes         = [
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/png'  => 'png',
        ];
        $tempFilename = tempnam($directory, 'prefix');
        file_put_contents($tempFilename, $content);
        $imageMimeType = mime_content_type($tempFilename);
        if(is_array($old_images) && count($old_images) > 0){
            $exists = $this->validateImage($tempFilename, $old_images);
            unlink($tempFilename);
            if($exists){
                return false;
            }
        }else{
            unlink($tempFilename);
        }
        if (!isset($mineTypes[$imageMimeType])) {
            return false;
        }
        $imageExtension = $mineTypes[$imageMimeType];
        $tempFilename = $directory . DIRECTORY_SEPARATOR . $fileName. '.'.$imageExtension;
        file_put_contents($tempFilename, $content);
        return $tempFilename;
    }

    function getImage($image)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, str_replace([' '], ['%20'], $image));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $image_contents = curl_exec($curl);
        curl_close($curl);
        return $image_contents;
    }

    function getDirectory($dir)
    {
        return $this->directoryList->getPath($dir);
    }

    function getUniqueDirectory($dir)
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

    public function createUrlKey($title, $sku) 
    {
        $url = preg_replace('#[^0-9a-z]+#i', '-', $title);
        $sku = preg_replace('#[^0-9a-z]+#i', '-', $sku);
        $urlKey = strtolower($url);
        $storeId = (int) $this->storeManagerInterface->getStore()->getStoreId();

        $isUnique = $this->checkUrlKeyDuplicates($sku, $urlKey, $storeId);
        if ($isUnique) {
            return $urlKey;
        } else {
            return $urlKey . '-' . $sku;
        }
    }

    /*
    * Function to check URL Key Duplicates in Database
    */

    private function checkUrlKeyDuplicates($sku, $urlKey, $storeId) 
    {
        $urlKey .= '.html';

        $connection = $this->db;

        $tablename = $connection->getTableName('url_rewrite');
        $sql = $connection->select()->from(
                        ['url_rewrite' => $connection->getTableName('url_rewrite')], ['request_path', 'store_id']
                )->joinLeft(
                        ['cpe' => $connection->getTableName('catalog_product_entity')], "cpe.entity_id = url_rewrite.entity_id"
                )->where('request_path IN (?)', $urlKey)
                ->where('store_id IN (?)', $storeId)
                ->where('cpe.sku not in (?)', $sku);

        $urlKeyDuplicates = $connection->fetchAssoc($sql);

        if (!empty($urlKeyDuplicates)) {
            return false;
        } else {
            return true;
        }
    }

}