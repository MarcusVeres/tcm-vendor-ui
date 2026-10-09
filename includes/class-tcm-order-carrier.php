<?php
/**
 * TCM Order Carrier
 * Copies the customer's store number and carrier details onto the order at checkout,
 * and displays them on order emails, the admin order screen and My Account > View Order.
 *
 * Details are stored on the order (not read live from the profile)
 * so past orders keep the values they were placed with.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TCM_Order_Carrier {

    private $main_plugin;
    private $customer_fields;

    /**
     * Order meta keys
     */
    const META_STORE_NUMBER   = '_tcm_store_number';
    const META_CARRIER_NAME   = '_tcm_carrier_name';
    const META_CARRIER_NUMBER = '_tcm_carrier_number';

    /**
     * Marks orders placed after this feature was installed.
     * Older orders have no details, so the section is hidden for them
     * rather than showing "NOT SET" for everything.
     */
    const META_SAVED = '_tcm_order_details_saved';

    public function __construct($main_plugin, $customer_fields) {
        $this->main_plugin = $main_plugin;
        $this->customer_fields = $customer_fields;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Copy details onto the order (classic checkout and block checkout)
        add_action('woocommerce_checkout_create_order', array($this, 'save_details_to_order'), 10, 1);
        add_action('woocommerce_store_api_checkout_update_order_meta', array($this, 'save_details_to_block_order'), 10, 1);

        // Sample values for WooCommerce > Settings > Emails preview
        add_filter('woocommerce_email_preview_dummy_order', array($this, 'add_preview_details'), 10, 1);

        // Display
        add_action('woocommerce_email_order_meta', array($this, 'display_in_email'), 20, 3);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_in_admin'), 10, 1);
        add_action('woocommerce_order_details_after_customer_details', array($this, 'display_in_account'), 10, 1);

        // Shipping is billed on the invoice, so show "TBD" instead of WooCommerce's "Free!" for $0 shipping
        add_filter('woocommerce_get_order_item_totals', array($this, 'show_shipping_tbd_on_order'), 10, 2);
        add_filter('woocommerce_cart_shipping_total', array($this, 'show_shipping_tbd_in_cart'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'show_shipping_tbd_in_blocks'), 20);
    }

    /**
     * Block cart/checkout: their "Free" label is rendered in JavaScript,
     * so replace the text through WordPress's i18n filter (cart and checkout pages only)
     */
    public function show_shipping_tbd_in_blocks() {
        if (!function_exists('is_cart') || (!is_cart() && !is_checkout())) {
            return;
        }

        wp_enqueue_script('wp-hooks');
        wp_add_inline_script(
            'wp-hooks',
            'wp.hooks.addFilter("i18n.gettext_woocommerce", "tcm-vendor-ui/shipping-tbd", function(translation, text) {' .
                'return (text === "Free" || text === "Free!") ? ' . wp_json_encode(__('TBD', 'tcm-vendor-ui')) . ' : translation;' .
            '});'
        );
    }

    /**
     * Order totals (emails, order received page, My Account > View Order)
     * WooCommerce's email template replaces the shipping value with "Free!"
     * when it equals the method name; setting it to "TBD" prevents that.
     *
     * @param array    $total_rows
     * @param WC_Order $order
     * @return array
     */
    public function show_shipping_tbd_on_order($total_rows, $order) {
        if (isset($total_rows['shipping']) && is_a($order, 'WC_Order') && (float) $order->get_shipping_total() == 0) {
            $total_rows['shipping']['value'] = __('TBD', 'tcm-vendor-ui');
        }

        return $total_rows;
    }

    /**
     * Cart shipping total (classic cart/checkout templates)
     *
     * @param string  $total
     * @param WC_Cart $cart
     * @return string
     */
    public function show_shipping_tbd_in_cart($total, $cart) {
        if (is_a($cart, 'WC_Cart') && (float) $cart->get_shipping_total() == 0) {
            return __('TBD', 'tcm-vendor-ui');
        }

        return $total;
    }

    /**
     * Copy the customer's details onto the order
     * Called before the order is saved (classic checkout)
     *
     * @param WC_Order $order
     */
    public function save_details_to_order($order) {
        $this->save_details($order, $this->customer_fields->get_user_order_details($order->get_customer_id()));
    }

    /**
     * Store details on the order (does not save the order)
     *
     * @param WC_Order $order
     * @param array    $details Keys: store_number, carrier_name (already resolved from "Other"), carrier_number
     */
    public function save_details($order, $details) {
        $order->update_meta_data(self::META_STORE_NUMBER, $details['store_number']);
        $order->update_meta_data(self::META_CARRIER_NAME, $details['carrier_name']);
        $order->update_meta_data(self::META_CARRIER_NUMBER, $details['carrier_number']);
        $order->update_meta_data(self::META_SAVED, '1');
    }

    /**
     * Block checkout: same as above, then save explicitly
     *
     * @param WC_Order $order
     */
    public function save_details_to_block_order($order) {
        $this->save_details_to_order($order);
        $order->save();
    }

    /**
     * Add sample details to the email preview's dummy order
     * Carrier number is left blank to show how "NOT SET" looks
     *
     * @param WC_Order $order
     * @return WC_Order
     */
    public function add_preview_details($order) {
        if (is_a($order, 'WC_Order')) {
            $order->update_meta_data(self::META_STORE_NUMBER, '1234');
            $order->update_meta_data(self::META_CARRIER_NAME, 'Purolator');
            $order->update_meta_data(self::META_CARRIER_NUMBER, '');
            $order->update_meta_data(self::META_SAVED, '1');
        }
        return $order;
    }

    /**
     * Get details stored on an order
     *
     * @param WC_Order $order
     * @return array Label => value ("NOT SET" for blanks), or empty array for orders placed before this feature
     */
    private function get_order_detail_rows($order) {
        if (!is_a($order, 'WC_Order') || $order->get_meta(self::META_SAVED) !== '1') {
            return array();
        }

        return $this->build_rows(array(
            'store_number'   => $order->get_meta(self::META_STORE_NUMBER),
            'carrier_name'   => $order->get_meta(self::META_CARRIER_NAME),
            'carrier_number' => $order->get_meta(self::META_CARRIER_NUMBER),
        ));
    }

    /**
     * Turn raw details into display rows
     *
     * @param array $details Keys: store_number, carrier_name, carrier_number
     * @return array Label => value ("NOT SET" for blanks)
     */
    private function build_rows($details) {
        $rows = array(
            __('Store Number', 'tcm-vendor-ui')           => isset($details['store_number']) ? $details['store_number'] : '',
            __('Carrier', 'tcm-vendor-ui')                => isset($details['carrier_name']) ? $details['carrier_name'] : '',
            __('Carrier Account Number', 'tcm-vendor-ui') => isset($details['carrier_number']) ? $details['carrier_number'] : '',
        );

        foreach ($rows as $label => $value) {
            if (!is_string($value) || trim($value) === '') {
                $rows[$label] = __('NOT SET', 'tcm-vendor-ui');
            }
        }

        return $rows;
    }

    /**
     * Print rows as label: value lines
     *
     * @param array $rows Label => value
     */
    private function print_rows($rows) {
        foreach ($rows as $label => $value) : ?>
            <strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?><br>
        <?php endforeach;
    }

    /**
     * Order emails (admin New Order + customer emails)
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     */
    public function display_in_email($order, $sent_to_admin = false, $plain_text = false) {
        $rows = $this->get_order_detail_rows($order);
        if (empty($rows)) {
            return;
        }

        if ($plain_text) {
            echo "\n" . esc_html(strtoupper(__('Store & Shipping', 'tcm-vendor-ui'))) . "\n";
            foreach ($rows as $label => $value) {
                echo esc_html($label) . ': ' . esc_html($value) . "\n";
            }
            echo "\n";
            return;
        }
        ?>
        <h2><?php esc_html_e('Store & Shipping', 'tcm-vendor-ui'); ?></h2>
        <p>
            <?php foreach ($rows as $label => $value) : ?>
                <strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?><br>
            <?php endforeach; ?>
        </p>
        <?php
    }

    /**
     * Admin order screen, below the shipping address
     * Shows two sections: details saved when the order was placed,
     * and the customer's current profile values (which may have changed since)
     *
     * @param WC_Order $order
     */
    public function display_in_admin($order) {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        // Section 1: saved at checkout (all NOT SET for orders placed before this feature)
        $order_rows = $this->get_order_detail_rows($order);
        $recorded   = !empty($order_rows);
        if (!$recorded) {
            $order_rows = $this->build_rows(array());
        }

        // Section 2: customer's current profile
        $customer_id  = $order->get_customer_id();
        $profile_rows = $customer_id ? $this->build_rows($this->customer_fields->get_user_order_details($customer_id)) : array();
        $profile_link = $customer_id ? get_edit_user_link($customer_id) : '';
        ?>
        <div class="tcm-order-carrier">
            <h3><?php esc_html_e('Store & Shipping (when order was placed)', 'tcm-vendor-ui'); ?></h3>
            <p>
                <?php $this->print_rows($order_rows); ?>
                <?php if (!$recorded) : ?>
                    <em><?php esc_html_e('Not recorded: this order was placed before these details were saved at checkout.', 'tcm-vendor-ui'); ?></em>
                <?php endif; ?>
            </p>

            <h3><?php esc_html_e("Store & Shipping (from user's current profile)", 'tcm-vendor-ui'); ?></h3>
            <p>
                <?php if ($customer_id) : ?>
                    <?php $this->print_rows($profile_rows); ?>
                    <?php if ($profile_link) : ?>
                        <a href="<?php echo esc_url($profile_link); ?>"><?php esc_html_e('View customer profile →', 'tcm-vendor-ui'); ?></a>
                    <?php endif; ?>
                <?php else : ?>
                    <em><?php esc_html_e('Guest order: no customer profile.', 'tcm-vendor-ui'); ?></em>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /**
     * My Account > View Order (and classic order received page)
     *
     * @param WC_Order $order
     */
    public function display_in_account($order) {
        $rows = $this->get_order_detail_rows($order);
        if (empty($rows)) {
            return;
        }
        ?>
        <section class="tcm-order-carrier">
            <h2><?php esc_html_e('Store & Shipping', 'tcm-vendor-ui'); ?></h2>
            <p>
                <?php foreach ($rows as $label => $value) : ?>
                    <strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?><br>
                <?php endforeach; ?>
            </p>
        </section>
        <?php
    }
}
