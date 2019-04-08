<?php

namespace DirectoWooConnector;

use Directo\Customer;
use Directo\DirectoClient;
use Directo\DirectoXMLParser;
use Directo\LoggerBuilder;
use Monolog\Logger;

class WooCustomer extends WooDirecto {

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

    public function syncCustomers()
    {
        $response = $this->directo
            ->get('customer');
        $customers = $this->parser->parseResponse($response);
        foreach ($customers->customers->customer as $customer) {
            $customer_id = $this->getCustomerByEmail($customer);
            if (!$customer_id) {
                $this->addCustomer($customer);
            } else {
                $this->updateCustomer($customer_id, $customer);
            }
        }
    }

    public function addCustomer($item)
    {
        $customer_email = $this->getCustomerEmail($item);
        $password = $this->parser->getDataFieldValueByCode($item, 'PAROOL');

        if (!empty($password)) {
            if (isset($customer_email) && $customer_email) {
                $customer_code = $this->parser->getAttributeValue($item, 'code');
                $user_id = wc_create_new_customer($customer_email, $customer_code, $password);
                $data = array(
                    'billing_address_1' => $this->parser->getAttributeValue($item, 'address1'),
                    'billing_address_2' => $this->parser->getAttributeValue($item, 'address2'),
                    'billing_postcode' => $this->parser->getAttributeValue($item, 'address3'),
                    'billing_country' => $this->parser->getAttributeValue($item, 'country'),
                    'billing_phone' => $this->parser->getAttributeValue($item, 'phone'),
                    'billing_company' => $this->parser->getAttributeValue($item, 'name'),
                    'directo_obejct' => $this->parser->getAttributeValue($item, 'object'),
                    'directo_regno' => $this->parser->getAttributeValue($item, 'regno'),
                    'directo_loyaltycard' => $this->parser->getAttributeValue($item, 'loyaltycard'),
                    'directo_contact' => $this->parser->getAttributeValue($item, 'contact'),
                    'directo_vatregno' => $this->parser->getAttributeValue($item, 'vatregno'),
                    'directo_notice' => $this->parser->getAttributeValue($item, 'notice'),
                    'directo_gender' => $this->parser->getAttributeValue($item, 'gender'),
                    'directo_class' => $this->parser->getAttributeValue($item, 'class'),
                    'directo_type' => $this->parser->getAttributeValue($item, 'type'),
                    'directo_salesman' => $this->parser->getAttributeValue($item, 'salesman'),
                    'directo_creditlimit' => $this->parser->getAttributeValue($item, 'creditlimit'),
                    'directo_payterm' => $this->parser->getAttributeValue($item, 'payterm'),
                    'directo_priceformula' => $this->parser->getAttributeValue($item, 'priceformula')
                );
                foreach ($data as $meta_key => $meta_value) {
                    update_user_meta($user_id, $meta_key, $meta_value);
                }
            }
        }
    }

    public function getCustomerEmail($item)
    {
        $emails = $this->parser->getAttributeValue($item, 'email');

        if (strpos($emails, ';') !== false) {
            $emails_array = explode(';', $emails);
            $customer_email = $emails_array[0];
        } elseif((strpos($emails, ',') !== false)) {
            $emails_array = explode(',', $emails);
            $customer_email = $emails_array[0];
        } elseif ((strpos($emails, ' ') !== false)) {
            $emails_array = explode(' ', $emails);
            $customer_email = $emails_array[0];
        } else {
            $customer_email = $emails;
        }

        return $customer_email;
    }

    public function updateCustomer($user_id, $item)
    {
        $data = array(
            'directo_object' => $this->parser->getAttributeValue($item, 'object'),
            'directo_regno' => $this->parser->getAttributeValue($item, 'regno'),
            'directo_loyaltycard' => $this->parser->getAttributeValue($item, 'loyaltycard'),
            'directo_contact' => $this->parser->getAttributeValue($item, 'contact'),
            'directo_vatregno' => $this->parser->getAttributeValue($item, 'vatregno'),
            'directo_notice' => $this->parser->getAttributeValue($item, 'notice'),
            'directo_gender' => $this->parser->getAttributeValue($item, 'gender'),
            'directo_class' => $this->parser->getAttributeValue($item, 'class'),
            'directo_type' => $this->parser->getAttributeValue($item, 'type'),
            'directo_salesman' => $this->parser->getAttributeValue($item, 'salesman'),
            'directo_creditlimit' => $this->parser->getAttributeValue($item, 'creditlimit'),
            'directo_payterm' => $this->parser->getAttributeValue($item, 'payterm'),
            'directo_priceformula' => $this->parser->getAttributeValue($item, 'priceformula')
        );

        foreach ($data as $meta_key => $meta_value) {
            update_user_meta($user_id, $meta_key, $meta_value);
        }
    }

    public function getCustomerByEmail($item)
    {
        $emails_array = explode(', ', $this->parser->getAttributeValue($item, 'email'));
        $customer = get_user_by('email', $emails_array[0]);
        if (isset($customer->ID) && $customer->ID) {
            return $customer->ID;
        } else {
            return false;
        }
    }

    /**
     * @param $directo DirectoClient
     * @param $user_id
     * @throws \Exception
     */
    public function post($directo, $user_id)
    {
        $user_data = $this->getUserData($user_id);
        $isCustomer = $this->isUserCustomer($user_data->roles);
        if ($isCustomer) {
            $directoCustomer = new Customer();
            $customerArray = $this->prepCustomerArray($user_data);
            $xml = $directoCustomer->generateXml($customerArray);
            $response = $directo->post('customer', $xml);
            if (DIRECTO_LOGGER) {
                $message = 'Post response: ' . $response->getBody()->getContents() . ' Posted XML: ' . (string)$xml;

                $logger = (new LoggerBuilder())
                    ->createLogger('log', DIRECTO_PLUGIN_DIR . 'log/customers.log', Logger::DEBUG)
                    ->getLogger();

                $logger->debug($message);
            }
        }
    }

    public function getUserData($user_id)
    {
        return get_userdata($user_id);
    }

    public function isUserCustomer($roles)
    {
        foreach ($roles as $role) {
            if ($role === 'customer') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $customer_data \WP_User
     * @return array
     */
    public function prepCustomerArray($customer_data)
    {
        return [
            'appkey' => $this->getAppKey(),
            'code' => $customer_data->user_login,
            'name' => get_user_meta($customer_data->ID, 'billing_company', true),
            'address1' => get_user_meta($customer_data->ID, 'billing_address_1', true),
            'address2' => get_user_meta($customer_data->ID, 'billing_address_2', true).' '.get_user_meta($customer_data->ID, 'billing_postcode', true),
            'address3' => get_user_meta($customer_data->ID, 'billing_country', true),
            'country' => get_user_meta($customer_data->ID, 'billing_country', true),
            'phone' => get_user_meta($customer_data->ID, 'billing_phone', true),
            'email' => $customer_data->user_email,
        ];
    }
}