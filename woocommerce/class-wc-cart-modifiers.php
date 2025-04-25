<?php
if (!defined('ABSPATH')) exit;

class WRC_WC_Cart_Modifiers {
    public static function init() {
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_store_credit']);
        // add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_referral_discount'], 10);
    }

    public static function apply_store_credit($cart) {
        if (is_admin()) return;

        $user_id = get_current_user_id();
        if (!$user_id) return;
    
        $store_credit = floatval(get_user_meta($user_id, 'store_credit', true)) ?: 0;
        
        if ($store_credit > 0) {
            $discount = min($store_credit, $cart->subtotal);
            $cart->add_fee(__('Store Credit Used', 'woo-referral-credit'), -$discount, true);
            WC()->session->set('wrc_store_credit_used', $discount);
        }
    }

    // public static function apply_referral_discount($cart) {
    //     if (is_admin() || !isset($_COOKIE['wrc_referrer'])) return;

    //     $referrer_id = intval($_COOKIE['wrc_referrer']);
    //     $referrer_credit = floatval(get_user_meta($referrer_id, 'store_credit', true)) ?: 0;
        
    //     if ($referrer_credit > 0) {
    //         $discount = min($referrer_credit, $cart->subtotal);
    //         $cart->add_fee(__('Referral Discount Applied', 'woo-referral-credit'), -$discount, true);
    //         WC()->session->set('wrc_referral_discount_used', $discount);
    //         WC()->session->set('wrc_referrer_id', $referrer_id);
    //     }
    // }
}