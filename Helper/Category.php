<?php
namespace OrviSoft\Cloudburst\Helper;

class Category extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $_storeManager;
    protected $categoryFactory;
    protected $_collection;
    protected $_categoryFactory;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryFactory,
        \Magento\Catalog\Model\CategoryFactory $_categoryFactory,
        $data = []
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->_categoryFactory = $_categoryFactory;
    }

    function getCategories(){
        $output = [];
        $categoryFactory = $this->categoryFactory;
        $categories = $categoryFactory->create()                              
                            ->addAttributeToSelect('*')
                            ->setStore($this->_storeManager->getStore()); //categories from current store will be fetched

        foreach ($categories as $category){
            $data = ['id'=> $category->getData('entity_id'), 'parent'=> $category->getData('parent_id'), 'path' => $category->getData('path'), 'name' => $category->getData('name')];
            $output[] = $data;
        }
        return $this->_collection = $output;
    }

    function getCategory($category_name, $parent =1, $level =0){
        if(!$this->_collection){
            $this->getCategories();
        }

        foreach($this->_collection as $_category){
            if($_category['name'] == $category_name && $_category['parent'] == $parent){
                return $_category['id'];
            }
        }
        return false; // no such category exists at said location (by parent)
    }

    function validateCategory($category_name, $parent = 1){
        $_category = $this->getCategory($category_name, $parent);
        if(!$_category){
            // lets create a new category.
            $_category = $this->createCategory($category_name, $parent);
        }
        return $_category;
    }

    function getParentPath($id){
        if(!$this->_collection){
            $this->getCategories();
        }

        foreach($this->_collection as $_category){
            if($_category['id'] == $id){
                return $_category['path'];
            }
        }
        return false;
    }

    function createCategory($category_name, $parent){
        $websiteId = $this->_storeManager->getWebsite()->getWebsiteId();
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $root_id = $this->_storeManager->getStore()->getRootCategoryId();
        $name=$category_name;
        $url=strtolower($category_name);
        //$cleanurl = trim(preg_replace('/ +/', '-', preg_replace('/[^A-Za-z0-9 ]/', '', urldecode(html_entity_decode(strip_tags($url))))));
        /// Add a new sub category under root category
        $categoryTmp = $this->_categoryFactory->create();
        $categoryTmp->setName($name);
        $categoryTmp->setIsActive(true);
        if($parent != $root_id){
            $categoryTmp->setIncludeInMenu(true); // set only categories to include in menu which are non root categories.
        }
        //$categoryTmp->setUrlKey($cleanurl);
        //$categoryTmp->setData('description', 'description'); // description not required to be set by here...!
        $categoryTmp->setParentId($parent);
        //$mediaAttribute = array ('image', 'small_image', 'thumbnail');
        //$categoryTmp->setImage('/m2.png', $mediaAttribute, true, false);// Path pub/meida/catalog/category/m2.png
        $categoryTmp->setStoreId($storeId);
        $categoryTmp->setPath($this->getParentPath($parent));
        $categoryTmp->save();
        $this->_collection = false;
        return $categoryTmp->getId();
    }
}