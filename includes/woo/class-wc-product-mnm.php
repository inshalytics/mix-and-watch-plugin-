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
     * Treat it as purchasable (we will control add-to-cart validation later).
     */
    public function is_purchasable(): bool
    {
        return parent::is_purchasable();
    }

    /**
     * For MVP, virtual/shipping behavior stays default.
     * We'll enhance later when we add packing modes.
     */
}
