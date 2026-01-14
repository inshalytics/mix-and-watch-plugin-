<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WC_MNM
{

    /**
     * Boot plugin hooks.
     */
    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->includes();

        // Register our product type early enough for WC product screens.
        add_filter('product_type_selector', [$this, 'register_product_type_in_selector']);

        // Map product type -> product class.
        add_filter('woocommerce_product_class', [$this, 'map_product_class'], 10, 2);
    }

    /**
     * Load required class files.
     */
    private function includes(): void
    {
        require_once WC_MNM_PLUGIN_DIR . 'includes/admin/class-wc-mnm-product-type.php';
        require_once WC_MNM_PLUGIN_DIR . 'includes/woo/class-wc-product-mnm.php';
        require_once WC_MNM_PLUGIN_DIR . 'includes/admin/class-wc-mnm-product-data.php';

    }

    /**
     * Add Mix and Match to the Product Type dropdown.
     *
     * @param array $types
     * @return array
     */
    public function register_product_type_in_selector(array $types): array
    {
        $types['mnm'] = __('Mix and Match', 'wc-mix-and-match');
        return $types;
    }

    /**
     * Tell WooCommerce which PHP class should represent our product type.
     *
     * @param string $classname
     * @param string $product_type
     * @return string
     */
    public function map_product_class(string $classname, string $product_type): string
    {
        if ('mnm' === $product_type) {
            return 'WC_Product_MNM';
        }
        return $classname;
    }
}
