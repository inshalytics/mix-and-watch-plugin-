<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_MNM_Debug
{
    public static function log($message, $data = null): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_entry = date('Y-m-d H:i:s') . ' - ' . $message;

            if ($data !== null) {
                $log_entry .= ' - ' . print_r($data, true);
            }

            error_log('[WC_MNM] ' . $log_entry);
        }
    }

    public static function check_template_override(): void
    {
        global $product;

        if (!$product) {
            self::log('No product object found');
            return;
        }

        self::log('Current product type', $product->get_type());
        self::log('Is MNM?', $product->is_type('mnm'));

        if ($product->is_type('mnm')) {
            self::log('Product ID', $product->get_id());
            self::log('Child products count', count($product->get_allowed_child_products()));
        }
    }
}