<?php
/**
 * TCM Customer Fields
 * Lets customers view and edit their ACF user fields (store number, carrier)
 * in My Account > Account Details.
 *
 * The fields themselves are defined in ACF (field group "ACF User Fields"),
 * which also renders them for administrators in Users > Edit User.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TCM_Customer_Fields {

    private $main_plugin;

    /**
     * Carrier choice that reveals the custom carrier name field
     * Must match the ACF choice exactly
     */
    const CARRIER_OTHER = 'Other (Please Specify)';

    /**
     * Fallback carrier list, used only if the ACF field definition can't be read
     */
    private $default_carriers = array(
        'Canada Post',
        'DHL',
        'FedEx',
        'Purolator',
        'United States Postal Service (USPS)',
        'UPS',
        self::CARRIER_OTHER,
    );

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;

        // ACF stores and reads these fields; do nothing without it
        if (!function_exists('update_field') || !function_exists('acf_get_field')) {
            return;
        }

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Priority 20 places our fields after B2BKing's registration fields
        add_action('woocommerce_edit_account_form', array($this, 'render_account_fields'), 20);
        add_action('woocommerce_save_account_details_errors', array($this, 'validate_account_fields'), 10, 1);
        add_action('woocommerce_save_account_details', array($this, 'save_account_fields'), 10, 1);
    }

    /**
     * Get carrier choices from the ACF field definition
     * Returns associative array: value => label
     */
    public function get_carrier_choices() {
        $field = acf_get_field('carrier_name');

        if (!empty($field['choices']) && is_array($field['choices'])) {
            return $field['choices'];
        }

        return array_combine($this->default_carriers, $this->default_carriers);
    }

    /**
     * Get a raw (unformatted) field value for a user
     */
    private function get_value($name, $user_id) {
        $value = get_field($name, 'user_' . $user_id, false);
        return is_string($value) ? $value : '';
    }

    /**
     * Get a user's store and carrier details for use elsewhere (e.g. orders)
     * "Other (Please Specify)" is resolved to the custom carrier name
     *
     * @param int $user_id
     * @return array Keys: store_number, carrier_name, carrier_number (empty strings if not set)
     */
    public function get_user_order_details($user_id) {
        $details = array(
            'store_number'   => '',
            'carrier_name'   => '',
            'carrier_number' => '',
        );

        if (!$user_id || !function_exists('get_field')) {
            return $details;
        }

        $carrier_name = $this->get_value('carrier_name', $user_id);
        if ($carrier_name === self::CARRIER_OTHER) {
            $carrier_name = $this->get_value('carrier_name_custom', $user_id);
        }

        $details['store_number']   = $this->get_value('store_number', $user_id);
        $details['carrier_name']   = $carrier_name;
        $details['carrier_number'] = $this->get_value('carrier_number', $user_id);

        return $details;
    }

    /**
     * Get a user's raw profile values (carrier is NOT resolved; "Other" stays "Other")
     *
     * @param int $user_id
     * @return array Keys: store_number, carrier_name, carrier_name_custom, carrier_number
     */
    public function get_user_profile_values($user_id) {
        $values = array(
            'store_number'        => '',
            'carrier_name'        => '',
            'carrier_name_custom' => '',
            'carrier_number'      => '',
        );

        if (!$user_id || !function_exists('get_field')) {
            return $values;
        }

        foreach (array_keys($values) as $name) {
            $values[$name] = $this->get_value($name, $user_id);
        }

        return $values;
    }

    /**
     * Save store and carrier values to a user's profile
     * Only keys present in $values are saved
     *
     * @param int   $user_id
     * @param array $values Keys: store_number, carrier_name, carrier_name_custom, carrier_number
     */
    public function save_user_profile_values($user_id, $values) {
        if (!$user_id || !function_exists('update_field')) {
            return;
        }

        foreach (array('store_number', 'carrier_name', 'carrier_name_custom', 'carrier_number') as $name) {
            if (array_key_exists($name, $values)) {
                $this->set_value($name, $values[$name], $user_id);
            }
        }
    }

    /**
     * Save a field value, using the ACF field key when available
     * so ACF's reference meta (_field_name => field_xxx) stays intact
     */
    private function set_value($name, $value, $user_id) {
        $field = acf_get_field($name);
        $selector = !empty($field['key']) ? $field['key'] : $name;
        update_field($selector, $value, 'user_' . $user_id);
    }

    /**
     * Render fields in My Account > Account Details
     */
    public function render_account_fields() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $store_number   = $this->get_value('store_number', $user_id);
        $carrier_name   = $this->get_value('carrier_name', $user_id);
        $carrier_custom = $this->get_value('carrier_name_custom', $user_id);
        $carrier_number = $this->get_value('carrier_number', $user_id);
        $is_other       = ($carrier_name === self::CARRIER_OTHER);

        // Keep an imported value that isn't in the ACF choices, so saving doesn't wipe it
        $choices = $this->get_carrier_choices();
        if ($carrier_name !== '' && !array_key_exists($carrier_name, $choices)) {
            $choices[$carrier_name] = $carrier_name;
        }
        ?>
        <fieldset class="tcm-customer-fields">
            <legend><?php esc_html_e('Store & Shipping', 'tcm-vendor-ui'); ?></legend>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="tcm_store_number"><?php esc_html_e('Store Number', 'tcm-vendor-ui'); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                       name="tcm_store_number" id="tcm_store_number"
                       value="<?php echo esc_attr($store_number); ?>">
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="tcm_carrier_name"><?php esc_html_e('Carrier', 'tcm-vendor-ui'); ?></label>
                <select name="tcm_carrier_name" id="tcm_carrier_name">
                    <option value=""><?php esc_html_e('Select a carrier', 'tcm-vendor-ui'); ?></option>
                    <?php foreach ($choices as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($carrier_name, (string) $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide"
               id="tcm_carrier_name_custom_row"<?php echo $is_other ? '' : ' style="display:none;"'; ?>>
                <label for="tcm_carrier_name_custom"><?php esc_html_e('Carrier Name', 'tcm-vendor-ui'); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                       name="tcm_carrier_name_custom" id="tcm_carrier_name_custom"
                       value="<?php echo esc_attr($carrier_custom); ?>">
            </p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="tcm_carrier_number"><?php esc_html_e('Carrier Account Number', 'tcm-vendor-ui'); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text"
                       name="tcm_carrier_number" id="tcm_carrier_number"
                       value="<?php echo esc_attr($carrier_number); ?>">
            </p>
        </fieldset>
        <div class="clear"></div>

        <script>
            (function() {
                var select = document.getElementById('tcm_carrier_name');
                var customRow = document.getElementById('tcm_carrier_name_custom_row');
                if (!select || !customRow) {
                    return;
                }
                select.addEventListener('change', function() {
                    customRow.style.display = (select.value === <?php echo wp_json_encode(self::CARRIER_OTHER); ?>) ? '' : 'none';
                });
            })();
        </script>
        <?php
    }

    /**
     * Validate submitted fields (all optional; only the carrier must be a known choice)
     *
     * @param WP_Error $errors
     */
    public function validate_account_fields($errors) {
        if (empty($_POST['tcm_carrier_name'])) {
            return;
        }

        $carrier = sanitize_text_field(wp_unslash($_POST['tcm_carrier_name']));

        // The user's existing (possibly imported) value is always allowed
        $current = $this->get_value('carrier_name', get_current_user_id());

        if ($carrier !== $current && !array_key_exists($carrier, $this->get_carrier_choices())) {
            $errors->add('tcm_carrier_name', __('Please choose a carrier from the list.', 'tcm-vendor-ui'));
        }
    }

    /**
     * Save submitted fields
     * WooCommerce verifies the account details nonce before this runs
     *
     * @param int $user_id
     */
    public function save_account_fields($user_id) {
        // Only save if our fields were part of the submitted form
        if (!isset($_POST['tcm_carrier_name'])) {
            return;
        }

        $store_number   = isset($_POST['tcm_store_number']) ? sanitize_text_field(wp_unslash($_POST['tcm_store_number'])) : '';
        $carrier_name   = sanitize_text_field(wp_unslash($_POST['tcm_carrier_name']));
        $carrier_custom = isset($_POST['tcm_carrier_name_custom']) ? sanitize_text_field(wp_unslash($_POST['tcm_carrier_name_custom'])) : '';
        $carrier_number = isset($_POST['tcm_carrier_number']) ? sanitize_text_field(wp_unslash($_POST['tcm_carrier_number'])) : '';

        // Custom carrier name only applies when "Other" is selected
        if ($carrier_name !== self::CARRIER_OTHER) {
            $carrier_custom = '';
        }

        $this->set_value('store_number', $store_number, $user_id);
        $this->set_value('carrier_name', $carrier_name, $user_id);
        $this->set_value('carrier_name_custom', $carrier_custom, $user_id);
        $this->set_value('carrier_number', $carrier_number, $user_id);
    }
}
