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
     * Get base container price (for base_addon mode)
     */
    public function get_base_price(): float
    {
        return floatval($this->get_meta('_mnm_base_price', true) ?: 0);
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
     * Calculate container price based on selected items
     */
    public function calculate_container_price(array $selected_items = []): float
    {
        $pricing_mode = $this->get_pricing_mode();

        switch ($pricing_mode) {
            case 'fixed':
                return $this->get_fixed_price();

            case 'base_addon':
                $base_price = $this->get_base_price();
                $addons_total = $this->calculate_addons_total($selected_items);
                return $base_price + $addons_total;

            case 'per_item':
            default:
                return $this->calculate_addons_total($selected_items);
        }
    }

    /**
     * Calculate total price of selected add-on items
     */
    private function calculate_addons_total(array $selected_items): float
    {
        $total = 0.0;

        foreach ($selected_items as $product_id => $quantity) {
            $product = wc_get_product($product_id);
            if ($product && $quantity > 0) {
                $total += $product->get_price() * $quantity;
            }
        }

        return $total;
    }

    /**
     * Get minimum possible price for this container
     */
    public function get_minimum_price(): float
    {
        $pricing_mode = $this->get_pricing_mode();
        $min_qty = $this->get_min_quantity();

        switch ($pricing_mode) {
            case 'fixed':
                return $this->get_fixed_price();

            case 'base_addon':
                $base_price = $this->get_base_price();
                if ($min_qty > 0) {
                    $min_addon_price = $this->get_minimum_addon_price();
                    return $base_price + $min_addon_price;
                }
                return $base_price;

            case 'per_item':
            default:
                if ($min_qty > 0) {
                    return $this->get_minimum_addon_price();
                }
                return 0.0;
        }
    }

    /**
     * Get minimum possible add-on price based on cheapest items
     */
    private function get_minimum_addon_price(): float
    {
        $min_qty = $this->get_min_quantity();
        if ($min_qty <= 0) {
            return 0.0;
        }

        $child_products = $this->get_allowed_child_products();
        if (empty($child_products)) {
            return 0.0;
        }

        // Get prices of all child products
        $prices = [];
        foreach ($child_products as $product) {
            $prices[] = $product->get_price();
        }

        // Sort prices ascending
        sort($prices);

        // Calculate minimum price for required quantity
        $total = 0.0;
        for ($i = 0; $i < min($min_qty, count($prices)); $i++) {
            $total += $prices[$i];
        }

        return $total;
    }

    /**
     * Get pricing description for frontend display
     */
    public function get_pricing_description(): string
    {
        $pricing_mode = $this->get_pricing_mode();
        $min_qty = $this->get_min_quantity();

        switch ($pricing_mode) {
            case 'fixed':
                $price = wc_price($this->get_fixed_price());
                return sprintf(__('Fixed price: %s', 'wc-mix-and-match'), $price);

            case 'base_addon':
                $base_price = wc_price($this->get_base_price());
                if ($min_qty > 0) {
                    $min_price = wc_price($this->get_minimum_price());
                    return sprintf(__('Base price: %s + item prices (minimum: %s)', 'wc-mix-and-match'), $base_price, $min_price);
                }
                return sprintf(__('Base price: %s + item prices', 'wc-mix-and-match'), $base_price);

            case 'per_item':
            default:
                if ($min_qty > 0) {
                    $min_price = wc_price($this->get_minimum_price());
                    return sprintf(__('Item prices (minimum: %s)', 'wc-mix-and-match'), $min_price);
                }
                return __('Item prices', 'wc-mix-and-match');
        }
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
        $pricing_mode = $this->get_pricing_mode();

        $rules = [];

        if ($min > 0 && $max > 0) {
            if ($min === $max) {
                $rules[] = sprintf(__('Select exactly %d items total.', 'wc-mix-and-match'), $min);
            } else {
                $rules[] = sprintf(__('Select %1$d to %2$d items total.', 'wc-mix-and-match'), $min, $max);
            }
        } elseif ($min > 0) {
            $rules[] = sprintf(__('Select at least %d items total.', 'wc-mix-and-match'), $min);
        } elseif ($max > 0) {
            $rules[] = sprintf(__('Select up to %d items total.', 'wc-mix-and-match'), $max);
        }

        // Add pricing info
        switch ($pricing_mode) {
            case 'fixed':
                $rules[] = __('Fixed container price.', 'wc-mix-and-match');
                break;
            case 'base_addon':
                $base_price = wc_price($this->get_base_price());
                $rules[] = sprintf(__('Base container price: %s.', 'wc-mix-and-match'), $base_price);
                break;
            case 'per_item':
                $rules[] = __('Pay for selected items.', 'wc-mix-and-match');
                break;
        }

        return implode(' ', $rules);
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

    /**
     * Get pricing mode description for admin
     */
    public function get_pricing_mode_description(): string
    {
        $mode = $this->get_pricing_mode();

        switch ($mode) {
            case 'fixed':
                $price = wc_price($this->get_fixed_price());
                return sprintf(__('Fixed Price: %s', 'wc-mix-and-match'), $price);

            case 'base_addon':
                $base_price = wc_price($this->get_base_price());
                return sprintf(__('Base Price + Add-ons: %s base + item prices', 'wc-mix-and-match'), $base_price);

            case 'per_item':
            default:
                return __('Per Item: Sum of selected item prices', 'wc-mix-and-match');
        }
    }
}