<?php

namespace DirectoWooConnector;

use Directo\DirectoClient;
use Directo\DirectoXMLParser;

class WooSpecificPrice {


    private $discount_type = [
      '8' => 1, //flat dicount type
      '0' => 2  //percentage dicount type
    ];

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

    public function syncSpecificPrices()
    {
        $productPrices = [];
        $priceformulaCustomers = [];
        $priceformulaProducts = [];
        $response = $this->directo
            ->get('priceformularow');
        $priceformularows = $this->parser->parseResponse($response);

        foreach ($priceformularows->priceformularows->priceformularow as $priceformularow) {
            $code = $this->parser->getAttributeValue($priceformularow, 'code');
            $item = $this->parser->getAttributeValue($priceformularow, 'item');

            if (!isset($priceformulaCustomers[$code])) {
                $customer_id = $this->getCustomerIDByCode($code);
                $priceformulaCustomers[$code] = $customer_id;
            }

            if (!isset($priceformulaProducts[$item])) {
                $product_ids = $this->getProductIDBySKU($item);
                if (isset($product_ids) && $product_ids) {
                    foreach ($product_ids as $product_id) {
                        $priceformulaProducts[$item][] = $product_id;
                    }
                } else {
                    $priceformulaProducts[$item] = false;
                }
            }

            if ((isset($priceformulaCustomers[$code]) && $priceformulaCustomers[$code]) &&
                (isset($priceformulaProducts[$item]) && $priceformulaProducts[$item])
            ) {
                foreach ($priceformulaProducts[$item] as $product_id) {
                    $customer_id = $priceformulaCustomers[$code];
                    $specific_value = $this->parser->getAttributeValue($priceformularow, 'discount');
                    $type = $this->parser->getAttributeValue($priceformularow, 'type');
                    $productPrices[$product_id][$customer_id] = [
                        'value' => $specific_value,
                        'type' => $this->discount_type[$type]
                    ];
                }
            }
        }
        $this->updateSpecificPrices($productPrices);
    }

    public function getProductIDBySKU($code)
    {
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sku' AND meta_value = '$code'"
        );
        $product_ids = [];
        if (isset($result[0]->post_id)) {
            foreach ($result as $row) {
                $product_ids[] = $row->post_id;
            }
            return $product_ids;
        }

        return false;
    }

    public function getCustomerIDByCode($code)
    {
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT user_id FROM $wpdb->usermeta WHERE meta_value = '$code'"
        );

        if (isset($result[0]->user_id)) {
            return (int)$result[0]->user_id;
        }

        return false;
    }

    public function updateSpecificPrices($productPrices)
    {
        foreach ($productPrices as $product_id => $productPrice) {
            $customer_id = key($productPrice);
            $specific_price_id = $this->getCustomerSpesificPricesId($customer_id, $product_id);
            if ($specific_price_id) {
                $this->updateSpecificPrice($specific_price_id, $productPrice);
            } else {
                $this->addSpecificPrice($productPrice, $customer_id, $product_id);
            }
        }
    }

    public function addSpecificPrice($productPrice, $customer_id, $product_id)
    {
        global $wpdb;
        $productPrice = reset($productPrice);
        $data = [
            'product_id' => $product_id,
            'user_id' => $customer_id,
            'price' => $productPrice['value'],
            'flat_or_discount_price' => $productPrice['type']
        ];

        $wpdb->insert($wpdb->prefix.'wusp_user_pricing_mapping', $data);
    }
    
    public function updateSpecificPrice($specific_price_id, $productPrice)
    {
        global $wpdb;
        $productPrice = reset($productPrice);
        $query = 'UPDATE '.$wpdb->prefix.'wusp_user_pricing_mapping SET price = 
        \''.$productPrice['value'].'\', flat_or_discount_price = '.$productPrice['type'].' WHERE id = '.$specific_price_id;

        $wpdb->query($query);
    }


    public function getCustomerSpesificPricesId($customer_id, $product_id)
    {
        global $wpdb;
        $result = $wpdb->get_results(
            'SELECT id FROM '.$wpdb->prefix.'wusp_user_pricing_mapping WHERE 
            user_id = '.$customer_id.' AND product_id = '.$product_id
        );

        if (isset($result[0]->id)) {
            return (int)$result[0]->id;
        }

        return false;
    }
}