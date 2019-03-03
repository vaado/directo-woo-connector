<?php

namespace DirectoWooConnector;

use Directo\ClientFactory;
use Directo\DirectoClient;
use Directo\DirectoXMLParser;

class WooDirecto {

    /**
     * @var array options
     */
    public $fields = [
        'woo_directo_account',
        'woo_directo_key'
    ];

    private $wooProduct;

    /**
     * @var DirectoClient
     */
    private $client;

    public static function init()
    {
        add_action('admin_menu', array('DirectoConnector', 'registerDirectoMenu'));
    }

    /**
     * Creates Directo menu link under WooCommerce menu.
     */
    public static function registerDirectoMenu()
    {
        add_submenu_page(
            'woocommerce',
            'Directo',
            'Directo',
            'add_users',
            DIRECO_PLUGIN_DIR . '/index.php',
            ''
        );
    }

    /**
     * Creates Direcot API instance.
     */
    public function createClient()
    {
        $account = get_option('woo_directo_account');
        $key = get_option('woo_directo_key');
        $clientFactory = new ClientFactory();
        $this->client = $clientFactory->create($account, $key);
    }

    /**
     * Updates Directo settings accountname, key ect.
     * @param $post
     */
    public function updateDirectoSettings($post)
    {
        foreach ($this->fields as $field) {
            update_option($field, $post[$field]);
        }
    }

    /**
     * Install Directo.
     */
    public function installWooDirecto()
    {
        foreach ($this->fields as $field) {
            add_option($field, '', null, 'no');
        }
    }

    /**
     * Synchronize Directo data with Woo.
     */
    public function syncData()
    {
        $this->createClient();
        $this->processItems();
        $this->processCustomers();
        $this->processPriceFormulas();
    }

    /**
     * Processes items XML.
     */
    public function processItems()
    {
        $this->wooProduct = new WooProduct();
        $response = $this->client
            ->get('item');
        $parser = new DirectoXMLParser();
        $items = $parser->parseResponse($response);
        foreach ($items->items->item as $item) {
            $is_web_product = $this->isWebProduct($parser, $item);
            if ($is_web_product) {
                $code = (string)$item->attributes()->code;
                $product_id = wc_get_product_id_by_sku($code);
                if (isset($product_id) && $product_id && $product_id != 0) {
                    $this->wooProduct->updateProduct($product_id, $item);
                } else {
                    $this->wooProduct->addProduct($item);
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


    public function processCustomers()
    {
        $response = $this->client
            ->Exceptionget('customer');
        $parser = new DirectoXMLParser();
        $customers = $parser->parseResponse($response);

        foreach ($customers->customers->customer as $customer) {

        }
    }

    public function processPriceFormulas()
    {
        $response = $this->client
            ->get('priceformularow');
        $parser = new DirectoXMLParser();
        $priceFormulas = $parser->parseResponse($response);

        foreach ($priceFormulas->priceformularows->priceformularow as $priceformularow) {

        }
    }

}