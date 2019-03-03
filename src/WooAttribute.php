<?php

namespace DirectoWooConnector;


use Directo\DirectoXMLParser;

class WooAttribute
{
    public function getAttributeMapping()
    {
        return woo_directo_attribute_mapping();
    }

    public function addProductAttributes($product_id, $item)
    {
        $attribute_values = $this->addAttributes($item);
        $this->addAttributeValues($attribute_values);
        $this->addProductAttributeValue($product_id, $attribute_values);
    }

    public function addAttributes($item)
    {
        $attribute_values = [];
        foreach ($this->getAttributeMapping() as $directo_attribute_name => $woo_attribute) {
            $parser = new DirectoXMLParser();
            $datafields = $parser->getDataFields($item);
            if (isset($datafields[$directo_attribute_name])) {
                $attribute = [
                    'attribute_name' => $woo_attribute['name'],
                    'attribute_label' => $woo_attribute['label'],
                    'attribute_type' => 'text',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 0,

                ];
                $this->addAttribute($attribute);
                $attribute_values[$woo_attribute['name']] = $datafields[$directo_attribute_name];
            }
        }

        return $attribute_values;
    }

    public function addAttribute($attribute)
    {
        global $wpdb;

        $attribute_id = $wpdb->get_row('
            SELECT attribute_id FROM '.$wpdb->prefix.'woocommerce_attribute_taxonomies 
            WHERE attribute_name = \''.$attribute['attribute_name'].'\''
        );

        if (is_null($attribute_id)) {
            $wpdb->insert($wpdb->prefix . 'woocommerce_attribute_taxonomies', $attribute);
            do_action('woocommerce_attribute_added', $wpdb->insert_id, $attribute);
        }
    }

    public function addAttributeValues($attribute_values)
    {
        global $wpdb;
        foreach ($attribute_values as $woo_attribute_name => $attribute_value) {
            $data = [
                'name' => $attribute_value
            ];
            $term_id = $this->getTermID($attribute_value);
            if (!$term_id) {
                $wpdb->insert($wpdb->prefix . 'terms', $data);
                $term_id = $this->getTermID($attribute_value);
                $taxonomy_data = [
                    'term_id' => $term_id,
                    'taxonomy' => 'pa_'.$woo_attribute_name,
                    'parent' => 0,
                    'count' => 0
                ];
                $wpdb->insert($wpdb->prefix . 'term_taxonomy', $taxonomy_data);
            }
        }
    }

    public function getTermID($name)
    {
        global $wpdb;
        $result = $wpdb->get_row('
            SELECT term_id FROM '.$wpdb->prefix.'terms 
            WHERE name = \''.$name.'\''
        );

        if (isset($result->term_id)) {
            return $result->term_id;
        }

        return false;
    }

    public function addProductAttributeValue($product_id, $attribute_values)
    {
        $attributes = [];
        foreach ($attribute_values as $woo_attribute_name => $attribute_value) {
            $attributes['pa_'.$woo_attribute_name] = [
                'name' => 'pa_'.$woo_attribute_name,
                'value' => $attribute_value,
                'position' => 0,
                'is_visible' => 1,
                'is_variatsion' => 0,
                'is_taxonomy' => 1,
            ];
        }
        delete_transient('wc_attribute_taxonomies');
        update_post_meta($product_id, '_product_attributes', $attributes);
        flush_rewrite_rules();
        foreach ($attribute_values as $woo_attribute_name => $attribute_value) {
            wp_set_object_terms($product_id, $attribute_value, 'pa_'.$woo_attribute_name);
        }
    }
}