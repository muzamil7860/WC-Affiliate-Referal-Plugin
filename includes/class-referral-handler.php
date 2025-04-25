<?php
if (!defined('ABSPATH')) exit;

class WRC_Referral_Handler {
    public static function init() {
        add_action('init', [__CLASS__, 'track_referral']);
        add_action('user_register', [__CLASS__, 'assign_referral_link_on_registration']);
        add_action('woocommerce_created_customer', [__CLASS__, 'generate_referral_link_for_new_user']);
        add_action('init', [__CLASS__, 'track_referral_visit']);
    }

    public static function track_referral() {
        if (!empty($_GET['ref']) && is_numeric($_GET['ref'])) {
            $referrer_id = intval($_GET['ref']);
            // Sanitize the referrer ID before saving it in cookies
            setcookie('wrc_referrer', $referrer_id, time() + MONTH_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true);
            error_log("Referral ID set in cookie: $referrer_id"); // Debugging line
        }
    }

    public static function assign_referral_link_on_registration($user_id) {
           // Check if referral code already exists for this user to avoid duplicates
    $referral_code = get_user_meta($user_id, '_referral_code', true);
    if (!$referral_code) {
        $referral_code = 'ref_' . $user_id; // You can customize this format
        update_user_meta($user_id, '_referral_code', $referral_code);
    }

    // Generate the referral link
    $referral_link = esc_url(home_url('/?ref=' . $referral_code));
    update_user_meta($user_id, 'referral_link', $referral_link);

    // Check if this user was referred by someone
    if (!empty($_COOKIE['wrc_referrer'])) {
        $referrer_id = intval($_COOKIE['wrc_referrer']);
        update_user_meta($user_id, 'wrc_referrer_used', $referrer_id);
    }
    }

    public static function generate_referral_link_for_new_user($customer_id) {
        $referral_code = get_user_meta($customer_id, '_referral_code', true);
    
        if (!$referral_code) {
            $referral_code = 'ref_' . $customer_id; // Customize this code format
            update_user_meta($customer_id, '_referral_code', $referral_code);
        }
    
        // Referral link
        $referral_link = home_url() . '/?ref=' . $referral_code;
    
        // Save the referral link
        update_user_meta($customer_id, 'referral_link', $referral_link);
    
        // Send email
        $user = get_user_by('id', $customer_id);
        $subject = 'Welcome to Our Referral Program!';
        $message = 'Thanks for registering with us! Here is your unique referral link to start earning rewards: ' . $referral_link;
        
        if (!wp_mail($user->user_email, $subject, $message)) {
            error_log("Failed to send referral link email to user ID: $customer_id.");
        }
    }
    public static function track_referral_visit() {
        if (isset($_GET['ref'])) {
            $referrer_id = absint($_GET['ref']);
            if (!is_user_logged_in() || $referrer_id !== get_current_user_id()) {
                WC()->session->set('referrer_id', $referrer_id);
                setcookie('wrc_referrer', $referrer_id, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }
}