<?php
/**
 * Plugin Name: WooCommerce Referral & Store Credit
 * Description: Grants 2% store credit on purchases, manages referrals, deducts used credits properly, and ensures email notifications.
 * Version: 1.0.0
 * Author: Muzamil Attiq
 * Text Domain: woo-referral-credit
 */

if (!defined('ABSPATH')) exit; // Prevent direct access
// Grant store credit & send referral email

function wrc_grant_store_credit($order_id) {
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

add_action('woocommerce_thankyou', 'wrc_grant_store_credit');

function wrc_deduct_referral_credit_after_order($order_id) {
    $order = wc_get_order($order_id);
    $buyer_id = $order->get_user_id();
    if (!$buyer_id) return;

    // Deduct store credit for the buyer if used
    $store_credit_used = WC()->session->get('wrc_store_credit_used', 0);
    if ($store_credit_used > 0) {
        $current_credit = floatval(get_user_meta($buyer_id, 'store_credit', true)) ?: 0;
        update_user_meta($buyer_id, 'store_credit', max(0, $current_credit - $store_credit_used));
    }

    // Deduct referral bonus from the referrer (if applicable)
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
        update_user_meta($buyer_id, 'wrc_referrer_used', $referrer_id);
    }

    // Clear session data after completing the order
    WC()->session->__unset('wrc_store_credit_used');
    WC()->session->__unset('wrc_referral_discount_used');
    WC()->session->__unset('wrc_referrer_id');

    // Clear the referrer cookie
    setcookie('wrc_referrer', '', time() - 3600, '/', COOKIE_DOMAIN);
}

add_action('woocommerce_thankyou', 'wrc_deduct_referral_credit_after_order');


// Track referral visits (even for guest users)
function wrc_track_referral() {
    if (!empty($_GET['ref']) && is_numeric($_GET['ref'])) {
        $referrer_id = intval($_GET['ref']);
        // Sanitize the referrer ID before saving it in cookies
        setcookie('wrc_referrer', $referrer_id, time() + MONTH_IN_SECONDS, '/', COOKIE_DOMAIN, is_ssl(), true);
        error_log("Referral ID set in cookie: $referrer_id"); // Debugging line
    }
}
add_action('init', 'wrc_track_referral');


// Assign referral link upon user registration
function wrc_assign_referral_link_on_registration($user_id) {
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
add_action('user_register', 'wrc_assign_referral_link_on_registration');

// // Apply referral credit as a discount
// function wrc_apply_referral_discount($cart) {
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
// add_action('woocommerce_cart_calculate_fees', 'wrc_apply_referral_discount');


// Apply store credit
function wrc_apply_store_credit($cart) {
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
add_action('woocommerce_cart_calculate_fees', 'wrc_apply_store_credit');

function wrc_deduct_store_credit_after_order($order_id) {
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
add_action('woocommerce_thankyou', 'wrc_deduct_store_credit_after_order');



// Show credit balance in My Account
function wrc_show_store_credit_in_account() {
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

add_action('woocommerce_before_my_account', 'wrc_show_store_credit_in_account');

// Show referrer on checkout
function wrc_show_referrer_on_checkout() {
    $referrer_id = WC()->session->get('wrc_referrer_id');
    if ($referrer_id) {
        $referrer_name = get_userdata($referrer_id)->display_name;
        echo '<p><strong>' . __('Referral Discount From:', 'woo-referral-credit') . '</strong> ' . esc_html($referrer_name) . '</p>';
    }
}
add_action('woocommerce_review_order_before_submit', 'wrc_show_referrer_on_checkout');

// Generate referral link for new users
add_action('woocommerce_created_customer', 'generate_referral_link_for_new_user', 10, 1);
function generate_referral_link_for_new_user($customer_id) {
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

// Grant store credit when a review is submitted
function wrc_grant_credit_for_review($comment_ID, $comment_approved, $comment_data) {
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

// Wordpress email

add_filter( 'wp_mail_from', 'sender_email' );
function sender_email( $original_email_address ) {
    return 'pachamanacacao@gmail.com';
}

// Code to change your sender name:
add_filter( 'wp_mail_from_name', 'sender_name' );
function sender_name( $original_email_from ) {
    return 'Pacha Mana Cacao';
}

add_action('comment_post', 'wrc_grant_credit_for_review', 10, 3);

// // Apply store credit to existing users.
// function wrc_apply_credit_to_existing_users() {
//     $args = [
//         'role'    => 'customer',
//         'orderby' => 'ID',
//         'order'   => 'ASC',
//         'fields'  => 'ID'
//     ];

//     $customers = get_users($args);

//     foreach ($customers as $user_id) {
//         $total_spent = wc_get_customer_total_spent($user_id);
//         if ($total_spent > 0) {
//             $credit_to_add = round($total_spent * 0.02, 2); // 2% of total spent
//             update_user_meta($user_id, 'store_credit', $credit_to_add);
//         }

//         // Generate and store referral link
//         $referral_link = esc_url(site_url('/?ref=' . $user_id));
//         update_user_meta($user_id, 'referral_link', $referral_link);
//     }
// }

// // Run this function once via admin panel
// add_action('admin_init', 'wrc_apply_credit_to_existing_users');


// Function to display the referral link on My Account page
function display_referral_link_on_account() {
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
add_action('woocommerce_account_dashboard', 'display_referral_link_on_account', 15);
//  Store referrer ID in session and cookie
add_action('init', function () {
    if (isset($_GET['ref'])) {
        $referrer_id = absint($_GET['ref']);

        if (!is_user_logged_in() || $referrer_id !== get_current_user_id()) {
            WC()->session->set('referrer_id', $referrer_id);
            setcookie('wrc_referrer', $referrer_id, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }
    }
});

// Apply 10% referral discount if first time
add_action('woocommerce_cart_calculate_fees', function ($cart) {
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
}, 10);

// Give store credit to referrer & mark ref usage + email
add_action('woocommerce_checkout_create_order', function ($order, $data) {
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
}, 10, 2);

//  Show Referred By in admin panel
add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
    $referrer = $order->get_meta('_referrer_name');
    if ($referrer) {
        echo '<p><strong>' . __('Referred By') . ':</strong> ' . esc_html($referrer) . '</p>';
    }
});

// Show Referred By + Store Credit in My Account
add_action('woocommerce_before_my_account', function () {
    if (!is_user_logged_in()) return;

    $referrer = get_user_meta(get_current_user_id(), 'wrc_referrer_used', true);
    if ($referrer) {
        echo '<p><strong>Referred By:</strong> ' . esc_html($referrer) . '</p>';
    }

    $credit = (float) get_user_meta(get_current_user_id(), 'store_credit', true);
    if ($credit > 0) {
        // echo '<p><strong>Your Store Credit:</strong> $' . number_format($credit, 2) . '</p>';
    }
});

// Automatically apply store credit on checkout
add_action('woocommerce_cart_calculate_fees', function ($cart) {
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
}, 20);

// After order is placed, reduce used store credit
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    if (!is_user_logged_in()) return;

    $user_id = get_current_user_id();
    $used_credit = WC()->session->get('store_credit_used');
    if ($used_credit) {
        $existing = (float) get_user_meta($user_id, 'store_credit', true);
        $new = max(0, $existing - $used_credit);
        update_user_meta($user_id, 'store_credit', $new);
        WC()->session->__unset('store_credit_used');
    }
}, 20);
