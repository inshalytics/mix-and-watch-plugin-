<?php
/**
 * Mix & Match Product Add to Cart Template
 *
 * @package WooCommerce Mix & Match
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

global $product;

// Ensure this is a MNM product
if (!$product->is_type('mnm')) {
    return;
}

/** @var WC_Product_MNM $product */
$container = $product;

// Get child products
$child_products = $container->get_allowed_child_products();

// Get container data
$min_qty = $container->get_min_quantity();
$max_qty = $container->get_max_quantity();
$pricing_mode = $container->get_pricing_mode();
$display_layout = $container->get_display_layout();
$rules_description = $container->get_rules_description();
$pricing_description = $container->get_pricing_description();

// Get base price if applicable
$base_price = 0;
$show_base_price = false;
if ('base_addon' === $pricing_mode) {
    $base_price = $container->get_base_price();
    $show_base_price = true;
}

do_action('woocommerce_before_add_to_cart_form');
?>

<form class="cart wc-mnm-form"
    action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>"
    method="post" enctype='multipart/form-data'>

    <?php do_action('woocommerce_before_add_to_cart_button'); ?>

    <div class="wc-mnm-container wc-mnm-layout-<?php echo esc_attr($display_layout); ?>">

        <!-- Container header with rules -->
        <div class="wc-mnm-container-header">
            <h3 class="wc-mnm-container-title">
                <?php echo esc_html($container->get_container_size_string()); ?>
            </h3>
        </div>

        <!-- Progress bar for max quantity -->
        <?php if ($max_qty > 0): ?>
            <div class="wc-mnm-progress">
                <div class="wc-mnm-progress-bar">
                    <div class="wc-mnm-progress-fill" id="wc-mnm-progress-fill" style="width: 0%"></div>
                </div>
                <div class="wc-mnm-progress-text">
                    <span>
                        <?php esc_html_e('0 items', 'wc-mix-and-match'); ?>
                    </span>
                    <span>
                        <?php echo sprintf(__('%d maximum', 'wc-mix-and-match'), $max_qty); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Price Display Section -->
        <div class="wc-mnm-price-display-section">
            <?php if ('fixed' === $pricing_mode): ?>
                <!-- Fixed Price Display -->
                <div class="wc-mnm-fixed-price-display">
                    <div class="wc-mnm-price-label">
                        <?php esc_html_e('Total Price:', 'wc-mix-and-match'); ?>
                    </div>
                    <div class="wc-mnm-price-amount">
                        <?php echo wp_kses_post(wc_price($container->get_fixed_price())); ?>
                    </div>
                </div>

            <?php elseif ('base_addon' === $pricing_mode): ?>
                <!-- Base + Add-ons Price Display -->
                <div class="wc-mnm-base-price-display">
                    <div class="wc-mnm-price-breakdown">
                        <!-- Base Price -->
                        <div class="wc-mnm-price-row wc-mnm-base-price-row">
                            <span class="wc-mnm-price-label">
                                <?php esc_html_e('Base Container:', 'wc-mix-and-match'); ?>
                            </span>
                            <span class="wc-mnm-price-amount wc-mnm-base-price">
                                <?php echo wp_kses_post(wc_price($base_price)); ?>
                            </span>
                        </div>

                        <!-- Add-ons Price -->
                        <div class="wc-mnm-price-row wc-mnm-addons-price-row">
                            <span class="wc-mnm-price-label">
                                <?php esc_html_e('Add-ons Total:', 'wc-mix-and-match'); ?>
                            </span>
                            <span class="wc-mnm-price-amount wc-mnm-addons-price" id="wc-mnm-addons-price">
                                <?php echo wp_kses_post(wc_price(0)); ?>
                            </span>
                        </div>

                        <!-- Total Price -->
                        <div class="wc-mnm-price-row wc-mnm-total-price-row">
                            <span class="wc-mnm-price-label">
                                <strong>
                                    <?php esc_html_e('Total:', 'wc-mix-and-match'); ?>
                                </strong>
                            </span>
                            <span class="wc-mnm-price-amount wc-mnm-total-price" id="wc-mnm-total-price">
                                <?php echo wp_kses_post(wc_price($base_price)); ?>
                            </span>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Per Item Price Display -->
                <div class="wc-mnm-per-item-price-display">
                    <div class="wc-mnm-price-label">
                        <?php esc_html_e('Total Price:', 'wc-mix-and-match'); ?>
                    </div>
                    <div class="wc-mnm-price-amount wc-mnm-total-price" id="wc-mnm-total-price">
                        <?php echo wp_kses_post(wc_price(0)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Child Products Grid -->
        <div class="wc-mnm-child-products">
            <?php if (empty($child_products)): ?>
                <p class="wc-mnm-no-products">
                    <?php esc_html_e('No products available for selection.', 'wc-mix-and-match'); ?>
                </p>
            <?php else: ?>
                <?php foreach ($child_products as $child_product):
                    $child_id = $child_product->get_id();
                    $is_in_stock = $child_product->is_in_stock();
                    $image_id = $child_product->get_image_id();
                    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();
                    $child_price = $child_product->get_price();
                    $child_price_html = $child_product->get_price_html();
                    ?>
                    <div class="wc-mnm-child-product" data-child-id="<?php echo esc_attr($child_id); ?>">

                        <!-- Product Image -->
                        <div class="wc-mnm-child-image">
                            <img src="<?php echo esc_url($image_url); ?>"
                                alt="<?php echo esc_attr($child_product->get_name()); ?>" loading="lazy" />
                        </div>

                        <!-- Product Content -->
                        <div class="wc-mnm-child-content">
                            <h4 class="wc-mnm-child-title">
                                <?php echo esc_html($child_product->get_name()); ?>
                            </h4>

                            <?php if ('per_item' === $pricing_mode || 'base_addon' === $pricing_mode): ?>
                                <div class="wc-mnm-child-price">
                                    <?php echo wp_kses_post($child_price_html); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Stock Status -->
                        <?php if (!$is_in_stock): ?>
                            <p class="wc-mnm-child-out-of-stock stock out-of-stock">
                                <?php esc_html_e('Out of stock', 'wc-mix-and-match'); ?>
                            </p>
                        <?php else: ?>
                            <!-- Quantity Selector -->
                            <div class="wc-mnm-quantity">
                                <div class="wc-mnm-quantity-selector">
                                    <button type="button" class="wc-mnm-quantity-btn decrease"
                                        data-target="<?php echo esc_attr($child_id); ?>"
                                        aria-label="<?php esc_attr_e('Decrease quantity', 'wc-mix-and-match'); ?>">
                                        &minus;
                                    </button>

                                    <input type="number" id="wc-mnm-quantity-<?php echo esc_attr($child_id); ?>"
                                        class="wc-mnm-quantity-input" name="wc_mnm_quantity[<?php echo esc_attr($child_id); ?>]"
                                        value="0" min="0" <?php if ($max_qty > 0): ?>
                                        max="
                        <?php echo esc_attr($max_qty); ?>"
                                    <?php endif; ?>
                                    data-price="
                        <?php echo esc_attr($child_price); ?>"
                                    aria-label="
                        <?php echo esc_attr(sprintf(__('Quantity for %s', 'wc-mix-and-match'), $child_product->get_name())); ?>"
                                    />

                                    <button type="button" class="wc-mnm-quantity-btn increase"
                                        data-target="<?php echo esc_attr($child_id); ?>"
                                        aria-label="<?php esc_attr_e('Increase quantity', 'wc-mix-and-match'); ?>">
                                        &plus;
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Selection Summary -->
        <div class="wc-mnm-selection-summary">
            <div class="wc-mnm-summary-grid">
                <div class="wc-mnm-summary-item">
                    <span class="wc-mnm-summary-label">
                        <?php esc_html_e('Items Selected:', 'wc-mix-and-match'); ?>
                    </span>
                    <span class="wc-mnm-summary-value" id="wc-mnm-items-count">
                        <?php
                        if ($max_qty > 0) {
                            echo sprintf(__('%d/%d', 'wc-mix-and-match'), 0, $max_qty);
                        } else {
                            echo esc_html('0');
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Description/Note - minimal messaging -->
        <div class="wc-mnm-description" id="wc-mnm-description">
            <?php if ($min_qty > 0): ?>
                <?php echo sprintf(__('Select %d items to continue', 'wc-mix-and-match'), $min_qty); ?>
            <?php else: ?>
                <?php esc_html_e('Select your items', 'wc-mix-and-match'); ?>
            <?php endif; ?>
        </div>

        <!-- Add to Cart Section -->
        <div class="wc-mnm-add-to-cart">
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />

            <?php
            // Display quantity selector for container
            if (!$product->is_sold_individually()):
                woocommerce_quantity_input([
                    'min_value' => 1,
                    'max_value' => $product->get_max_purchase_quantity(),
                    'input_value' => 1,
                    'classes' => ['wc-mnm-container-qty']
                ]);
            endif;
            ?>

            <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>"
                class="single_add_to_cart_button button alt disabled" disabled>
                <?php echo esc_html($product->single_add_to_cart_text()); ?>
            </button>

            <!-- Price Summary on Button (for mobile) -->
            <?php if ('fixed' !== $pricing_mode): ?>
                <div class="wc-mnm-mobile-price-summary">
                    <?php if ('base_addon' === $pricing_mode): ?>
                        <span class="wc-mnm-mobile-base-price">
                            <?php echo wp_kses_post(wc_price($base_price)); ?>
                        </span>
                        <span class="wc-mnm-mobile-plus">+</span>
                    <?php endif; ?>
                    <span class="wc-mnm-mobile-total-price" id="wc-mnm-mobile-total-price">
                        <?php
                        if ('base_addon' === $pricing_mode) {
                            echo wp_kses_post(wc_price($base_price));
                        } else {
                            echo wp_kses_post(wc_price(0));
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php do_action('woocommerce_after_add_to_cart_button'); ?>
</form>

<?php do_action('woocommerce_after_add_to_cart_form'); ?>