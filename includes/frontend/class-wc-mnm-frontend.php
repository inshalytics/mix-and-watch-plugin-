<?php

if (!defined('ABSPATH')) {
    exit;
}

final class WC_MNM_Frontend
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): WC_MNM_Frontend
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize frontend functionality
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void
    {
        // Template filter override
        add_filter('woocommerce_locate_template', [$this, 'locate_template'], 999, 3);

        // Direct action override
        add_action('woocommerce_single_product_summary', [$this, 'override_add_to_cart'], 29);

        // Add frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add additional data to localized script
        add_filter('wp_enqueue_scripts', [$this, 'add_pricing_data']);
    }

    /**
     * Template filter override
     */
    public function locate_template($template, $template_name, $template_path): string
    {
        // Only override for simple product add to cart template
        if ('single-product/add-to-cart/simple.php' === $template_name) {
            global $product;

            if ($product && $product->is_type('mnm')) {
                $plugin_template = WC_MNM_PLUGIN_DIR . 'templates/single-product/add-to-cart/mnm.php';

                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }

        return $template;
    }

    /**
     * Direct action override
     */
    public function override_add_to_cart(): void
    {
        global $product;

        if (!$product || !$product->is_type('mnm')) {
            return;
        }

        // Remove WooCommerce's default add to cart template
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);

        // Include our custom template
        $this->display_mnm_template();
    }

    /**
     * Display MNM template
     */
    private function display_mnm_template(): void
    {
        global $product;

        if (!$product || !$product->is_type('mnm')) {
            return;
        }

        $template_path = WC_MNM_PLUGIN_DIR . 'templates/single-product/add-to-cart/mnm.php';

        if (file_exists($template_path)) {
            // Get child products
            $child_products = $product->get_allowed_child_products();

            if (empty($child_products)) {
                echo '<div class="wc-mnm-no-products">';
                echo '<p>' . esc_html__('No products available for selection.', 'wc-mix-and-match') . '</p>';
                echo '</div>';
                return;
            }

            // Include the template
            include $template_path;
        }
    }

    /**
     * Add pricing data to localized script
     */
    public function add_pricing_data(): void
    {
        // Only on single product pages
        if (!is_product()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $product = wc_get_product($post->ID);
        if (!$product || !$product->is_type('mnm')) {
            return;
        }

        // Add base price data for base_addon mode
        if (method_exists($product, 'get_base_price')) {
            $base_price = $product->get_base_price();
        } else {
            $base_price = 0;
        }

        wp_localize_script('wc-mnm-frontend', 'wc_mnm_pricing', [
            'base_price' => $base_price,
        ]);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts(): void
    {
        // Only on single product pages
        if (!is_product()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $product = wc_get_product($post->ID);

        if (!$product || !$product->is_type('mnm')) {
            return;
        }

        // Get container pricing data
        $pricing_mode = $product->get_pricing_mode();
        $base_price = 0;

        if ('base_addon' === $pricing_mode && method_exists($product, 'get_base_price')) {
            $base_price = $product->get_base_price();
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wc-mnm-frontend',
            WC_MNM_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            WC_MNM_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'wc-mnm-frontend',
            WC_MNM_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery', 'wc-add-to-cart'],
            WC_MNM_VERSION,
            true
        );

        $currency_pos = get_option('woocommerce_currency_pos');
        $price_format = get_woocommerce_price_format();

        // Prepare data for JavaScript
        wp_localize_script('wc-mnm-frontend', 'wc_mnm_params', [
            'product_id' => $product->get_id(),
            'min_qty' => $product->get_min_quantity(),
            'max_qty' => $product->get_max_quantity(),
            'pricing_mode' => $pricing_mode,
            'base_price' => $base_price,
            'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
            'currency_position' => $currency_pos,
            'price_format' => $price_format,
            'price_decimals' => wc_get_price_decimals(),
            'price_decimal_sep' => wc_get_price_decimal_separator(),
            'price_thousand_sep' => wc_get_price_thousand_separator(),
            'minimum_price' => method_exists($product, 'get_minimum_price') ? $product->get_minimum_price() : 0,
            'i18n' => [
                'selection_complete' => __('Selection complete. Ready to add to cart.', 'wc-mix-and-match'),
                'need_more_items' => __('You have selected %d items total, please select %d more item(s) to continue.', 'wc-mix-and-match'),
                'select_items' => __('Please select items.', 'wc-mix-and-match'),
                'base_price' => __('Base price:', 'wc-mix-and-match'),
                'addons_total' => __('Add-ons total:', 'wc-mix-and-match'),
                'container_total' => __('Container total:', 'wc-mix-and-match'),
                'max_limit_reached' => __('Maximum limit reached. To add more items, reduce quantities of other products.', 'wc-mix-and-match'),
            ],
        ]);
    }
}