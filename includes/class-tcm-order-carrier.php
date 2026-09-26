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
    }

    /**
     * Copy the customer's details onto the order
     * Called before the order is saved (classic checkout)
     *
     * @param WC_Order $order
     */
    public function save_details_to_order($order) {
        $details = $this->customer_fields->get_user_order_details($order->get_customer_id());

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

        $rows = array(
            __('Store Number', 'tcm-vendor-ui')           => $order->get_meta(self::META_STORE_NUMBER),
            __('Carrier', 'tcm-vendor-ui')                => $order->get_meta(self::META_CARRIER_NAME),
            __('Carrier Account Number', 'tcm-vendor-ui') => $order->get_meta(self::META_CARRIER_NUMBER),
        );

        foreach ($rows as $label => $value) {
            if (!is_string($value) || trim($value) === '') {
                $rows[$label] = __('NOT SET', 'tcm-vendor-ui');
            }
        }

        return $rows;
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
     *
     * @param WC_Order $order
     */
    public function display_in_admin($order) {
        $rows = $this->get_order_detail_rows($order);
        if (empty($rows)) {
            return;
        }
        ?>
        <div class="tcm-order-carrier">
            <h3><?php esc_html_e('Store & Shipping', 'tcm-vendor-ui'); ?></h3>
            <p>
                <?php foreach ($rows as $label => $value) : ?>
                    <strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($value); ?><br>
                <?php endforeach; ?>
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
