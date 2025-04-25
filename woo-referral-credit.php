<?php
/**
 * Plugin Name: WooCommerce Referral & Store Credit
 * Description: Grants 2% store credit on purchases, manages referrals, deducts used credits properly, and ensures email notifications.
 * Version: 1.1.0
 * Author: Muzamil Attiq
 * Text Domain: woo-referral-credit
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WRC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WRC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WRC_VERSION', '1.1.0');

// Include required files
require_once WRC_PLUGIN_PATH . 'includes/class-credit-handler.php';
require_once WRC_PLUGIN_PATH . 'includes/class-referral-handler.php';
require_once WRC_PLUGIN_PATH . 'includes/class-hooks.php';
require_once WRC_PLUGIN_PATH . 'includes/functions-helpers.php';
require_once WRC_PLUGIN_PATH . 'woocommerce/class-wc-hooks.php';
require_once WRC_PLUGIN_PATH . 'woocommerce/class-wc-cart-modifiers.php';
require_once WRC_PLUGIN_PATH . 'admin/class-admin-menu.php';

// Initialize the plugin
function wrc_init() {
    WRC_Credit_Handler::init();
    WRC_Referral_Handler::init();
    WRC_Hooks::init();
    WRC_WC_Hooks::init();
    WRC_WC_Cart_Modifiers::init();
    WRC_Admin_Menu::init();
}
add_action('plugins_loaded', 'wrc_init');

// Load text domain
function wrc_load_textdomain() {
    load_plugin_textdomain('woo-referral-credit', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('init', 'wrc_load_textdomain');