<?php

namespace DirectoWooConnector;

use Directo\DirectoXMLParser;

class WooCategory {

    const PARENT_CAT = 'P_HI_KATEGOORIA';
    const SUB_CAT = 'ALAM_KATEGOORIA';

    /**
     * Returns WooCommerce Category IDs.
     *
     * @param $item
     * @return array
     */
    public function getCategoryIds($item)
    {
        $category_titles = $this->getCategoryTitles($item);

        return $this->getCategoryIdsByName($category_titles);
    }

    /**
     * Returns category titles.
     *
     * @param $item
     * @return array
     */
    public function getCategoryTitles($item)
    {
        $parser = new DirectoXMLParser();
        $datafields = $parser->getDataFields($item);
        $categories = [];

        foreach ($datafields as $key => $datafield) {
            if ($key == self::PARENT_CAT || $key == self::SUB_CAT) {
                $categories[$key] = $datafield;
            }
        }

        return $categories;
    }

    /**
     * Returns WooCommerce Category IDs
     *
     * If there is no category with that title new
     * category is created. Sub category is created
     * after parent category is created.
     *
     * @param $category_titles
     * @return array
     */
    public function getCategoryIdsByName($category_titles)
    {
        $category_ids = [];
        foreach ($category_titles as $key => $category_title) {
            $category_id = $this->getCategoryIdByName($category_title);
            $parent_id = null;
            if (isset($category_id) && $category_id) {
                $category_ids[] = $category_id;
            } else {
                if ($key == self::SUB_CAT) {
                    $parent_id = $this->getCategoryIdByName($category_titles[self::PARENT_CAT]);
                    if (!$parent_id) {
                        $parent_id = $this->createCategory($category_titles[self::PARENT_CAT]);
                    }
                    $category_ids[] = $this->createCategory($category_title, $parent_id);
                } else {
                    $category_ids[] = $this->createCategory($category_title, $parent_id);
                }
            }
        }

        return $category_ids;
    }

    /**
     * Returns WooCommerce category ID by category name
     *
     * @param $category_title
     * @return bool|int
     */
    public function getCategoryIdByName($category_title)
    {
        global $wpdb;
        $result = $wpdb->get_results(
            "SELECT term_id FROM $wpdb->terms WHERE name = '$category_title'"
        );
        if (isset($result[0]->term_id)) {
            return (int)$result[0]->term_id;
        }

        return false;
    }

    /**
     * @param $category_title
     * @return int|\WP_Error
     */
    public function createCategory($category_title, $parent_id = null)
    {
        $category = wp_insert_term(
            $category_title,
            'product_cat',
            [
                'parent' => $parent_id
            ]
        );

        return (int)$category['term_id'];
    }
}