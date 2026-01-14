<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WC_MNM_Product_Data
{
    public function __construct()
    {
        // Add custom fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_fields']);

        // Save custom fields
        add_action('woocommerce_admin_process_product_object', [$this, 'save_fields']);
    }

    /**
     * Add Mix & Match fields in General tab
     */
    public function add_fields(): void
    {
        global $product_object;


        echo '<div class="options_group show_if_mnm">';

        // Pricing mode
        woocommerce_wp_select([
            'id' => '_mnm_pricing_mode',
            'label' => __('Pricing Mode', 'wc-mix-and-match'),
            'options' => [
                'fixed' => __('Fixed Price', 'wc-mix-and-match'),
                'per_item' => __('Per Item Price', 'wc-mix-and-match'),
            ],
            'desc_tip' => true,
            'description' => __('Choose how this container is priced.', 'wc-mix-and-match'),
        ]);

        // Fixed price
        woocommerce_wp_text_input([
            'id' => '_mnm_fixed_price',
            'label' => __('Fixed Container Price', 'wc-mix-and-match'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min' => '0',
            ],
        ]);

        // Min quantity
        woocommerce_wp_text_input([
            'id' => '_mnm_min_qty',
            'label' => __('Minimum Quantity', 'wc-mix-and-match'),
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
            ],
        ]);

        // Max quantity
        woocommerce_wp_text_input([
            'id' => '_mnm_max_qty',
            'label' => __('Maximum Quantity', 'wc-mix-and-match'),
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
            ],
        ]);

        // Child products
        woocommerce_wp_select([
            'id' => '_mnm_child_products',
            'label' => __('Child Products', 'wc-mix-and-match'),
            'options' => $this->get_simple_products(),
            'class' => 'wc-enhanced-select',
            'custom_attributes' => [
                'multiple' => 'multiple',
            ],
            'desc_tip' => true,
            'description' => __('Products customers can choose from.', 'wc-mix-and-match'),
        ]);

        echo '</div>';
    }

    /**
     * Save product meta
     */
    public function save_fields(WC_Product $product): void
    {
        if ($product->get_type() !== 'mnm') {
            return;
        }

        // Pricing mode
        $pricing_mode = isset($_POST['_mnm_pricing_mode'])
            ? wc_clean($_POST['_mnm_pricing_mode'])
            : 'per_item';

        // Fixed price
        $fixed_price = isset($_POST['_mnm_fixed_price'])
            ? floatval($_POST['_mnm_fixed_price'])
            : 0;

        // Min / Max
        $min_qty = isset($_POST['_mnm_min_qty'])
            ? absint($_POST['_mnm_min_qty'])
            : 0;

        $max_qty = isset($_POST['_mnm_max_qty'])
            ? absint($_POST['_mnm_max_qty'])
            : 0;

        // Child products
        $children = isset($_POST['_mnm_child_products'])
            ? array_map('absint', (array) $_POST['_mnm_child_products'])
            : [];

        // Basic validation
        if ($max_qty > 0 && $min_qty > $max_qty) {
            $min_qty = $max_qty;
        }

        if ($pricing_mode === 'fixed' && $fixed_price <= 0) {
            $pricing_mode = 'per_item';
        }

        // Save meta
        $product->update_meta_data('_mnm_pricing_mode', $pricing_mode);
        $product->update_meta_data('_mnm_fixed_price', $fixed_price);
        $product->update_meta_data('_mnm_min_qty', $min_qty);
        $product->update_meta_data('_mnm_max_qty', $max_qty);
        $product->update_meta_data('_mnm_child_products', $children);
    }

    /**
     * Get simple products for selector
     */
    private function get_simple_products(): array
    {
        $products = wc_get_products([
            'limit' => -1,
            'type' => 'simple',
            'status' => 'publish',
        ]);

        $options = [];

        foreach ($products as $product) {
            $options[$product->get_id()] = $product->get_name();
        }

        return $options;
    }
}

// Admin only
if (is_admin()) {
    new WC_MNM_Product_Data();
}
