<?php
if (!defined('ABSPATH')) exit;

class WRC_WC_Hooks {
    public static function init() {
        add_action('woocommerce_thankyou', [__CLASS__, 'deduct_store_credit_after_order']);
        add_action('woocommerce_review_order_before_submit', [__CLASS__, 'show_referrer_on_checkout']);
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'handle_referral_order'], 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [__CLASS__, 'show_referred_by_in_admin']);
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'auto_apply_store_credit'], 20);
        add_action('woocommerce_checkout_order_processed', [__CLASS__, 'reduce_used_store_credit'], 20);
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'apply_first_time_referral_discount'], 10);


    }

    public static function deduct_store_credit_after_order($order_id) {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        if (!$user_id) return;
    
        // Deduct store credit for the buyer
        $store_credit_used = WC()->session->get('wrc_store_credit_used', 0);
        if ($store_credit_used > 0) {
            $current_credit = floatval(get_user_meta($user_id, 'store_credit', true)) ?: 0;
            update_user_meta($user_id, 'store_credit', max(0, $current_credit - $store_credit_used));
        }
    
        // Deduct referral bonus for the referrer (if applicable)
        $referrer_id = WC()->session->get('wrc_referrer_id');
        $ref_discount_used = WC()->session->get('wrc_referral_discount_used', 0);
        
        if ($referrer_id && $ref_discount_used > 0) {
            // Update referrer's credit after referral bonus deduction
            $referrer_credit = floatval(get_user_meta($referrer_id, 'store_credit', true)) ?: 0;
            $new_referrer_credit = max(0, $referrer_credit - $ref_discount_used);
            update_user_meta($referrer_id, 'store_credit', $new_referrer_credit);
    
            // Log the update for debugging
            error_log("Referrer ID: $referrer_id, Referrer Credit Deducted: $ref_discount_used, New Referrer Credit: $new_referrer_credit");
    
            // Update buyer's meta with referrer ID (if not already done)
            update_user_meta($user_id, 'wrc_referrer_used', $referrer_id);
        }
    
        // Clear session data after completing the order
        WC()->session->__unset('wrc_store_credit_used');
        WC()->session->__unset('wrc_referral_discount_used');
        WC()->session->__unset('wrc_referrer_id');
        
        // Clear the referrer cookie
        setcookie('wrc_referrer', '', time() - 3600, '/', COOKIE_DOMAIN);
    }

    public static function show_referrer_on_checkout() {
        $referrer_id = WC()->session->get('wrc_referrer_id');
        if ($referrer_id) {
            $referrer_name = get_userdata($referrer_id)->display_name;
            echo '<p><strong>' . __('Referral Discount From:', 'woo-referral-credit') . '</strong> ' . esc_html($referrer_name) . '</p>';
        }
    }

    public static function show_referred_by_in_admin($order) {
        $referrer = $order->get_meta('_referrer_name');
        if ($referrer) {
            echo '<p><strong>' . __('Referred By') . ':</strong> ' . esc_html($referrer) . '</p>';
        }
    }

    public static function handle_referral_order($order, $data) {
        $referrer_id = WC()->session->get('referrer_id') ?: (isset($_COOKIE['wrc_referrer']) ? absint($_COOKIE['wrc_referrer']) : null);
        if (!$referrer_id) return;
    
        $referrer_user = get_user_by('ID', $referrer_id);
    if ($referrer_user) {
        $referrer_name = $referrer_user->display_name;
        $referrer_email = $referrer_user->user_email;
        $order->update_meta_data('_referrer_name', $referrer_name);
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();

        if (!get_user_meta($user_id, '_used_referral_discount', true)) {
            update_user_meta($user_id, '_used_referral_discount', true);
            update_user_meta($user_id, 'wrc_referrer_used', $referrer_name ?? 'Unknown');

            //  Give 20% store credit to referrer
            $credit_amount = $order->get_subtotal() * 0.20;
            $existing_credit = (float) get_user_meta($referrer_id, 'store_credit', true);
            $new_credit = $existing_credit + $credit_amount;
            update_user_meta($referrer_id, 'store_credit', $new_credit);

            //  Email to Referrer (User A)
            if (!empty($referrer_email)) {
                $subject = '🎉 You earned $' . number_format($credit_amount, 2) . ' store credit!';
                $message = 'Hi ' . esc_html($referrer_name) . ",<br><br>"
                    . 'Good news! You earned $' . number_format($credit_amount, 2) . ' in store credit because <strong>' . esc_html($current_user->display_name) . '</strong> used your referral link and completed a purchase.<br><br>'
                    . 'You can use your credit on your next order!<br><br>'
                    . 'Thanks for spreading the word!<br>'
                    . get_bloginfo('name');

                wp_mail($referrer_email, $subject, $message, ['Content-Type: text/html']);
            }

            //  Email to User B
            $user_b_email = $current_user->user_email;
            $subject_b = '🎉 You saved 10% with your referral discount!';
            $message_b = 'Hi ' . esc_html($current_user->display_name) . ",<br><br>"
                . 'You just saved 10% on your purchase using a referral code from <strong>' . esc_html($referrer_name) . '</strong>.<br><br>'
                . 'Enjoy your savings and don’t forget you can now refer your own friends too!<br><br>'
                . 'Thanks for shopping with us!<br>'
                . get_bloginfo('name');

            wp_mail($user_b_email, $subject_b, $message_b, ['Content-Type: text/html']);
        }
    } else {
        WC()->session->set('guest_used_referral_discount', true);
    }

    WC()->session->__unset('referrer_id');
    }

    public static function apply_first_time_referral_discount($cart) {
        if (is_admin() || is_cart()) return;
        
        $referrer_id = WC()->session->get('referrer_id') ?: (isset($_COOKIE['wrc_referrer']) ? absint($_COOKIE['wrc_referrer']) : null);
        if (!$referrer_id) return;
    
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (get_user_meta($user_id, '_used_referral_discount', true)) return;
        } else {
            if (WC()->session->get('guest_used_referral_discount')) return;
        }
    
        $discount = $cart->get_subtotal() * 0.10;
        $cart->add_fee(__('Referral Discount', 'your-textdomain'), -$discount);
    }

    public static function auto_apply_store_credit($cart) {
        if (!is_user_logged_in()) return;

    $user_id = get_current_user_id();
    $credit = (float) get_user_meta($user_id, 'store_credit', true);
    if ($credit <= 0) return;

    $cart_total = $cart->get_subtotal();
    $apply_credit = min($credit, $cart_total);

    if ($apply_credit > 0) {
        $cart->add_fee(__('Store Credit', 'your-textdomain'), -$apply_credit);
        WC()->session->set('store_credit_used', $apply_credit);
    }
    }


    public static function reduce_used_store_credit($order_id) {
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $used_credit = WC()->session->get('store_credit_used');
        if ($used_credit) {
            $existing = (float) get_user_meta($user_id, 'store_credit', true);
            $new = max(0, $existing - $used_credit);
            update_user_meta($user_id, 'store_credit', $new);
            WC()->session->__unset('store_credit_used');
        }
    }

    }


