<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin helpers for Mix and Match product type.
 */
final class WC_MNM_Product_Type_Admin
{
    public function __construct()
    {
        // Register Mix & Match as simple product type
        add_action('admin_footer', [$this, 'register_mnm_as_simple']);

        // Add Mix & Match product data tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_mix_and_match_tab']);

        // Add content to Mix & Match tab
        add_action('woocommerce_product_data_panels', [$this, 'add_mix_and_match_tab_content']);

        // Add custom CSS for tab styling
        add_action('admin_head', [$this, 'admin_custom_css']);
    }

    /**
     * Register MNM as simple product type for pricing fields
     */
    public function register_mnm_as_simple(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        ?>
        <script>
            (function ($) {
                if (typeof wc_product_types !== 'undefined') {
                    if (wc_product_types.simple.indexOf('mnm') === -1) {
                        wc_product_types.simple.push('mnm');
                    }
                }
            })(jQuery);
        </script>
        <?php
    }

    /**
     * Add Mix & Match tab to product data tabs
     */
    public function add_mix_and_match_tab(array $tabs): array
    {
        $tabs['mix_and_match'] = [
            'label' => __('Mix & Match', 'wc-mix-and-match'),
            'target' => 'mix_and_match_product_data',
            'class' => ['show_if_mnm'],
            'priority' => 80, // After Inventory, before Shipping
        ];

        return $tabs;
    }

    /**
     * Add content to Mix & Match tab
     */
    public function add_mix_and_match_tab_content(): void
    {
        global $product_object;
        $product = $product_object;

        // Only show for MNM products
        if (!$product || $product->get_type() !== 'mnm') {
            return;
        }

        // Get saved values
        $pricing_mode = $product->get_meta('_mnm_pricing_mode', true) ?: 'per_item';
        $fixed_price = $product->get_meta('_mnm_fixed_price', true) ?: '';
        $base_price = $product->get_meta('_mnm_base_price', true) ?: '';
        $min_qty = $product->get_meta('_mnm_min_qty', true) ?: '';
        $max_qty = $product->get_meta('_mnm_max_qty', true) ?: '';
        $child_source = $product->get_meta('_mnm_child_source', true) ?: 'products';
        $display_layout = $product->get_meta('_mnm_display_layout', true) ?: 'grid';
        $max_products_limit = $product->get_meta('_mnm_max_products_limit', true) ?: '';

        // Get selected products, categories, and tags
        $selected_products = $product->get_meta('_mnm_child_products', true) ?: [];
        $selected_categories = $product->get_meta('_mnm_child_categories', true) ?: [];
        $selected_tags = $product->get_meta('_mnm_child_tags', true) ?: [];

        ?>
        <div id="mix_and_match_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group" style="padding: 20px;">

                <!-- Tab Header -->
                <div class="mnm-tab-header"
                    style="margin-bottom: 30px; border-bottom: 2px solid #f1f1f1; padding-bottom: 15px;">
                    <h3 style="margin: 0; color: #333; font-size: 1.3em;">
                        <?php _e('Mix & Match Container Configuration', 'wc-mix-and-match'); ?>
                    </h3>
                    <p style="margin: 5px 0 0; color: #666; font-size: 0.95em;">
                        <?php _e('Configure how customers can mix and match products in this container.', 'wc-mix-and-match'); ?>
                    </p>
                </div>

                <!-- Pricing Settings Section -->
                <div class="mnm-section" style="margin-bottom: 30px;">
                    <h4 class="mnm-section-title"
                        style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; color: #2c3e50; font-size: 1.1em;">
                        <span class="dashicons dashicons-money-alt" style="margin-right: 8px; color: #7e3bd0;"></span>
                        <?php _e('Pricing Settings', 'wc-mix-and-match'); ?>
                    </h4>

                    <div class="mnm-section-content" style="padding-left: 30px;">
                        <?php
                        woocommerce_wp_select([
                            'id' => '_mnm_pricing_mode',
                            'label' => __('Pricing Mode', 'wc-mix-and-match'),
                            'value' => $pricing_mode,
                            'options' => [
                                'fixed' => __('Fixed Price', 'wc-mix-and-match'),
                                'per_item' => __('Per Item Price', 'wc-mix-and-match'),
                                'base_addon' => __('Base Price + Add-on products', 'wc-mix-and-match'),
                            ],
                            'desc_tip' => true,
                            'description' => $this->get_pricing_mode_descriptions(),
                            'wrapper_class' => 'mnm-field mnm-pricing-mode-field',
                        ]);

                        // Fixed price field (shown when mode is 'fixed')
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
                            'description' => __('Total price regardless of items selected.', 'wc-mix-and-match'),
                            'desc_tip' => true,
                            'wrapper_class' => 'mnm-field mnm-fixed-price-field',
                        ]);

                        // Base price field (shown when mode is 'base_addon')
                        woocommerce_wp_text_input([
                            'id' => '_mnm_base_price',
                            'label' => __('Base Container Price (' . get_woocommerce_currency_symbol() . ')', 'wc-mix-and-match'),
                            'value' => $base_price,
                            'type' => 'number',
                            'custom_attributes' => [
                                'step' => '0.01',
                                'min' => '0',
                                'placeholder' => __('0.00', 'wc-mix-and-match'),
                            ],
                            'description' => __('Base fee for the container. Item prices will be added to this.', 'wc-mix-and-match'),
                            'desc_tip' => true,
                            'wrapper_class' => 'mnm-field mnm-base-price-field',
                        ]);
                        ?>
                    </div>
                </div>

                <!-- Quantity Rules Section -->
                <div class="mnm-section" style="margin-bottom: 30px;">
                    <h4 class="mnm-section-title"
                        style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; color: #2c3e50; font-size: 1.1em;">
                        <span class="dashicons dashicons-filter" style="margin-right: 8px; color: #3498db;"></span>
                        <?php _e('Quantity Rules', 'wc-mix-and-match'); ?>
                    </h4>

                    <div class="mnm-section-content" style="padding-left: 30px;">
                        <?php
                        woocommerce_wp_text_input([
                            'id' => '_mnm_min_qty',
                            'label' => __('Minimum Quantity', 'wc-mix-and-match'),
                            'value' => $min_qty,
                            'type' => 'number',
                            'custom_attributes' => [
                                'min' => '0',
                                'placeholder' => __('0', 'wc-mix-and-match'),
                            ],
                            'description' => __('Minimum total quantity customers must select. Set 0 for no minimum.', 'wc-mix-and-match'),
                            'desc_tip' => true,
                            'wrapper_class' => 'mnm-field',
                        ]);

                        woocommerce_wp_text_input([
                            'id' => '_mnm_max_qty',
                            'label' => __('Maximum Quantity', 'wc-mix-and-match'),
                            'value' => $max_qty,
                            'type' => 'number',
                            'custom_attributes' => [
                                'min' => '0',
                                'placeholder' => __('0', 'wc-mix-and-match'),
                            ],
                            'description' => __('Maximum total quantity customers can select. Set 0 for unlimited.', 'wc-mix-and-match'),
                            'desc_tip' => true,
                            'wrapper_class' => 'mnm-field',
                        ]);
                        ?>
                    </div>
                </div>

                <!-- Display Settings Section -->
                <div class="mnm-section" style="margin-bottom: 30px;">
                    <h4 class="mnm-section-title"
                        style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; color: #2c3e50; font-size: 1.1em;">
                        <span class="dashicons dashicons-layout" style="margin-right: 8px; color: #27ae60;"></span>
                        <?php _e('Display Settings', 'wc-mix-and-match'); ?>
                    </h4>

                    <div class="mnm-section-content" style="padding-left: 30px;">
                        <?php
                        woocommerce_wp_select([
                            'id' => '_mnm_display_layout',
                            'label' => __('Display Layout', 'wc-mix-and-match'),
                            'value' => $display_layout,
                            'options' => [
                                'grid' => __('Grid Layout', 'wc-mix-and-match'),
                                'list' => __('List Layout', 'wc-mix-and-match'),
                            ],
                            'desc_tip' => true,
                            'description' => __('Grid: Products displayed in a responsive grid.<br>List: Products displayed in a vertical list.', 'wc-mix-and-match'),
                            'wrapper_class' => 'mnm-field',
                        ]);
                        ?>
                    </div>
                </div>

                <!-- Child Products Section -->
                <div class="mnm-section" style="margin-bottom: 30px;">
                    <h4 class="mnm-section-title"
                        style="margin: 0 0 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e5e5; color: #2c3e50; font-size: 1.1em;">
                        <span class="dashicons dashicons-screenoptions" style="margin-right: 8px; color: #e74c3c;"></span>
                        <?php _e('Child Products Configuration', 'wc-mix-and-match'); ?>
                    </h4>

                    <div class="mnm-section-content" style="padding-left: 30px;">
                        <?php
                        woocommerce_wp_select([
                            'id' => '_mnm_child_source',
                            'label' => __('Child Product Source', 'wc-mix-and-match'),
                            'value' => $child_source,
                            'options' => [
                                'products' => __('Specific Products', 'wc-mix-and-match'),
                                'categories' => __('Product Categories', 'wc-mix-and-match'),
                                'tags' => __('Product Tags', 'wc-mix-and-match'),
                            ],
                            'desc_tip' => true,
                            'description' => __('Choose how child products are selected for this container.', 'wc-mix-and-match'),
                            'wrapper_class' => 'mnm-field',
                        ]);

                        // Child products (for "Specific Products" source)
                        woocommerce_wp_select([
                            'id' => '_mnm_child_products',
                            'label' => __('Select Child Products', 'wc-mix-and-match'),
                            'value' => $selected_products,
                            'options' => $this->get_simple_products(),
                            'class' => 'wc-enhanced-select',
                            'custom_attributes' => [
                                'multiple' => 'multiple',
                                'data-placeholder' => __('Search for products...', 'wc-mix-and-match'),
                            ],
                            'desc_tip' => true,
                            'description' => __('Products customers can choose from.', 'wc-mix-and-match'),
                            'wrapper_class' => 'mnm-field mnm-child-products-field',
                        ]);

                        // Child categories (for "Product Categories" source)
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
                            'wrapper_class' => 'mnm-field mnm-child-categories-field',
                        ]);

                        // Child tags (for "Product Tags" source)
                        woocommerce_wp_select([
                            'id' => '_mnm_child_tags',
                            'label' => __('Allowed Tags', 'wc-mix-and-match'),
                            'value' => $selected_tags,
                            'options' => $this->get_product_tags(),
                            'class' => 'wc-enhanced-select',
                            'custom_attributes' => [
                                'multiple' => 'multiple',
                                'data-placeholder' => __('Select tags...', 'wc-mix-and-match'),
                            ],
                            'desc_tip' => true,
                            'description' => __('Products with these tags can be selected.', 'wc-mix-and-match'),
                            'wrapper_class' => 'mnm-field mnm-child-tags-field',
                        ]);

                        // Maximum products limit (for categories and tags only)
                        woocommerce_wp_text_input([
                            'id' => '_mnm_max_products_limit',
                            'label' => __('Maximum Products to Display', 'wc-mix-and-match'),
                            'value' => $max_products_limit,
                            'type' => 'number',
                            'custom_attributes' => [
                                'min' => '0',
                                'step' => '1',
                                'placeholder' => __('Unlimited', 'wc-mix-and-match'),
                            ],
                            'description' => __('Limit the number of products displayed (applies to Categories and Tags sources only). Set 0 for unlimited.', 'wc-mix-and-match'),
                            'desc_tip' => true,
                            'wrapper_class' => 'mnm-field mnm-max-products-field',
                        ]);
                        ?>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Get pricing mode descriptions
     */
    private function get_pricing_mode_descriptions(): string
    {
        return sprintf(
            '%s<br><br><strong>%s:</strong> %s<br><strong>%s:</strong> %s<br><strong>%s:</strong> %s',
            __('Choose how the container is priced:', 'wc-mix-and-match'),
            __('Fixed Price', 'wc-mix-and-match'),
            __('One price for entire container regardless of items selected.', 'wc-mix-and-match'),
            __('Per Item', 'wc-mix-and-match'),
            __('Sum of individual item prices.', 'wc-mix-and-match'),
            __('Base Price + Add-ons', 'wc-mix-and-match'),
            __('Container base fee plus individual item prices.', 'wc-mix-and-match')
        );
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
     * Get product categories for selector
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
     * Get product tags for selector
     */
    private function get_product_tags(): array
    {
        $terms = get_terms([
            'taxonomy' => 'product_tag',
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
     * Add custom CSS for Mix & Match tab
     */
    public function admin_custom_css(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        ?>
        <style>
            /* Mix & Match tab styling */
            #woocommerce-product-data .wc-tabs li.mix_and_match_options a::before {
                content: "\f538";
                font-family: dashicons;
                font-size: 16px;
                line-height: 1;
            }

            /* Section styling */
            .mnm-section {
                position: relative;
            }

            .mnm-section-title {
                display: flex;
                align-items: center;
            }

            .mnm-field {
                margin-bottom: 20px;
            }

            /* Conditional field hiding */
            .mnm-fixed-price-field,
            .mnm-base-price-field,
            .mnm-child-products-field,
            .mnm-child-categories-field,
            .mnm-child-tags-field,
            .mnm-max-products-field {
                transition: opacity 0.3s ease;
            }

            /* Enhanced select improvements */
            .select2-container .select2-selection--multiple {
                min-height: 34px;
                border-color: #ddd;
            }

            /* Pricing examples box */
            .mnm-pricing-examples {
                animation: fadeIn 0.5s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Responsive adjustments */
            @media (max-width: 782px) {
                .mnm-section-content {
                    padding-left: 15px !important;
                }

                .mnm-help-section>div {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
        <?php
    }
}

// Instantiate on admin only.
if (is_admin()) {
    new WC_MNM_Product_Type_Admin();
}