<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin helpers for Mix and Match product type.
 *
 * Note: WooCommerce uses JS + CSS classes to show/hide pricing, inventory, shipping panels.
 * We add our product type to those lists so standard panels can work.
 */
final class WC_MNM_Product_Type_Admin
{

    public function __construct()
    {
        // Make sure regular pricing fields appear for our type.
        add_filter('product_type_options', [$this, 'product_type_options']);

        // Add our type to the list of types that should show certain tabs.
        add_filter('woocommerce_product_data_tabs', [$this, 'product_data_tabs']);

        // Add JS so WC knows how to show/hide tabs for our type.
        add_action('admin_footer', [$this, 'admin_js']);
    }

    public function product_type_options(array $options): array
    {
        // Keep defaults; no change needed for MVP.
        return $options;
    }

    public function product_data_tabs(array $tabs): array
    {
        // Ensure General tab (pricing) is visible for our type.
        if (isset($tabs['general'])) {
            $tabs['general']['class'][] = 'show_if_mnm';
        }

        // Ensure Inventory tab is visible for our type.
        if (isset($tabs['inventory'])) {
            $tabs['inventory']['class'][] = 'show_if_mnm';
        }

        // Ensure Shipping tab is visible (useful later; harmless now).
        if (isset($tabs['shipping'])) {
            $tabs['shipping']['class'][] = 'show_if_mnm';
        }

        return $tabs;
    }

    public function admin_js(): void
    {
        // Only load on product edit screens.
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'product' !== $screen->post_type) {
            return;
        }
        ?>
                <script>
                  (function($){
                    // Add our product type to WC's show/hide mechanism
                    // so panels with class "show_if_mnm" become visible.
                    $(document.body).on('woocommerce-product-type-change', function(){
                      // WC triggers this event; nothing else required here for MVP.
                    });
                  })(jQuery);
                </script>
                <?php
    }
}

// Instantiate on admin only.
if (is_admin()) {
    new WC_MNM_Product_Type_Admin();
}
