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
     * Cache for child products to avoid repeated queries
     *
     * @var array|null
     */
    protected $child_products_cache = null;

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
        return absint($this->get_meta('_mnm_min_qty', true) ?: 0);
    }

    /**
     * Get maximum quantity
     */
    public function get_max_quantity(): int
    {
        return absint($this->get_meta('_mnm_max_qty', true) ?: 0);
    }

    /**
     * Get child product source
     */
    public function get_child_source(): string
    {
        return $this->get_meta('_mnm_child_source', true) ?: 'products';
    }

    /**
     * Get maximum products limit
     */
    public function get_max_products_limit(): int
    {
        return absint($this->get_meta('_mnm_max_products_limit', true) ?: 0);
    }

    /**
     * Get selected child product IDs
     */
    public function get_child_product_ids(): array
    {
        $ids = $this->get_meta('_mnm_child_products', true);
        return is_array($ids) ? array_map('absint', $ids) : [];
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
     * Get selected tag IDs
     */
    public function get_child_tag_ids(): array
    {
        $ids = $this->get_meta('_mnm_child_tags', true);
        return is_array($ids) ? array_map('absint', $ids) : [];
    }

    /**
     * Get display layout
     */
    public function get_display_layout(): string
    {
        return $this->get_meta('_mnm_display_layout', true) ?: 'grid';
    }

    /**
     * Resolve and get all allowed child products (actual WC_Product objects)
     */
    public function get_allowed_child_products(): array
    {
        // Use cache to avoid repeated queries
        if ($this->child_products_cache !== null) {
            return $this->child_products_cache;
        }

        $child_products = [];
        $source = $this->get_child_source();
        $max_limit = $this->get_max_products_limit();

        switch ($source) {
            case 'products':
                $child_products = $this->get_products_from_selection();
                break;

            case 'categories':
                $child_products = $this->get_products_from_categories($max_limit);
                break;

            case 'tags':
                $child_products = $this->get_products_from_tags($max_limit);
                break;
        }

        // Cache the result
        $this->child_products_cache = $child_products;

        return $child_products;
    }

    /**
     * Get products from specific product selection
     */
    private function get_products_from_selection(): array
    {
        $product_ids = $this->get_child_product_ids();
        $products = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($this->is_valid_child_product($product)) {
                $products[$product_id] = $product;
            }
        }

        return $products;
    }

    /**
     * Get products from selected categories
     */
    private function get_products_from_categories(int $limit = 0): array
    {
        $category_ids = $this->get_child_category_ids();

        if (empty($category_ids)) {
            return [];
        }

        $args = [
            'limit' => $limit > 0 ? $limit : -1,
            'type' => ['simple'],
            'status' => 'publish',
            'return' => 'objects',
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_ids,
                    'operator' => 'IN',
                ]
            ],
        ];

        $products = wc_get_products($args);
        $valid_products = [];

        foreach ($products as $product) {
            if ($this->is_valid_child_product($product)) {
                $valid_products[$product->get_id()] = $product;
            }
        }

        return $valid_products;
    }

    /**
     * Get products from selected tags
     */
    private function get_products_from_tags(int $limit = 0): array
    {
        $tag_ids = $this->get_child_tag_ids();

        if (empty($tag_ids)) {
            return [];
        }

        $args = [
            'limit' => $limit > 0 ? $limit : -1,
            'type' => ['simple'],
            'status' => 'publish',
            'return' => 'objects',
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'product_tag',
                    'field' => 'term_id',
                    'terms' => $tag_ids,
                    'operator' => 'IN',
                ]
            ],
        ];

        $products = wc_get_products($args);
        $valid_products = [];

        foreach ($products as $product) {
            if ($this->is_valid_child_product($product)) {
                $valid_products[$product->get_id()] = $product;
            }
        }

        return $valid_products;
    }

    /**
     * Check if a product is valid as a child product
     */
    private function is_valid_child_product($product): bool
    {
        return $product &&
            $product->is_type('simple') &&
            $product->is_purchasable() &&
            $product->is_visible();
    }

    /**
     * Clear child products cache
     */
    public function clear_child_products_cache(): void
    {
        $this->child_products_cache = null;
    }

    /**
     * Get container size string for display
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
     * Check if product is valid for adding to cart
     */
    public function is_purchasable(): bool
    {
        $allowed_products = $this->get_allowed_child_products();
        return parent::is_purchasable() && !empty($allowed_products);
    }

    /**
     * Get container rules description
     */
    public function get_rules_description(): string
    {
        $min = $this->get_min_quantity();
        $max = $this->get_max_quantity();

        if ($min > 0 && $max > 0) {
            if ($min === $max) {
                return sprintf(__('Select exactly %d items total.', 'wc-mix-and-match'), $min);
            }
            return sprintf(__('Select %1$d to %2$d items total.', 'wc-mix-and-match'), $min, $max);
        } elseif ($min > 0) {
            return sprintf(__('Select at least %d items total.', 'wc-mix-and-match'), $min);
        } elseif ($max > 0) {
            return sprintf(__('Select up to %d items total.', 'wc-mix-and-match'), $max);
        }

        return __('Select items.', 'wc-mix-and-match');
    }

    /**
     * Get child source description for admin reference
     */
    public function get_child_source_description(): string
    {
        $source = $this->get_child_source();
        $limit = $this->get_max_products_limit();

        switch ($source) {
            case 'products':
                $count = count($this->get_child_product_ids());
                return sprintf(__('%d specific products', 'wc-mix-and-match'), $count);

            case 'categories':
                $count = count($this->get_child_category_ids());
                $limit_text = $limit > 0 ? sprintf(__(' (limited to %d products)', 'wc-mix-and-match'), $limit) : '';
                return sprintf(__('%d product categories%s', 'wc-mix-and-match'), $count, $limit_text);

            case 'tags':
                $count = count($this->get_child_tag_ids());
                $limit_text = $limit > 0 ? sprintf(__(' (limited to %d products)', 'wc-mix-and-match'), $limit) : '';
                return sprintf(__('%d product tags%s', 'wc-mix-and-match'), $count, $limit_text);

            default:
                return __('No source selected', 'wc-mix-and-match');
        }
    }
}