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
        // Make sure regular pricing fields appear for our type.
        add_filter('product_type_options', [$this, 'product_type_options']);


        add_action('admin_footer', [$this, 'register_mnm_as_simple']);
 
        // Add our type to the list of types that should show certain tabs.
        // add_filter('woocommerce_product_data_tabs', [$this, 'product_data_tabs']);

        // Tell WooCommerce which features our product type supports
        // add_filter('woocommerce_product_type_supports', [$this, 'add_product_type_supports'], 10, 3);

        // Add JS so WC knows how to show/hide tabs for our type.
        // add_action('admin_footer', [$this, 'admin_js']);
    }

    public function product_type_options(array $options): array
    {
        // Keep defaults; no change needed for MVP.
        return $options;
    }


    public function register_mnm_as_simple(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        ?>
        <script>
            (function ($) {
                if ( typeof wc_product_types !== 'undefined' ) {
                    if ( wc_product_types.simple.indexOf('mnm') === -1 ) {
                        wc_product_types.simple.push('mnm');
                    }
                }
            })(jQuery);
        </script>
        <?php
    }


}

// Instantiate on admin only.
if (is_admin()) {
    new WC_MNM_Product_Type_Admin();
}