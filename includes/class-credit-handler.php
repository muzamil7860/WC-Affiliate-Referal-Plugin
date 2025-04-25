<?php
if (!defined('ABSPATH')) exit;

class WRC_Credit_Handler {
    public static function init() {
        add_action('woocommerce_thankyou', [__CLASS__, 'grant_store_credit']);
        add_action('comment_post', [__CLASS__, 'grant_credit_for_review'], 10, 3);
    }

    public static function grant_store_credit($order_id) {
        $order = wc_get_order($order_id);
        $buyer_id = $order->get_user_id();
        if (!$buyer_id) return;
    
        $total_spent = floatval($order->get_total());
        $credit_to_add = round($total_spent * 0.02, 2); // 2% credit for the buyer
    
        // Grant store credit to the buyer
        $current_credit = floatval(get_user_meta($buyer_id, 'store_credit', true)) ?: 0;
        update_user_meta($buyer_id, 'store_credit', $current_credit + $credit_to_add);
    
        // Get referrer ID (from session or user meta)
        $referrer_id = get_user_meta($buyer_id, 'wrc_referrer_used', true);
    
        if (!$referrer_id && isset($_COOKIE['wrc_referrer'])) {
            $referrer_id = intval($_COOKIE['wrc_referrer']);
            update_user_meta($buyer_id, 'wrc_referrer_used', $referrer_id);
        }
    
        // If a referrer exists
        if ($referrer_id) {
            $referrer_credit = floatval(get_user_meta($referrer_id, 'store_credit', true)) ?: 0;
            $referral_bonus = round($total_spent * 0.02, 2); // 2% credit for referrer
    
            // Log the credit status before the deduction (debugging)
            error_log("Referrer ID: $referrer_id, Current Credit: $referrer_credit, Referral Bonus: $referral_bonus");
    
            // Check if referrer has enough credit to deduct
            if ($referrer_credit >= $referral_bonus) {
                // Deduct the referral bonus from the referrer's store credit
                update_user_meta($referrer_id, 'store_credit', $referrer_credit - $referral_bonus);
    
                // Log the updated credit (debugging)
                $new_referrer_credit = floatval(get_user_meta($referrer_id, 'store_credit', true));
                error_log("Updated Referrer Credit: $new_referrer_credit");
    
                // Send email to the referrer about the earned credit
                $referrer_email = get_userdata($referrer_id)->user_email;
                $subject = "🎉 You've Earned Store Credit!";
                $message = "<p>Someone used your referral link and made a purchase! You've earned <strong>\$$referral_bonus</strong> in store credit.</p>";
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                wp_mail($referrer_email, $subject, $message, $headers);
            } else {
                // Deduct the available credit if referrer doesn't have enough
                update_user_meta($referrer_id, 'store_credit', 0);
    
                // Log insufficient credit situation (debugging)
                error_log("Insufficient credit for Referrer ID: $referrer_id. Current Credit: $referrer_credit");
    
                // Send email to the referrer notifying them about insufficient credit
                $referrer_email = get_userdata($referrer_id)->user_email;
                $subject = "🔔 Referral Credit Deduction Alert";
                $message = "<p>Your referral bonus could not be processed because you do not have enough store credit. The remaining bonus has been deducted from your account.</p>";
                wp_mail($referrer_email, $subject, $message, $headers);
            }
    
            // Send email to the buyer about their earned store credit
            $subject = "🎉 You've Earned Store Credit!";
            $message = "<p>Thank you for your purchase! You've earned <strong>\$$credit_to_add</strong> in store credit.</p>";
            wp_mail($order->get_billing_email(), $subject, $message, $headers);
        } else {
            // Log if no referrer is found (debugging)
            error_log("No referrer found for Buyer ID: $buyer_id.");
        }
    }



    public static function grant_credit_for_review($comment_ID, $comment_approved, $comment_data) {
        if ($comment_approved != 1) return; // Only grant credit for approved reviews

    $user_id = $comment_data['user_id'];
    if (!$user_id) return;

    // Ensure the comment is a WooCommerce product review
    $comment = get_comment($comment_ID);
    $product_id = $comment->comment_post_ID;
    if (get_post_type($product_id) !== 'product') return;

    // Define the store credit amount for each review
    $credit_to_add = 3.00; 

    $current_credit = floatval(get_user_meta($user_id, 'store_credit', true)) ?: 0;
    update_user_meta($user_id, 'store_credit', $current_credit + $credit_to_add);

    // Send an email to notify the user
    $subject = "🎉 You've Earned Store Credit for Your Review!";
    $message = "<p>Thank you for reviewing our product! You've earned <strong>\$$credit_to_add</strong> in store credit.</p>";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    wp_mail(get_userdata($user_id)->user_email, $subject, $message, $headers);
    }
}