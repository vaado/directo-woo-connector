<?php

namespace DirectoWooConnector;

use Directo\DirectoClient;
use Directo\DirectoXMLParser;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class WooProduct {

    /**
     * @var DirectoClient
     */
    private $directo;

    /**
     * @var DirectoXMLParser;
     */
    private $parser;

    public function __construct($directo, $parser)
    {
        $this->directo = $directo;
        $this->parser = $parser;
    }

    public function syncProducts()
    {
        $response = $this->directo
            ->get('item');
        $items = $this->parser->parseResponse($response);
        foreach ($items->items->item as $item) {
            $is_web_product = $this->isWebProduct($this->parser, $item);
            if ($is_web_product) {
                $code = (string)$item->attributes()->code;
                $product_id = wc_get_product_id_by_sku($code);
                if (isset($product_id) && $product_id && $product_id != 0) {
                    $this->updateProduct($product_id, $item);
                } else {
                    $this->addProduct($item);
                }
            }
        }
    }

    /**
     * @param $parser DirectoXMLParser
     * @param $item
     * @return bool
     */
    public function isWebProduct($parser, $item)
    {
        $web_product_attribute = woo_directo_web_product_attribute();
        $web_product_value = $parser->getDataFieldValueByCode($item, $web_product_attribute['code']);

        return $web_product_value === $web_product_attribute['value'];
    }

    /**
     * @param $item
     */
    public function addProduct($item)
    {
        $params = $this->extractParams($item);
        $wooCategoty = new WooCategory();
        $category_ids = $wooCategoty->getCategoryIds($item);
        if (!empty($category_ids)) {
            if (isset($params['name']) && $params['name']) {
                try {
                    $objProduct = new \WC_Product();
                    $objProduct->set_name($params['name']);
                    $objProduct->set_status('publish');
                    $objProduct->set_catalog_visibility('visible');
                    $objProduct->set_description('');
                    $objProduct->set_sku($params['code']);
                    $objProduct->set_price($params['price']);
                    $objProduct->set_regular_price($params['price']);
                    $objProduct->set_manage_stock(true);
                    $objProduct->set_stock_quantity($params['stock_level']);
                    $objProduct->set_stock_status('instock');
                    $objProduct->set_backorders('no');
                    $objProduct->set_reviews_allowed(true);
                    $objProduct->set_sold_individually(false);
                    $objProduct->set_category_ids($category_ids);
                    $product_id = $objProduct->save();
                    /** Adds attributes */
                    $this->addProductAttributes($product_id, $item);
                    /** Creates dublicates for translations */
                    do_action('wpml_make_post_duplicates', $product_id);
                } catch (\WC_Data_Exception $exception) {
                    $log = new Logger('Directo');
                    try {
                        $message = 'Exeption: '.$exception->getMessage().'. SKU: '.$params['code'];
                        $log->pushHandler(new StreamHandler(DIRECTO_PLUGIN_DIR.'log/product_import.log', Logger::ERROR));
                        $log->info($message);
                    } catch (\Exception $e) {
                        var_dump($e->getMessage());
                    }
                }
            }
            wp_reset_query();
        }
    }

    /**
     * @param $product_id
     * @param $item
     */
    public function updateProduct($product_id, $item)
    {
        $params = $this->extractParams($item);
        $product = new \WC_Product($product_id);
        $product->set_price($params['price']);
        $product->set_regular_price($params['price']);
        $product->set_stock_quantity($params['stock_level']);
        update_post_meta($product_id, '_wpm_gtin_code', $params['gtin']);
        update_post_meta($product_id, '_backorders', 'notify');
        $product->save();
        $this->addProductAttributes($product_id, $item);
        $this->updateTranslatedProduct($product_id, $item, $params);
    }

    /**
     * Extracts product params from XML objc.
     *
     * @param $item
     * @return mixed
     */
    public function extractParams($item)
    {
        $params['name'] = $this->parser->getAttributeValue($item, 'name');
        $params['code'] = $this->parser->getAttributeValue($item, 'code');
        $params['price'] = $this->parser->getAttributeValue($item, 'price');
        $params['gtin'] = $this->parser->getAttributeValue($item, 'barcode');
        $params['stock_level'] = (int)$item->stocklevels->stocklevel->attributes()->level;

        return $params;
    }

    public function addProductAttributes($product_id, $item)
    {
        $wooAttribute = new WooAttribute();
        $wooAttribute->addProductAttributes($product_id, $item);
    }

    public function updateTranslatedProduct($product_id, $item, $params)
    {
        $translated_id = wpml_object_id_filter($product_id, 'product', false, 'en');
        if ($translated_id) {
            $eng_name = $this->parser->getDataFieldValueByCode($item, 'ART_LANG');
            $translated_product = new \WC_Product($translated_id);
            $translated_product->set_name($eng_name);
            $translated_product->set_price($params['price']);
            $translated_product->set_regular_price($params['price']);
            $translated_product->set_stock_quantity($params['stock_level']);
            $translated_product->save();
        } else {
            /** Creates dublicates for translations */
            do_action('wpml_make_post_duplicates', $product_id);
        }
    }
}