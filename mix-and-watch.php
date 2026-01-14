<?php
/*
  Plugin Name: Mix & Wlatch Products for WooCommerce
  Plugin URI:  https://inshalytics.com/
  Description: Allow your customers to combine products and create their own variations, and increase your average order value
  Version:     1.0.0
  Author:      Inshalytics
  Author URI:  https://inshalytics.com
*/


if (!defined('ABSPATH')) {
    exit;
}


define('WC_MNM_VERSION', '0.1.0');
define('WC_MNM_PLUGIN_FILE', __FILE__);
define('WC_MNM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_MNM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load core class.
require_once WC_MNM_PLUGIN_DIR . 'includes/class-wc-mnm.php';


/**
 * Initialize the plugin once all plugins are loaded.
 */
function wc_mnm_init_plugin()
{
  // Only run if WooCommerce is active.
  if (!class_exists('WooCommerce')) {
    return;
  }

  $plugin = new WC_MNM();
  $plugin->init();
}
add_action('plugins_loaded', 'wc_mnm_init_plugin');

?>