<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WC_MNM_Product_Data
{
    public function __construct()
    {
        // Save custom fields from Mix & Match tab
        add_action('woocommerce_admin_process_product_object', [$this, 'save_fields']);

        // Admin JS for conditional fields in Mix & Match tab
        add_action('admin_footer', [$this, 'admin_js']);
    }

    /**
     * Save product meta from Mix & Match tab
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

        // Base price (for base_addon mode)
        $base_price = isset($_POST['_mnm_base_price'])
            ? floatval($_POST['_mnm_base_price'])
            : 0;

        // Min / Max
        $min_qty = isset($_POST['_mnm_min_qty'])
            ? absint($_POST['_mnm_min_qty'])
            : 0;

        $max_qty = isset($_POST['_mnm_max_qty'])
            ? absint($_POST['_mnm_max_qty'])
            : 0;

        // Display layout
        $display_layout = isset($_POST['_mnm_display_layout'])
            ? wc_clean($_POST['_mnm_display_layout'])
            : 'grid';

        // Child source
        $child_source = isset($_POST['_mnm_child_source'])
            ? wc_clean($_POST['_mnm_child_source'])
            : 'products';

        // Child products
        $children = isset($_POST['_mnm_child_products'])
            ? array_map('absint', (array) $_POST['_mnm_child_products'])
            : [];

        // Child categories
        $categories = isset($_POST['_mnm_child_categories'])
            ? array_map('absint', (array) $_POST['_mnm_child_categories'])
            : [];

        // Child tags
        $tags = isset($_POST['_mnm_child_tags'])
            ? array_map('absint', (array) $_POST['_mnm_child_tags'])
            : [];

        // Maximum products limit
        $max_products_limit = isset($_POST['_mnm_max_products_limit'])
            ? absint($_POST['_mnm_max_products_limit'])
            : 0;

        // Validation and cleaning
        $this->validate_and_clean_data(
            $product,
            $pricing_mode,
            $fixed_price,
            $base_price,
            $min_qty,
            $max_qty,
            $child_source,
            $children,
            $categories,
            $tags,
            $max_products_limit
        );

        // Save meta
        $product->update_meta_data('_mnm_pricing_mode', $pricing_mode);
        $product->update_meta_data('_mnm_fixed_price', $fixed_price);
        $product->update_meta_data('_mnm_base_price', $base_price);
        $product->update_meta_data('_mnm_min_qty', $min_qty);
        $product->update_meta_data('_mnm_max_qty', $max_qty);
        $product->update_meta_data('_mnm_display_layout', $display_layout);
        $product->update_meta_data('_mnm_child_source', $child_source);
        $product->update_meta_data('_mnm_child_products', $children);
        $product->update_meta_data('_mnm_child_categories', $categories);
        $product->update_meta_data('_mnm_child_tags', $tags);
        $product->update_meta_data('_mnm_max_products_limit', $max_products_limit);
    }

    /**
     * Validate and clean Mix & Match data
     */
    private function validate_and_clean_data(
        WC_Product $product,
        string &$pricing_mode,
        float &$fixed_price,
        float &$base_price,
        int &$min_qty,
        int &$max_qty,
        string &$child_source,
        array &$children,
        array &$categories,
        array &$tags,
        int &$max_products_limit
    ): void {
        // Validate quantity rules
        if ($max_qty > 0 && $min_qty > $max_qty) {
            $min_qty = $max_qty;
        }

        // Validate pricing based on mode
        switch ($pricing_mode) {
            case 'fixed':
                if ($fixed_price <= 0) {
                    $pricing_mode = 'per_item';
                    $fixed_price = 0;
                }
                $base_price = 0;
                break;

            case 'base_addon':
                if ($base_price < 0) {
                    $base_price = 0;
                }
                $fixed_price = 0;
                break;

            case 'per_item':
                $fixed_price = 0;
                $base_price = 0;
                break;
        }

        // Clear unused data based on source
        if ($child_source === 'products') {
            $categories = [];
            $tags = [];
            $max_products_limit = 0;
        } elseif ($child_source === 'categories') {
            $children = [];
            $tags = [];
        } elseif ($child_source === 'tags') {
            $children = [];
            $categories = [];
        }

        // Ensure regular price is set for WooCommerce calculations
        if ($pricing_mode === 'fixed') {
            $product->set_regular_price($fixed_price);
            $product->set_price($fixed_price);
        } elseif ($pricing_mode === 'base_addon') {
            // Set base price as the minimum price
            $product->set_regular_price($base_price);
            $product->set_price($base_price);
        } else {
            // For per_item, set price to 0 since it's dynamic
            $product->set_regular_price(0);
            $product->set_price(0);
        }
    }

    /**
     * Admin JS for conditional field display in Mix & Match tab
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

                /**
                 * Toggle pricing fields based on selected mode
                 */
                function togglePricingFields() {
                    var pricingMode = $('#_mnm_pricing_mode').val();

                    // Fixed price field
                    var fixedPriceField = $('.mnm-fixed-price-field');
                    if (pricingMode === 'fixed') {
                        fixedPriceField.show();
                        $('#_mnm_fixed_price').prop('disabled', false);
                    } else {
                        fixedPriceField.hide();
                        $('#_mnm_fixed_price').prop('disabled', true);
                    }

                    // Base price field
                    var basePriceField = $('.mnm-base-price-field');
                    if (pricingMode === 'base_addon') {
                        basePriceField.show();
                        $('#_mnm_base_price').prop('disabled', false);
                    } else {
                        basePriceField.hide();
                        $('#_mnm_base_price').prop('disabled', true);
                    }

                    // Update pricing examples
                    updatePricingExamples(pricingMode);
                }

                /**
                 * Update pricing examples based on mode
                 */
                function updatePricingExamples(pricingMode) {
                    var examples = $('.mnm-pricing-examples');
                    var items = examples.find('li');

                    // Reset all examples
                    items.css('opacity', '0.6');

                    // Highlight active example
                    switch (pricingMode) {
                        case 'fixed':
                            items.eq(0).css('opacity', '1').css('font-weight', 'bold');
                            break;
                        case 'per_item':
                            items.eq(1).css('opacity', '1').css('font-weight', 'bold');
                            break;
                        case 'base_addon':
                            items.eq(2).css('opacity', '1').css('font-weight', 'bold');
                            break;
                    }
                }

                /**
                 * Toggle child product fields based on source
                 */
                function toggleChildSourceFields() {
                    var source = $('#_mnm_child_source').val();

                    // Show/hide product/category/tag selectors
                    $('.mnm-child-products-field').toggle(source === 'products');
                    $('.mnm-child-categories-field').toggle(source === 'categories');
                    $('.mnm-child-tags-field').toggle(source === 'tags');

                    // Show/hide max products limit field (only for categories and tags)
                    $('.mnm-max-products-field').toggle(source === 'categories' || source === 'tags');
                }

                /**
                 * Initialize on page load
                 */
                $(document).ready(function () {
                    // Initial toggles
                    togglePricingFields();
                    toggleChildSourceFields();

                    // Add event listeners
                    $(document).on('change', '#_mnm_pricing_mode', togglePricingFields);
                    $(document).on('change', '#_mnm_child_source', toggleChildSourceFields);

                    // Ensure Mix & Match tab is visible when product type is MNM
                    var isMnmProduct = $('#product-type').val() === 'mnm';
                    if (isMnmProduct) {
                        $('.mix_and_match_options').show();
                        // Show pricing examples with active mode
                        var currentMode = $('#_mnm_pricing_mode').val();
                        updatePricingExamples(currentMode);
                    }
                });

                /**
                 * Handle product type change
                 */
                $(document).on('change', '#product-type', function () {
                    var isMnmProduct = $(this).val() === 'mnm';
                    $('.mix_and_match_options').toggle(isMnmProduct);

                    // If switching to MNM, activate the Mix & Match tab
                    if (isMnmProduct) {
                        setTimeout(function () {
                            $('.mix_and_match_options a').trigger('click');
                        }, 100);
                    }
                });

            })(jQuery);
        </script>
        <?php
    }
}

// Admin only
if (is_admin()) {
    new WC_MNM_Product_Data();
}