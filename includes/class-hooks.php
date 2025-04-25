<?php
if (!defined('ABSPATH')) exit;

class WRC_Hooks {
    public static function init() {
        add_filter('wp_mail_from', [__CLASS__, 'sender_email']);
        add_filter('wp_mail_from_name', [__CLASS__, 'sender_name']);
        add_action('woocommerce_before_my_account', [__CLASS__, 'show_store_credit_in_account']);
        add_action('woocommerce_account_dashboard', [__CLASS__, 'display_referral_link_on_account'], 15);
        add_action('woocommerce_before_my_account', [__CLASS__, 'show_account_referral_info']);
    }

    public static function sender_email($original_email_address) {
        return 'pachamanacacao@gmail.com';
    }

    public static function sender_name($original_email_from) {
        return 'Pacha Mana Cacao';
    }

    public static function show_store_credit_in_account() {
        $user_id = get_current_user_id();
        $credit = floatval(get_user_meta($user_id, 'store_credit', true)) ?: 0;
        $referral_link = get_user_meta($user_id, 'referral_link', true);
    
        echo '<p><strong>' . __('Your Store Credit:', 'woo-referral-credit') . '</strong> $' . number_format($credit, 2) . '</p>';
    
        if (!empty($referral_link)) {
            echo '<p><strong>' . __('Your Referral Link:', 'woo-referral-credit') . '</strong> <a href="' . esc_url($referral_link) . '">' . esc_html($referral_link) . '</a></p>';
        }
    
        $referrer_id = get_user_meta($user_id, 'wrc_referrer_used', true);
        if ($referrer_id) {
            $referrer_name = get_userdata($referrer_id)->display_name;
            // echo '<p><strong>' . __('Referred by:', 'woo-referral-credit') . '</strong> ' . esc_html($referrer_name) . '</p>';
        }
    }

    public static function display_referral_link_on_account() {
        $user_id = get_current_user_id(); // Get the current logged-in user's ID

    if ($user_id) {
        // Generate the referral link
        $referral_link = home_url() . '/?ref=' . $user_id;
        
        // Display the referral link
        echo '<h3>Your Referral Link</h3>';
        echo '<p>Share this link with your friends and earn store credits when they make a purchase: </p>';
        echo '<input type="text" value="' . esc_url($referral_link) . '" readonly style="width: 100%; padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px;" />';
    }
    }

    public static function show_account_referral_info() {
        if (!is_user_logged_in()) return;
    
        $referrer = get_user_meta(get_current_user_id(), 'wrc_referrer_used', true);
        if ($referrer) {
            echo '<p><strong>Referred By:</strong> ' . esc_html($referrer) . '</p>';
        }
    
        $credit = (float) get_user_meta(get_current_user_id(), 'store_credit', true);
        if ($credit > 0) {
            // echo '<p><strong>Your Store Credit:</strong> $' . number_format($credit, 2) . '</p>';
        }
    }
}