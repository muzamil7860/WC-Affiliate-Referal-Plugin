<?php
if (!defined('ABSPATH')) exit;

class WRC_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
    }

    public static function add_admin_menu() {
        // Add your admin menu code here if needed
    }
}