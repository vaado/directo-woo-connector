<?php

namespace DirectoWooConnector;

use Directo\Directo;

class WooDirecto {

    /**
     * @var array options
     */
    public $fields = [
        'woo_directo_account',
        'woo_directo_key'
    ];

    /**
     * @var Directo
     */
    private $directo;

    public static function init()
    {
        add_action('admin_menu', array('WooDirecto', 'registerDirectoMenu'));
    }

    /**
     * Creates Directo menu link under WooCommerce menu
     */
    public static function registerDirectoMenu()
    {
        add_submenu_page(
            'woocommerce',
            'Directo Settings',
            'Directo Settings',
            'add_users',
            DIRECO_PLUGIN_DIR . '/index.php',
            ''
        );
    }

    /**
     * Creates Direcot API instance
     */
    public function createDirecto()
    {
        $account = get_option('woo_directo_account');
        $key = get_option('woo_directo_key');
        $this->directo = new Directo($account, $key);
    }

    /**
     * Updates directo settings accountname, key ect.
     *
     * @param $post
     */
    public function updateDirectoSettings($post)
    {
        foreach ($this->fields as $field) {
            update_option($field, $post[$field]);
        }
    }

    public function installWooDirecto()
    {
        foreach ($this->fields as $field) {
            add_option($field, '', null, 'no');
        }
    }

    /**
     * Synchronize Dircto data with Woo
     */
    public function syncData()
    {
        $this->createDirecto();
        $this->processItems();
    }

    /**
     * Processes itmes XML
     */
    public function processItems()
    {
        $items = $this->directo->getItemXML();
        $i = 0;
        foreach ($items->items->item as $item) {
            $code = (string)$item->attributes()->code;
            $product_id = wc_get_product_id_by_sku($code);
            if ($i == 100) {
                die;
            }
            if (isset($product_id) && $product_id) {
                $this->updateProduct($product_id, $item);
            } else {
                $this->createProduct($item);
            }
            $i++;
        }
    }

    /**
     * Creates WooCommerce Product
     *
     * @param $item
     */
    public function createProduct($item)
    {
        $params = $this->extractParams($item);
        WooProduct::createProduct($params);
    }

    /**
     * Updates WooCommerce Product
     *
     * @param $product_id
     * @param $item
     */
    public function updateProduct($product_id, $item)
    {
        $params = $this->extractParams($item);
        WooProduct::updateProduct($product_id, $params);
    }

    /**
     * Extracts product params from XML objc
     *
     * @param $item
     * @return mixed
     */
    public function extractParams($item)
    {
        $params['name'] = (string)$item->attributes()->name;
        $params['code'] = (string)$item->attributes()->code;
        $params['price'] = (string)$item->attributes()->price;
        $params['stock_level'] = (int)$item->stocklevels->stocklevel->attributes()->level;

        return $params;
    }

}