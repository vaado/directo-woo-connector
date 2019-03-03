<?php

namespace DirectoWooConnector;

class WooProduct {

    /**
     * @param $item
     */
    public function addProduct($item)
    {
        $params = $this->extractParams($item);
        $wooCategoty = new WooCategory();
        $category_ids = $wooCategoty->getCategoryIds($item);
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
                $this->addProductAttributes($product_id, $item);
            } catch (\WC_Data_Exception $exception) {
                //TODO implement monolog
            }
        }
        wp_reset_query();
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
        $product->save();
        $this->addProductAttributes($product_id, $item);
    }

    /**
     * Extracts product params from XML objc.
     *
     * @param $item
     * @return mixed
     */
    public function extractParams($item)
    {
        $params['name'] = $this->getXMLAttributeValue($item, 'name');
        $params['code'] = $this->getXMLAttributeValue($item, 'code');
        $params['price'] = $this->getXMLAttributeValue($item, 'price');
        $params['stock_level'] = (int)$item->stocklevels->stocklevel->attributes()->level;

        return $params;
    }

    public function getXMLAttributeValue($item, $attribute)
    {
        return (string)$item->attributes()->$attribute;
    }

    public function addProductAttributes($product_id, $item)
    {
        $wooAttribute = new WooAttribute();
        $wooAttribute->addProductAttributes($product_id, $item);
    }
}