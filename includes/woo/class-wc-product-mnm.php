<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_MNM extends WC_Product_Simple
{

    /**
     * Product type name used internally by WooCommerce.
     *
     * @var string
     */
    protected $product_type = 'mnm';

    /**
     * Return product type.
     */
    public function get_type(): string
    {
        return 'mnm';
    }

    /**
     * Get pricing mode
     */
    public function get_pricing_mode(): string
    {
        return $this->get_meta('_mnm_pricing_mode', true) ?: 'per_item';
    }

    /**
     * Get fixed container price
     */
    public function get_fixed_price(): float
    {
        return floatval($this->get_meta('_mnm_fixed_price', true) ?: 0);
    }

    /**
     * Get minimum quantity
     */
    public function get_min_quantity(): int
    {
        return absint( $this->get_meta('_mnm_min_qty', true) ?: 0);
    }

    /**
     * Get maximum quantity
     */
    public function get_max_quantity(): int
    {
        return absint( $this->get_meta('_mnm_max_qty', true) ?: 0);
    }

    /**
     * Get child product source
     */
    public function get_child_source(): string
    {
        return $this->get_meta('_mnm_child_source', true) ?: 'products';
    }

    /**
     * Get selected child product IDs
     */
    public function get_child_product_ids(): array
    {
        $ids = $this->get_meta('_mnm_child_products', true);
        return is_array($ids) ? array_map('absint', $ids): [];
    }

    /**
     * Get selected category IDs
     */
    public function get_child_category_ids(): array
    {
        $ids = $this->get_meta('_mnm_child_categories', true);
        return is_array($ids) ? array_map('absint', $ids) : [];
    }

    /**
     * Resolve and get all allowed child products (actual WC_Product objects)
     * Based on source (products or categories)
     */

    public function get_allowed_child_products(): array
    {
        $child_products = [];
        $source = $this->get_child_source();

        if ('products' === $source) {
            // Get specific products
            $product_ids = $this->get_child_product_ids();
            foreach ($product_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ($product && $product->is_type('simple') && $product->is_purchasable()) {
                    $child_products[$product_id] = $product;
                }
            }
        } elseif ('categories' === $source) {
            // Get products from selected categories
            $category_ids = $this->get_child_category_ids();

            if (empty($category_ids)) {
                return [];
            }

            // Debug: Log category IDs
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WC_MNM] Fetching products from categories: ' . print_r($category_ids, true));
            }

            // Build tax query for categories
            $tax_query = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_ids,
                    'operator' => 'IN',
                ]
            ];

            // Check if we need to include subcategories
            // For now, let's just get direct category products

            $args = [
                'limit' => -1,
                'type' => ['simple'],  // Array format
                'status' => 'publish',
                'return' => 'objects',
                'orderby' => 'title',
                'order' => 'ASC',
                'tax_query' => $tax_query,
            ];

            // Debug: Log the query args
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WC_MNM] Product query args: ' . print_r($args, true));
            }

            $products = wc_get_products($args);

            // Debug: Log results
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WC_MNM] Found ' . count($products) . ' products from categories');
                foreach ($products as $product) {
                    error_log('[WC_MNM] Product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')');
                }
            }

            foreach ($products as $product) {
                if ($product->is_purchasable() && $product->is_type('simple')) {
                    $child_products[$product->get_id()] = $product;
                }
            }
        }

        // Debug: Final result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WC_MNM] Total child products returned: ' . count($child_products));
        }

        return $child_products;
    }


    /**
     * Get container size string for display
     * Example: "Choose 3-6 items" or "Choose at least 3 items"
     */
    public function get_container_size_string(): string
    {
        $min = $this->get_min_quantity();
        $max = $this->get_max_quantity();

        if ($min > 0 && $max > 0) {
            if ($min === $max) {
                return sprintf(_n('Choose %d item', 'Choose %d items', $min, 'wc-mix-and-match'), $min);
            }
            return sprintf(__('Choose %1$d–%2$d items', 'wc-mix-and-match'), $min, $max);
        } elseif ($min > 0) {
            return sprintf(__('Choose at least %d items', 'wc-mix-and-match'), $min);
        } elseif ($max > 0) {
            return sprintf(__('Choose up to %d items', 'wc-mix-and-match'), $max);
        }

        return __('Choose items', 'wc-mix-and-match');
    }

    /**
     * Check if product is valid for adding to cart (basic check)
     */
    public function is_purchasable(): bool
    {
        $allowed_products = $this->get_allowed_child_products();
        return parent::is_purchasable() && !empty($allowed_products);
    }
}
