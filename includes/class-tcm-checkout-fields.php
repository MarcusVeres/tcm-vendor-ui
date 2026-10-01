<?php
/**
 * TCM Checkout Fields
 * Adds required Store Number and Carrier fields to the block checkout
 * (shown in the "Additional order information" block).
 *
 * - Pre-filled from the customer's profile (ACF user fields)
 * - Values submitted at checkout are saved back to the profile
 *   and stored on the order (see TCM_Order_Carrier)
 *
 * Uses WooCommerce's Additional Checkout Fields API (block checkout only).
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TCM_Checkout_Fields {

    private $main_plugin;
    private $customer_fields;
    private $order_carrier;

    /**
     * Checkout field IDs (namespace/field)
     */
    const FIELD_STORE_NUMBER   = 'tcm/store-number';
    const FIELD_CARRIER_NAME   = 'tcm/carrier-name';
    const FIELD_CARRIER_CUSTOM = 'tcm/carrier-name-custom';
    const FIELD_CARRIER_NUMBER = 'tcm/carrier-number';

    /**
     * WooCommerce stores "order" location field values on the order under this meta prefix
     */
    const ORDER_META_PREFIX = '_wc_other/';

    /**
     * Contact details shown on the checkout page
     */
    const CONTACT_PHONE = '1.888.473.3629';
    const CONTACT_EMAIL = 'tcmservice@instorecorp.com';

    public function __construct($main_plugin, $customer_fields, $order_carrier) {
        $this->main_plugin = $main_plugin;
        $this->customer_fields = $customer_fields;
        $this->order_carrier = $order_carrier;

        // Requires WooCommerce's checkout fields API and ACF (for carrier choices and profile values)
        if (!function_exists('woocommerce_register_additional_checkout_field') || !function_exists('acf_get_field')) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WooCommerce recommends registering on woocommerce_init; it has usually already fired by now
        if (did_action('woocommerce_init')) {
            $this->register_fields();
        } else {
            add_action('woocommerce_init', array($this, 'register_fields'));
        }

        // Pre-fill from profile
        foreach (array_keys($this->get_field_map()) as $field_id) {
            add_filter('woocommerce_get_default_value_for_' . $field_id, array($this, 'default_value'), 10, 3);
        }

        // "Other" requires a carrier name
        add_action('woocommerce_blocks_validate_location_order_fields', array($this, 'validate_order_fields'), 10, 3);

        // Save submitted values to the profile and the order (runs before payment and emails)
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'save_checkout_values'), 20, 1);

        // Our read-only "Store & Shipping" sections replace WooCommerce's editable copy on the admin order screen
        add_filter('woocommerce_admin_shipping_fields', array($this, 'remove_from_admin_fields'), 20, 1);

        // Contact message above the checkout form
        add_filter('render_block_woocommerce/checkout', array($this, 'add_help_message'), 10, 1);
    }

    /**
     * Checkout field ID => ACF profile field name
     */
    private function get_field_map() {
        return array(
            self::FIELD_STORE_NUMBER   => 'store_number',
            self::FIELD_CARRIER_NAME   => 'carrier_name',
            self::FIELD_CARRIER_CUSTOM => 'carrier_name_custom',
            self::FIELD_CARRIER_NUMBER => 'carrier_number',
        );
    }

    /**
     * Register the checkout fields
     * show_in_order_confirmation is off because TCM_Order_Carrier already
     * shows these details on emails and the order pages
     */
    public function register_fields() {
        $carrier_options = array();
        foreach ($this->customer_fields->get_carrier_choices() as $value => $label) {
            $carrier_options[] = array(
                'value' => (string) $value,
                'label' => (string) $label,
            );
        }

        woocommerce_register_additional_checkout_field(array(
            'id'                         => self::FIELD_STORE_NUMBER,
            'label'                      => __('Store Number', 'tcm-vendor-ui'),
            'location'                   => 'order',
            'type'                       => 'text',
            'required'                   => true,
            'show_in_order_confirmation' => false,
        ));

        woocommerce_register_additional_checkout_field(array(
            'id'                         => self::FIELD_CARRIER_NAME,
            'label'                      => __('Carrier', 'tcm-vendor-ui'),
            'location'                   => 'order',
            'type'                       => 'select',
            'options'                    => $carrier_options,
            'required'                   => true,
            'show_in_order_confirmation' => false,
        ));

        // Always visible: conditional show/hide needs WooCommerce's experimental features,
        // so "required when Other" is enforced in validate_order_fields() instead
        woocommerce_register_additional_checkout_field(array(
            'id'                         => self::FIELD_CARRIER_CUSTOM,
            'label'                      => __('Carrier Name (only if Other)', 'tcm-vendor-ui'),
            'optionalLabel'              => __('Carrier Name (only if Other)', 'tcm-vendor-ui'),
            'location'                   => 'order',
            'type'                       => 'text',
            'required'                   => false,
            'show_in_order_confirmation' => false,
        ));

        woocommerce_register_additional_checkout_field(array(
            'id'                         => self::FIELD_CARRIER_NUMBER,
            'label'                      => __('Carrier Account Number', 'tcm-vendor-ui'),
            'location'                   => 'order',
            'type'                       => 'text',
            'required'                   => true,
            'show_in_order_confirmation' => false,
        ));
    }

    /**
     * Default a field to the customer's profile value
     * Only called by WooCommerce when the field has no value yet
     *
     * @param mixed   $value     Always null
     * @param string  $group
     * @param WC_Data $wc_object Customer or order being read
     * @return mixed
     */
    public function default_value($value, $group, $wc_object) {
        $field_id = substr(current_filter(), strlen('woocommerce_get_default_value_for_'));
        $map = $this->get_field_map();
        if (!isset($map[$field_id])) {
            return $value;
        }

        // Use the object's customer, so the right profile is read wherever this runs
        $user_id = 0;
        if (is_a($wc_object, 'WC_Order')) {
            $user_id = $wc_object->get_customer_id();
        } elseif (is_a($wc_object, 'WC_Customer')) {
            $user_id = $wc_object->get_id();
        }
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            return $value;
        }

        $profile = $this->customer_fields->get_user_profile_values($user_id);
        $profile_value = $profile[$map[$field_id]];

        // Don't pre-select a carrier that isn't in the list (e.g. a typo from the import)
        if ($field_id === self::FIELD_CARRIER_NAME && !array_key_exists($profile_value, $this->customer_fields->get_carrier_choices())) {
            return $value;
        }

        return $profile_value !== '' ? $profile_value : $value;
    }

    /**
     * Require a carrier name when "Other (Please Specify)" is selected
     *
     * @param WP_Error $errors
     * @param array    $fields Field ID => submitted value
     * @param string   $group
     */
    public function validate_order_fields($errors, $fields, $group) {
        $carrier = isset($fields[self::FIELD_CARRIER_NAME]) ? $fields[self::FIELD_CARRIER_NAME] : '';
        $custom  = isset($fields[self::FIELD_CARRIER_CUSTOM]) ? trim((string) $fields[self::FIELD_CARRIER_CUSTOM]) : '';

        if ($carrier === TCM_Customer_Fields::CARRIER_OTHER && $custom === '') {
            $errors->add(
                'tcm_carrier_name_custom_required',
                __('You chose "Other (Please Specify)" as your carrier. Please enter the carrier name.', 'tcm-vendor-ui')
            );
        }
    }

    /**
     * Save submitted values to the customer's profile and onto the order
     *
     * @param WC_Order $order
     */
    public function save_checkout_values($order) {
        if (!is_a($order, 'WC_Order')) {
            return;
        }

        $values = array();
        foreach ($this->get_field_map() as $field_id => $name) {
            $value = $order->get_meta(self::ORDER_META_PREFIX . $field_id);
            $values[$name] = is_string($value) ? trim($value) : '';
        }

        // Fields weren't on this checkout (e.g. checkout block not used); keep the profile copy
        if ($values['store_number'] === '' && $values['carrier_name'] === '' && $values['carrier_number'] === '') {
            return;
        }

        // Custom carrier name only applies when "Other" is selected
        $is_other = ($values['carrier_name'] === TCM_Customer_Fields::CARRIER_OTHER);
        if (!$is_other) {
            $values['carrier_name_custom'] = '';
        }

        // Update the profile (also changes it if the customer edited a pre-filled value)
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $this->customer_fields->save_user_profile_values($customer_id, $values);
        }

        // Store what was submitted on the order ("when order was placed")
        $this->order_carrier->save_details($order, array(
            'store_number'   => $values['store_number'],
            'carrier_name'   => $is_other ? $values['carrier_name_custom'] : $values['carrier_name'],
            'carrier_number' => $values['carrier_number'],
        ));
        $order->save();
    }

    /**
     * Remove WooCommerce's editable copy of our fields from the admin order screen
     *
     * @param array $fields
     * @return array
     */
    public function remove_from_admin_fields($fields) {
        if (!is_array($fields)) {
            return $fields;
        }

        foreach (array_keys($this->get_field_map()) as $field_id) {
            unset($fields[$field_id]);
        }

        return $fields;
    }

    /**
     * Show a contact message above the checkout form
     *
     * @param string $block_content
     * @return string
     */
    public function add_help_message($block_content) {
        $phone_link = 'tel:' . preg_replace('/[^0-9]/', '', self::CONTACT_PHONE);

        $message = sprintf(
            '<p class="tcm-checkout-help">%1$s <a href="%2$s">%3$s</a> %4$s <a href="mailto:%5$s">%5$s</a></p>',
            esc_html__('Having trouble placing your order? Contact us at', 'tcm-vendor-ui'),
            esc_url($phone_link),
            esc_html(self::CONTACT_PHONE),
            esc_html__('or', 'tcm-vendor-ui'),
            esc_attr(self::CONTACT_EMAIL)
        );

        return $message . $block_content;
    }
}
