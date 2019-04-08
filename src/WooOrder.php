<?php

namespace DirectoWooConnector;

use Directo\DirectoClient;
use Directo\LoggerBuilder;
use Directo\Order;
use Monolog\Logger;

class WooOrder extends WooDirecto {

    public function getOrderById($orderId)
    {
        return new \WC_Order($orderId);
    }

    public function prepOrderArray($orderId)
    {
        $order = $this->getOrderById($orderId);

        return [
            'info' => $this->getOrderInfo($order),
            'products' => $this->getProducts($order),
        ];
    }

    /**
     * @param $order \WC_Order
     * @return array
     */
    public function getOrderInfo($order)
    {
        $customer_info = get_userdata($order->get_user_id());
        
        return [
            'appkey' => $this->getAppKey(),
            'number' => $order->get_id(),
            'date' => $order->get_date_completed()->date_i18n('Y-m-d'),
            'customer_code' => $customer_info->user_login,
            'customer_name' => $order->get_shipping_first_name(). ' '.$order->get_billing_last_name(),
            'transportterm' => 'transportterm',
            'comment' => $order->get_customer_note(),
            'contact' => $order->get_billing_first_name(). ' ' .$order->get_billing_last_name(),
            'address1' => $order->get_billing_address_1(). ' ' .$order->get_billing_address_2(),
            'address2' => $order->get_billing_city(). ' ' .$order->get_billing_postcode(),
            'address3' => $order->get_billing_country(),
            'deliveryaddress1' => $order->get_shipping_address_1(). ' ' .$order->get_shipping_address_2(),
            'deliveryaddress2' => $order->get_shipping_city(). ' ' .$order->get_shipping_postcode(),
            'deliveryaddress3' => $order->get_shipping_country(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'paymentterm' => $order->get_payment_method(),
            'object' => 'E-POOD',
            'warehouse' => '',
            'deliveryname' => 'deliveryname',
            'paymentamount' => '',
        ];
    }

    /**
     * @param $order \WC_Order
     * @return array
     */
    public function getProducts($order)
    {
        $order_products = [];
        $order_items = $order->get_items();
        $index = 0;
        foreach ($order_items as $order_item) {
            $index += 1;
            $product_id = $order_item->get_data()['product_id'];
            $product =  $product = new \WC_Product($product_id);
            $order_products[] = [
                'item' => $product->get_sku(),
                'description' => $product->get_title(),
                'price' => $order_item->get_data()['total'],
                'quantity' => $order_item->get_quantity(),
                'total' => $order_item->get_data()['total'],
                'discount' => '',
                'rn' => $index,
                'rr' => $index,
            ];
        }

        return $order_products;
    }

    /**
     * @param $directo DirectoClient
     * @param $directoOrder Order
     * @param $orderId
     * @throws \Exception
     */
    public function post($directo, $directoOrder, $orderId)
    {
        $this->createClient();
        $orderArray = $this->prepOrderArray($orderId);
        $xml = $directoOrder->generateOrderXml($orderArray);
        $response = $directo->post('order', $xml);

        if (DIRECTO_LOGGER) {
            $message = 'Post response: ' . $response->getBody()->getContents() . ' Posted XML: ' . (string)$xml;

            $logger = (new LoggerBuilder())
                ->createLogger('log', DIRECTO_PLUGIN_DIR . 'log/orders.log', Logger::DEBUG)
                ->getLogger();

            $logger->debug($message);
        }
    }
}