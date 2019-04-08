<?php

namespace DirectoWooConnector;

use Directo\ClientFactory;
use Directo\DirectoClient;
use Directo\DirectoXMLParser;
use Directo\Order as DirectoOrder;

class WooDirecto {

    /**
     * @var array options
     */
    public $fields = [
        'woo_directo_account',
        'woo_directo_key'
    ];

    /**
     * @var DirectoClient
     */
    private $directo;

    /**
     * @var DirectoXMLParser
     */
    private $parser;

    /**
     * @var WooProduct
     */
    private $wooProduct;

    /**
     * @var WooCustomer
     */
    private $wooCustomer;

    /**
     * @var WooSpecificPrice
     */
    private $wooSpecificPrice;

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
            DIRECTO_PLUGIN_DIR . '/index.php',
            ''
        );
    }

    /**
     * Creates Direcot API instance.
     */
    public function createClient()
    {
        $account = $this->getAccount();
        $key = $this->getAppKey();
        $clientFactory = new ClientFactory();
        $this->directo = $clientFactory->create($account, $key);
    }

    public function getAccount()
    {
        return get_option('woo_directo_account');
    }

    public function getAppKey()
    {
        return get_option('woo_directo_key');
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
     * @param $type
     */
    public function syncData($type)
    {
        $this->createClient();
        $this->parser = new DirectoXMLParser();
        switch ($type) {
            case 'items':
                $this->processItems();
                break;
            case 'customers':
                $this->processCustomers();
                break;
            case 'priceformulas':
                $this->processPriceFormulas();
                break;
            case 'all':
                $this->processItems();
                $this->processCustomers();
                $this->processPriceFormulas();
                break;
            default:
                break;
        }
    }

    /**
     * Processes items XML.
     */
    public function processItems()
    {
        $this->wooProduct = new WooProduct($this->directo, $this->parser);
        $this->wooProduct->syncProducts();
    }

    /**
     * Processes customer XML.
     */
    public function processCustomers()
    {
        $this->wooCustomer = new WooCustomer($this->directo, $this->parser);
        $this->wooCustomer->syncCustomers();
    }

    /**
     * Processes Price Formula XML.
     */
    public function processPriceFormulas()
    {
        $this->wooSpecificPrice = new WooSpecificPrice($this->directo, $this->parser);
        $this->wooSpecificPrice->syncSpecificPrices();
    }

    public function postOrder($orderId)
    {
        $this->createClient();
        $directoOrder = new DirectoOrder();
        $wooOrder = new WooOrder();
        $wooOrder->post($this->directo, $directoOrder, $orderId);
    }

    public function postCustomer($user_id)
    {
        $this->createClient();
        $this->parser = new DirectoXMLParser();
        $this->wooCustomer = new WooCustomer($this->directo, $this->parser);
        $this->wooCustomer->post($this->directo, $user_id);
    }
}