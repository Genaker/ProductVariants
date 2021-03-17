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

    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Framework\Stdlib\ArrayUtils $arrayUtils,
        \Magento\Catalog\Model\ProductRepository $productInst,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Swatches\Model\ResourceModel\Swatch\Collection $swatchCollection,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        $this->productInst = $productInst;
        $this->storeManager = $context->getStoreManager();
        $this->swatchCollection = $swatchCollection;
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

        // Warm up the product Reposetorycache
        $start1 = microtime(true);
        $productsObj = $this->productInst->getList($searchCriteria)->getItems();
        $end1 = microtime(true);

        //Check missung SKUS
        $foundSKUs = [];
        foreach ($productsObj as $pr) {
            $foundSKUs[$pr->getsku()] = 1;
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
            $sku = $obj->getData('sku');
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

            //basically any attribut not just collor it is option value
            $optionValue = $variationProduct->getData($productVariantAttribute);

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

            $swatch['data'] = $variationProduct->getResource()
                ->getAttribute($attribute[key($attribute)])->getData();

            $attributeId = $swatch['data']['attribute_id'];

            $label = $this->getProduct()->getResource()->getAttribute(key($attribute))->getStoreLabel();
            $swatch['data']['label'] = $label;

            if (isset($swatch[$sku]["option_id"]) || !@$swatch[$sku]["option_id"]) {
                $swatch[$sku]["option_id"] = $optionValue;
            }

            $swatch[$sku]['product_title'] = $variationProduct->getName();

            $attr = $variationProduct->getResource()->getAttribute($swatch['data']['attribute_code']);
            $swatch[$sku]['attribute_value'] = $variationProduct->getAttributeText($productVariantAttribute);

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
            // $prodvariationProductuct->getData('swatch_image'));

            $swatch[$sku]['image'] = $variationProduct->getData('swatch_image');
            if (!$variationProduct->getData('swatch_image')) {
                $swatch[$sku]['image'] = $variationProduct->getData('small_image');
            }

            $imageHelper = $this->objectManager->get('\Magento\Catalog\Helper\Image');
            $imageURL = $imageHelper->init($obj, 'small_image')->getUrl();

            if (!strpos($imageURL, 'placeholder')) {
                $swatch[$sku]['image'] = $imageURL;
            }

            $swatch[$sku]['option_text'] = $optionText;
            $swatch[$sku]["product_url_key"] = $variationProduct->getProductUrl();

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

        if (count($swatchsorted) > 2) {
            // ToDo: improve and fix;
            // array_multisort(array_column($swatchsorted, 'sort_order'), SORT_ASC, $swatchsorted);
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
}
