<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WC_MNM_Product_Data
{
    /**
     * @var WC_Product|null
     */
    private $product = null;

    public function __construct()
    {
        // Add custom fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_fields']);

        // Save custom fields
        add_action('woocommerce_admin_process_product_object', [$this, 'save_fields']);

        // Admin JS for conditional fields
        add_action('admin_footer', [$this, 'admin_js']);
    }

    /**
     * Get current product for editing
     */
    private function get_current_product(): ?WC_Product
    {
        if (null === $this->product) {
            global $product_object;
            $this->product = $product_object;
        }
        return $this->product;
    }

    /**
     * Add Mix & Match fields in General tab
     */
    public function add_fields(): void
    {
        $product = $this->get_current_product();

        // Only show for MNM products
        if (!$product || $product->get_type() !== 'mnm') {
            return;
        }

        // Get saved values
        $pricing_mode = $product->get_meta('_mnm_pricing_mode', true) ?: 'per_item';
        $fixed_price = $product->get_meta('_mnm_fixed_price', true) ?: '';
        $min_qty = $product->get_meta('_mnm_min_qty', true) ?: '';
        $max_qty = $product->get_meta('_mnm_max_qty', true) ?: '';
        $child_source = $product->get_meta('_mnm_child_source', true) ?: 'products';

        // Get selected products and categories
        $selected_products = $product->get_meta('_mnm_child_products', true) ?: [];
        $selected_categories = $product->get_meta('_mnm_child_categories', true) ?: [];

        echo '<div class="options_group show_if_mnm" id="mnm_product_data">';

        // Pricing mode
        woocommerce_wp_select([
            'id' => '_mnm_pricing_mode',
            'label' => __('Pricing Mode', 'wc-mix-and-match'),
            'value' => $pricing_mode,
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
            'label' => __('Fixed Container Price (' . get_woocommerce_currency_symbol() . ')', 'wc-mix-and-match'),
            'value' => $fixed_price,
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min' => '0',
                'placeholder' => __('0.00', 'wc-mix-and-match'),
            ],
            'description' => __('Required when pricing mode is "Fixed Price".', 'wc-mix-and-match'),
            'desc_tip' => true,
        ]);

        // Min quantity
        woocommerce_wp_text_input([
            'id' => '_mnm_min_qty',
            'label' => __('Minimum Quantity', 'wc-mix-and-match'),
            'value' => $min_qty,
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
                'placeholder' => __('0', 'wc-mix-and-match'),
            ],
            'description' => __('Minimum number of items customers must select. Set 0 for no minimum.', 'wc-mix-and-match'),
            'desc_tip' => true,
        ]);

        // Max quantity
        woocommerce_wp_text_input([
            'id' => '_mnm_max_qty',
            'label' => __('Maximum Quantity', 'wc-mix-and-match'),
            'value' => $max_qty,
            'type' => 'number',
            'custom_attributes' => [
                'min' => '0',
                'placeholder' => __('0', 'wc-mix-and-match'),
            ],
            'description' => __('Maximum number of items customers can select. Set 0 for unlimited.', 'wc-mix-and-match'),
            'desc_tip' => true,
        ]);

        // Child products source
        woocommerce_wp_select([
            'id' => '_mnm_child_source',
            'label' => __('Child Product Source', 'wc-mix-and-match'),
            'value' => $child_source,
            'options' => [
                'products' => __('Specific Products', 'wc-mix-and-match'),
                'categories' => __('Product Categories', 'wc-mix-and-match'),
            ],
            'desc_tip' => true,
            'description' => __('Choose how customers can select products.', 'wc-mix-and-match'),
        ]);

        // Child products (specific products)
        woocommerce_wp_select([
            'id' => '_mnm_child_products',
            'label' => __('Child Products', 'wc-mix-and-match'),
            'value' => $selected_products,
            'options' => $this->get_simple_products(),
            'class' => 'wc-enhanced-select',
            'custom_attributes' => [
                'multiple' => 'multiple',
                'data-placeholder' => __('Search for products...', 'wc-mix-and-match'),
            ],
            'desc_tip' => true,
            'description' => __('Products customers can choose from.', 'wc-mix-and-match'),
        ]);

        // Child categories
        woocommerce_wp_select([
            'id' => '_mnm_child_categories',
            'label' => __('Allowed Categories', 'wc-mix-and-match'),
            'value' => $selected_categories,
            'options' => $this->get_product_categories(),
            'class' => 'wc-enhanced-select',
            'custom_attributes' => [
                'multiple' => 'multiple',
                'data-placeholder' => __('Select categories...', 'wc-mix-and-match'),
            ],
            'desc_tip' => true,
            'description' => __('Products from these categories can be selected.', 'wc-mix-and-match'),
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
            $fixed_price = 0;
        }

        $child_source = isset($_POST['_mnm_child_source'])
            ? wc_clean($_POST['_mnm_child_source'])
            : 'products';

        $categories = isset($_POST['_mnm_child_categories'])
            ? array_map('absint', (array) $_POST['_mnm_child_categories'])
            : [];

        // Validate based on source
        if ($child_source === 'products' && empty($children)) {
            WC_Admin_Meta_Boxes::add_error(
                __('Please select at least one product for Mix & Match.', 'wc-mix-and-match')
            );
        }

        if ($child_source === 'categories' && empty($categories)) {
            WC_Admin_Meta_Boxes::add_error(
                __('Please select at least one category for Mix & Match.', 'wc-mix-and-match')
            );
        }

        // Save meta
        $product->update_meta_data('_mnm_pricing_mode', $pricing_mode);
        $product->update_meta_data('_mnm_fixed_price', $fixed_price);
        $product->update_meta_data('_mnm_min_qty', $min_qty);
        $product->update_meta_data('_mnm_max_qty', $max_qty);
        $product->update_meta_data('_mnm_child_products', $children);
        $product->update_meta_data('_mnm_child_source', $child_source);
        $product->update_meta_data('_mnm_child_categories', $categories);
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
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = [];

        foreach ($products as $product) {
            $options[$product->get_id()] = esc_html($product->get_name());
        }

        return $options;
    }

    /**
     * Category fetch helper
     */
    private function get_product_categories(): array
    {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $options = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->term_id] = $term->name;
            }
        }

        return $options;
    }

    /**
     * Conditional field visibility and UX enhancements
     */
    public function admin_js(): void
    {
        $screen = get_current_screen();

        if (!$screen || 'product' !== $screen->post_type) {
            return;
        }
        ?>
        <script type="text/javascript">
            (function ($) {
                'use strict';

                function toggleMNMFields() {
                    var source = $('#_mnm_child_source').val();

                    // Show/hide product/category selectors
                    $('#_mnm_child_products_field').toggle(source === 'products');
                    $('#_mnm_child_categories_field').toggle(source === 'categories');

                    // Enable/disable fixed price field
                    var pricingMode = $('#_mnm_pricing_mode').val();
                    var fixedPriceField = $('#_mnm_fixed_price');

                    if (pricingMode === 'fixed') {
                        fixedPriceField.prop('disabled', false)
                            .closest('.form-field').show();
                    } else {
                        fixedPriceField.prop('disabled', true)
                            .closest('.form-field').hide();
                    }
                }

                function validateMNMFields() {
                    var minQty = parseInt($('#_mnm_min_qty').val()) || 0;
                    var maxQty = parseInt($('#_mnm_max_qty').val()) || 0;

                    // Validate min <= max when both are set
                    if (maxQty > 0 && minQty > maxQty) {
                        $('#_mnm_min_qty').val(maxQty);
                    }

                    // Update placeholder based on min/max
                    var container = $('#mnm_product_data');
                    var totalSelected = 0;

                    // Count selected products (placeholder for now)
                    container.find('input[type="number"]').each(function () {
                        totalSelected += parseInt($(this).val()) || 0;
                    });

                    // Update summary
                    var summaryText = '';
                    if (minQty > 0 && maxQty > 0) {
                        summaryText = 'Customers must select between ' + minQty + ' and ' + maxQty + ' items.';
                    } else if (minQty > 0) {
                        summaryText = 'Customers must select at least ' + minQty + ' items.';
                    } else if (maxQty > 0) {
                        summaryText = 'Customers can select up to ' + maxQty + ' items.';
                    } else {
                        summaryText = 'Customers can select any number of items.';
                    }

                    // Remove existing summary
                    $('.mnm-summary').remove();

                    // Add summary
                    $('#mnm_product_data').append(
                        '<p class="description mnm-summary" style="margin-top: 10px; padding: 10px; background: #f5f5f5; border-radius: 4px;">' +
                        '<strong>' + summaryText + '</strong>' +
                        '</p>'
                    );
                }

                // Initialize on page load
                $(document).ready(function () {
                    // Wrap form fields for better targeting
                    $('#_mnm_child_products').closest('.form-field').attr('id', '_mnm_child_products_field');
                    $('#_mnm_child_categories').closest('.form-field').attr('id', '_mnm_child_categories_field');

                    // Initial toggle
                    toggleMNMFields();
                    validateMNMFields();

                    // Add event listeners
                    $(document).on('change', '#_mnm_child_source, #_mnm_pricing_mode', toggleMNMFields);
                    $(document).on('change keyup', '#_mnm_min_qty, #_mnm_max_qty', validateMNMFields);

                    // Add validation before form submission
                    $('form#post').on('submit', function (e) {
                        var childSource = $('#_mnm_child_source').val();

                        if (childSource === 'products') {
                            var selectedProducts = $('#_mnm_child_products').val() || [];
                            if (selectedProducts.length === 0) {
                                alert('<?php esc_html_e('Please select at least one product.', 'wc-mix-and-match'); ?>');
                                e.preventDefault();
                                return false;
                            }
                        } else if (childSource === 'categories') {
                            var selectedCategories = $('#_mnm_child_categories').val() || [];
                            if (selectedCategories.length === 0) {
                                alert('<?php esc_html_e('Please select at least one category.', 'wc-mix-and-match'); ?>');
                                e.preventDefault();
                                return false;
                            }
                        }

                        return true;
                    });
                });

            })(jQuery);
        </script>
        <style type="text/css">
            #mnm_product_data .wc-enhanced-select {
                width: 100%;
                max-width: 400px;
            }

            #mnm_product_data .form-field {
                margin-bottom: 15px;
            }

            .mnm-summary {
                transition: all 0.3s ease;
            }
        </style>
        <?php
    }
}

// Admin only
if (is_admin()) {
    new WC_MNM_Product_Data();
}