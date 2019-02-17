<?php

namespace DirectoWooConnector;

class WooProduct {

    public static function createProduct($params)
    {
        if (isset($params['name']) && $params['name']) {
            try {
                $objProduct = new WC_Product();
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
                $objProduct->set_category_ids(array(1, 2, 3));
                $objProduct->save();
            } catch (Exception $exception) {
                //TODO implement monolog
            }
        }
    }

    public static function updateProduct($product_id, $params)
    {
        $product = new WC_Product($product_id);
        $product->set_price($params['price']);
        $product->set_regular_price($params['price']);
        $product->set_stock_quantity($params['stock_level']);
        $product->save();
    }
}