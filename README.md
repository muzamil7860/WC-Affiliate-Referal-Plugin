# WC-Affiliate-Referal-Plugin
WooCommerce Referral & Store Credit Plugin
Contributors: Muzamil Attiq
Tags: WooCommerce, store credit, referral, rewards, loyalty
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: woo-referral-credit

📝 Description
The WooCommerce Referral & Store Credit plugin introduces a powerful loyalty and referral system for your WooCommerce store. It rewards both buyers and their referrers with store credit, enabling a robust incentive structure that boosts customer retention and word-of-mouth marketing.

Key Features
🔁 Referral System: Customers get a unique referral link to share with others.

💸 Store Credit Rewards:

Buyers receive 2% store credit after each purchase.

Referrers also receive 2% store credit when someone they referred makes a purchase.

📧 Email Notifications:

Buyers and referrers are notified of store credit earnings and deductions.

🛍️ Store Credit Discounts: Store credit is automatically applied as a discount during checkout.

⭐ Product Review Incentives: Customers earn store credit for submitting approved reviews.

👀 My Account Enhancements: Customers can view their current store credit and referral link.

🚀 Installation
Download the plugin ZIP file.

Upload it via Plugins > Add New > Upload Plugin in the WordPress dashboard.

Activate the plugin through the ‘Plugins’ menu.

No configuration needed—store credit and referral tracking start working automatically!

🛠️ How It Works
🎁 Earning Store Credit
On Purchase: Customers automatically earn 2% of their order total as store credit.

Via Referral:

When a new customer visits using a referral link (e.g., yoursite.com/?ref=ref_123), a referral cookie is set.

If the referred customer makes a purchase, the referrer earns 2% store credit (deducted from their balance).

For Reviews: Approved product reviews earn customers a fixed credit (default: $3).

💳 Using Store Credit
Store credit is applied automatically during checkout (up to the available balance).

Credit is shown as a discount in the order summary.

📬 Email Notifications
Emails are sent for:

Earning credit (buyer & referrer).

Insufficient credit (referrer).

Review credit earned.

Referral link upon registration.

👤 User Experience
My Account
Customers see:

Their current store credit.

Their unique referral link.

Checkout Page
If referred, the checkout page shows who referred the customer.

🔐 Data Stored Per User

Meta Key	Description
store_credit	Current store credit balance
referral_link	User's referral URL
_referral_code	Internal referral code
wrc_referrer_used	Referrer's user ID (if applicable)
⚙️ Developer Notes
Referral links are formatted as: https://yoursite.com/?ref=ref_123.

Store credit is saved as user meta and calculated using get_user_meta().

Debugging information is logged via error_log() to help track deductions or missing referrals.

Session values (like used credit and referral info) are cleared after checkout.

💌 Email Customization
Sender email: pachamanacacao@gmail.com
Sender name: Pacha Mana Cacao

To modify, edit the filters:

php
Copy
Edit
add_filter( 'wp_mail_from', 'sender_email' );
add_filter( 'wp_mail_from_name', 'sender_name' );
🧪 Planned Enhancements
Admin dashboard for managing credit balances.

Option to customize referral reward percentages.

Ability to disable auto-apply of credit at checkout.

Shortcodes for referral link display.

## Demo Video 

# First Step 
https://www.loom.com/share/d2377c41ec3649c8b7fc62b9e0ebf671?sid=b11e3814-7c4e-4720-b505-7e9922f86c8b

# Second Step
https://www.loom.com/share/bf4f811a53b643209ba96e4fc13a3a03?sid=9602076b-02d7-439e-9f2e-7d5df61c2fa1

❓ FAQs
Q: Can users stack referral and store credit?
A: Currently, referral-based discounts are applied from the referrer’s credit pool. Buyers use their own store credit at checkout.

Q: What if the referrer doesn’t have enough credit?
A: The plugin deducts what’s available and emails the referrer about the shortfall.

Q: Do referral cookies work for guests?
A: Yes. Cookies track referrals even if the user registers later.

🧑‍💻 Contributing
Pull requests and improvements are welcome! For major changes, please open an issue first to discuss what you would like to change.

📄 License
This plugin is open-source software licensed under the GPLv2 or later.
