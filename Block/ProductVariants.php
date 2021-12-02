<?php

namespace Genaker\ProductVariants\Block;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Setup\Exception;

class ProductVariants extends \Magento\Catalog\Block\Product\View\AbstractView
{

    /* You shuld add maping betwing Variants list atribute and real value attribute
     */

    protected $variationAttributes =
        [
        ['color_variants' => 'spec_design' /*'filtercolor'*/],
        ['size_variants' => 'filtersize'],
        //ToDo;
        //['tiles_size_variants' => 'tiles_size']
    ];

    // different logic based on configuration by default it is text swatch...
    public $varidationAttributesConfig = [
        'spec_design' => 'swatch',
    ];

    protected $objectManager;
    protected $storeManager;
    protected $productInst;
    protected $swatchCollection;
    protected $deploymentConfig;
    protected $searchCriteriaBuilder;
    protected $productCollection;

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Stdlib\ArrayUtils $arrayUtils,
        \Magento\Catalog\Model\ProductRepository $productInst,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Swatches\Model\ResourceModel\Swatch\Collection $swatchCollection,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        $this->productInst = $productInst;
        $this->storeManager = $context->getStoreManager();
        $this->swatchCollection = $swatchCollection;
        $this->productCollection = $productCollection;
        $this->deploymentConfig = $deploymentConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        parent::__construct(
            $context,
            $arrayUtils,
            $data
        );
    }

    public function getProductSwatchersVariants()
    {
        $swatch = [];
        foreach ($this->variationAttributes as $variationAttribute) {
            $swatch[key($variationAttribute)] = $this->getProductVariantSwatchesByAttribute($variationAttribute);
        }
        return $swatch;
    }

    public function isSwatch($attraibuteCode)
    {
        if (isset($this->varidationAttributesConfig[$attraibuteCode])
            && $this->varidationAttributesConfig[$attraibuteCode] === 'swatch') {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getProductVariantSwatchesByAttribute($attribute)
    {
        echo "\n <!-- \n";
        echo "<h1> Attribute : " . key($attribute) . "</h1>";
        echo "\n --> \n";

        $products = $this->getProductVariantsIds(key($attribute));

        echo "\n <!-- \n";
        echo "Variant Products: " . implode('->', $products);
        echo "\n --> \n";

        //die();
        //$attribute->getError();
        $swatch = [];
        $swatchOptionIds = [];
        $attributeId = null;
        $n = 1;

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('sku', $products, 'in')->create();

        $attributesToSellect = ['entity_id','swatch_image', 'media_gallery',
                                'small_image', 'name', 'status', 'sku', 
                                'attribute_set_id', 'type_id', 'has_options', 
                                'required_options', 'is_salable', 'color_variants', 
                                'size_variants', 'spec_design', 'filtersize'];
        
        $collection = $this->productCollection->create()
        ->addAttributeToSelect($attributesToSellect)
        ->addAttributeToFilter('status', array('eq' => 1))
        ->addAttributeToFilter('sku', array('in' => $products))
        ///->addStoreFilter()
        ->load();

        $productIdObj = [];
        foreach($collection as $p){
            $productIdObj[$p->getSku()] = $p;
        }


        $productsF = $this->getProductWithoutBrokenMagento($skus = $products, $attributesToSellect);
        //echo "<pre>";
        //var_dump($productsF);
        //echo "</pre>";
        // Warm up the product Reposetorycache
        //$start1 = microtime(true);
        $productsObj = $productsF; //$this->productInst->getList($searchCriteria)->getItems();
        //$end1 = microtime(true);

        //Check missung SKUS
        $foundSKUs = [];
        foreach ($productsObj as $pr) {
            $foundSKUs[$pr['sku']] = 1;
        }

        $k = 0;
        foreach ($products as $pr) {
            //echo $pr . "--";
            if (!isset($foundSKUs[$pr])) {
                unset($products[$k]);
                echo "\n" . '<!-- SKU ' . $pr . ' not found check input data -->' . "\n";
            }
            $k++;
        }

        // echo 'Collection (' . count($products) .' elements) load time: ' . ($end1 - $start1);

        foreach ($productsObj as $obj) {
            $sku = $obj['sku'];
            if (!$sku || $sku === '') {
                continue;
            }

            try {
                $variationProduct = $obj;
            } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
                continue;
            }

            // echo "\n <!-- \n";
            // echo "<br> Product Variant ID " . $variationProduct->getSKU() . "<br>";
            //die(key($attribute));
            // echo "\n --> \n";
            $productVariantAttribute = $attribute[key($attribute)];

            $optionValue = null;
            //basically any attribut not just collor it is option value
            if (isset($variationProduct['attributes'][$productVariantAttribute])) {
                $optionValue = $variationProduct['attributes'][$productVariantAttribute];
            }
            if ($optionValue === null) {
                continue;
            }
            //$optionValue = 'No Data';

            //echo "\n <!-- \n";
            //   echo "Attribut Value " . $optionValue;
            //   echo "\n --> \n";

            // if it is image swatch
            if (!$this->isSwatch($productVariantAttribute)) {
                $this->swatchCollection->addFieldtoFilter('option_id', $optionValue);
                $item = $this->swatchCollection->getFirstItem();
                $this->swatchCollection->getSelect()->reset('where');
                $swatch[$sku] = $item->getData();
                $this->swatchCollection->clear();
                // echo "\n <!-- \n";
                //  echo "<br> SwatchData -> "; var_dump($swatch[$sku]);
                //  echo "<br>";
                // echo "\n --> \n";

            }

            if (isset($swatch[$sku]['swatch_id'])) {
                $swatchKey = $swatch[$sku]['swatch_id'];
            } else {
                $swatchKey = false;
            }

            if (!$swatchKey) {
                $swatchKey = "not_swatch_" . $optionValue;
                //$swatchOptionIds = false;
            }

            $swatchOptionIds[$optionValue] = $optionValue;

            $swatch['data'] = $this->getProduct()->getResource()
                ->getAttribute($attribute[key($attribute)])->getData();

            $attributeId = $swatch['data']['attribute_id'];

            $label = $this->getProduct()->getResource()->getAttribute(key($attribute))->getStoreLabel();
            $swatch['data']['label'] = $label;

            if (isset($swatch[$sku]["option_id"]) || !@$swatch[$sku]["option_id"]) {
                $swatch[$sku]["option_id"] = $optionValue;
            }

            $swatch[$sku]['product_title'] = $variationProduct['attributes']['name'];

            $attr = $this->getProduct()->getResource()->getAttribute($swatch['data']['attribute_code']);
            $swatch[$sku]['attribute_value'] = $this->getProduct()->getAttributeText($productVariantAttribute);

            /*
            echo "<br> Attribute Text Value ";
            var_dump($swatch[$sku]['attribute_value']);
            echo "<\br>";
             */
            $optionText = $swatch[$sku]['attribute_value'];

            // Differernt yype of images you can use:

            // $variationProduct->getData('image'));
            // $variationProduct->getData('small_image'));
            // $variationProduct->getData('thumbnail'));
            // $prodvariationProductuct->getData('swatch_image'));]
            $swatchHelper = $this->objectManager->get("Magento\Swatches\Helper\Media"); 
            $imageReader = $this->objectManager->get('Magento\Catalog\Model\Product\Gallery\ReadHandler');
            $imageHelper = $this->objectManager->get('Magento\Catalog\Helper\Image');
            //$imageReader->execute($variationProduct);
            //$swatchImage = $swatchHelper->getSwatchAttributeImage('swatch_image', $variationProduct->getValue());
            //$thumbImage =  $swatchHelper->getSwatchAttributeImage('swatch_thumb', $_product->getValue());
            
            //$mediaGallery = $variationProduct->getMediaGalleryImages();

           // die();
            //var_dump($mediaGallery->getData());

            if ($variationProduct['attributes']['swatch_image']){
                $swatchImageType = 'swatch_image_base';
            } else if ($variationProduct['attributes']['small_image']) {
                $swatchImageType = 'product_small_image';
            } else {
                $swatchImageType = 'product_base_image';
            }
       
            $productModel = $productIdObj[$variationProduct['sku']];
            $imageURL = $imageHelper->init($productModel, $swatchImageType)->constrainOnly(true)
                        ->resize(100, 100)
                        ->getUrl();

            //if (!strpos($imageURL, 'placeholder')) {
                $swatch[$sku]['image'] = $imageURL;
            //}

            $swatch[$sku]['option_text'] = $optionText;
            $swatch[$sku]["product_url_key"] = $variationProduct['url_rewrites'];

        }

        $fromSql = $this->getSwatchesData($swatchOptionIds);

        if (count($swatch) === 1 || $swatch === false) {
            return false;
        }

        foreach ($swatch as $sku => $sw) {
            $swatch[$sku]['sku'] = $sku;
            if (isset($sw['option_id']) && isset($fromSql[$sw['option_id']]['option_id'])) {
                if ($sw['option_id'] == $fromSql[$sw['option_id']]['option_id'] ||
                    $sw['attribute_value'] == $fromSql[$sw['option_id']]['value']) {
                    if (isset($swatch[$sku]['sort_order'])) {
                        $swatch[$sku]['sort_order'] = $fromSql[$sw['option_id']]['sort_order'];
                    }

                }
            }

        }

        $swatchsorted = $swatch;

        if (isset($swatchsorted['data']['sort_order']) && $swatchsorted['data']['sort_order'] == null) {
            $swatchsorted['data']['sort_order'] = 0;
        }

        $tmpData = false;
        if(isset($swatchsorted['data'])) {
            $tmpData = $swatchsorted['data'];
            unset($swatchsorted['data']);
        }

        if (count($swatchsorted) > 2) {
            // ToDo: improve and fix;
            $toSort = [];
            if (key($attribute) == 'size_variants') {

                foreach ($swatchsorted as $key => $row) {
                        $toSort[$key] = (int)$row['option_text'];
                }

                array_multisort($toSort, SORT_ASC, $swatchsorted);
            }
        }

        if($tmpData !== false) {
            $swatchsorted['data'] = $tmpData;
        }

        $revertAssocArray = [];
        foreach ($swatchsorted as $item) {
            $revertAssocArray[$item['sku']] = $item;
        }

        return $revertAssocArray;
    }

    public function getCurrentProductSKU()
    {
        return $this->getProduct()->getSku();
    }

    public function getProductVariantsIds($attribute)
    {
        $products = [];
        $currentProduct = $this->getProduct();
        $currentSku = $currentProduct->getSku();
        $variantIds = $currentProduct->getData($attribute);
        $variantIds = trim(trim($variantIds, ','), '|');

        if (strstr($variantIds, ',')) {
            $products = explode(',', str_replace(' ', '', $variantIds));
        } else {
            $products = explode('|', $variantIds);
        }

        if (count($products) === 1 && $products[0] === '') {
            $products = [];
        }

        //adding current SKU to variants it will be active on front end
        if (count($products) > 0) {
            $products[] = $currentSku;
        }

        sort($products);
        return $products;
    }

    public function getAttributeTitle($code)
    {
        $currentProduct = $this->getProduct()->getAttributeText($code);
    }

    public function getSwatchesData($options = null)
    {

        //var_dump($options); die(555);
        if ($options === null || $options === false || count($options) === 0 || array_values($options)[0] == null) {
            return false;
        }
        $storeId = $this->storeManager->getStore()->getId();

        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        //TO DO: Move to Model/create variant model
        $tb_prefix = $this->deploymentConfig->get('db/table_prefix');

        if ($tb_prefix) {
            $tb_prefix = trim($tb_prefix, '_');
            $tb_prefix = $tb_prefix . "_";
        }

        $sql = "SELECT " .
        "aov.value_id as value_id,
             aov.option_id as option_id,
             aov.store_id as store_id,
             aov.value_id as value_id,
             aov.value as value,
             aov.store_id as value_store_id,
             ao.attribute_id as attribute_id,
             ao.sort_order as sort_order,
             aos.swatch_id as swatch_id,
             aos.option_id as sw_option_id,
             aos.store_id as sw_store_id,
             aos.type as sw_type,
             aos.value as sw_value"
        . " FROM " . $tb_prefix . "eav_attribute_option_value as aov" .
        " left join " . $tb_prefix . "eav_attribute_option as ao on aov.option_id=ao.option_id " .
        " left join " . $tb_prefix . "eav_attribute_option_swatch as aos on ao.option_id =aos.option_id" .
        " where ao.option_id in ('" . implode($options, '\',\'') . "') or aos.option_id in ('" . implode($options, '\',\'') . "')";

        //  echo $sql;

        $options = [];

        $start2 = microtime(true);

        $result = $connection->fetchAll($sql);

        $end2 = microtime(true);

        //echo 'SQL  load time: ' . ($end2 - $start2);

        foreach ($result as $option) {
            $options[$option['option_id']] = $option;
        }
        return $options;
    }

    public function getProductWithoutBrokenMagento($skus, $attributes){
        $timeS = microtime(true);
        $db = new \Genaker\Laragento\DB();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /* Create a new product object */
        //$product = $objectManager->create(\Magento\Catalog\Model\Product::class);
        /* Get a request object singleton */
        $MagentoPDO = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);

        $PDO = $MagentoPDO->getConnection();
        //var_dump($PDO);
	    $connection = $db->connect()->getConnection();
        $connection->enableQueryLog();
        //Your block code 
        echo "<pre>";

        $selectAttributes = array_merge($attributes);
        $EAVRows = ['entity_id', 'attribute_id','value', 'store_id'];

        $attributesIdsCode = \Laragento\Models\EavAttribute::select('attribute_id','attribute_code')->whereIn('attribute_code', $selectAttributes)->whereIn('entity_type_id',[4])->get()->toArray();
       
        $attributesIds = [];
        $attrToCode = [];
        foreach($attributesIdsCode as $attr){
            $attributesIds[] = $attr['attribute_id'];
            $attrToCode[$attr['attribute_id']] = $attr['attribute_code'];
        }

        //var_dump($attributesIds); die();
        $tableAttributes = [
            'catalog_product_entity_varchars',
            'catalog_product_entity_texts',
            'catalog_product_entity_ints',
            'catalog_product_entity_decimals',
        ];

        $products = \Laragento\Models\CatalogProductEntity::query()->whereIn('sku', $skus)
                        ->with([
                            'catalog_product_entity_varchars' => function ($q) use ($attributesIds,$EAVRows){$q->select($EAVRows)->whereIn(
                                                            'attribute_id', $attributesIds
                                                            )->whereIn('store_id', [0, 1]);},
                            /*'catalog_product_entity_texts' => function ($q) use ($attributesIds,$EAVRows){$q->select($EAVRows)->whereIn(
                                                            'attribute_id', $attributesIds
                                                            )->whereIn('store_id', [0, 1]);}, */                               
                            'url_rewrites' => function ($q) {$q->select(['entity_id', 'request_path', 'store_id'])->whereIn(
                                'store_id', [0, 1]
                                )->whereIn('entity_type',['product'])->whereNull('metadata');}
                        ])->get()->toArray();

        //var_dump($products);

        foreach($products as $i => $product){
            foreach ($tableAttributes as $attr){
                if(isset($product[$attr])){
                    foreach($product[$attr] as $a){
                        $products[$i]['attributes'][$attrToCode[$a['attribute_id']]] = $a['value'];
                    }
                }
                unset($products[$i][$attr]);
            }

            if(isset($product['url_rewrites'])) {
                $products[$i]['url_rewrites'] = $product['url_rewrites'][0]['request_path'];
                //unset($products[$i]['url_rewrites']);
            }

        }


        //var_dump($products);
       

        $timeE = microtime(true);
        //var_dump($connection->getQueryLog());
        echo $timeE - $timeS;
        echo "</pre>"; 

        return $products;
    }
}
