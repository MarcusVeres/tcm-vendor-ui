<?php
/**
 * Cart Type Category Meta Fields
 * Adds custom fields to Cart Type categories in WooCommerce
 */

class TCM_Cart_Type_Meta {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add meta fields to category edit screen
        add_action('product_cat_edit_form_fields', array($this, 'edit_category_fields'), 10, 1);

        // Save meta data
        add_action('edited_product_cat', array($this, 'save_category_fields'), 10, 1);
    }

    /**
     * Add meta fields to category edit screen
     */
    public function edit_category_fields($term) {
        // Only show for Cart Type categories
        if (!$this->is_cart_type_category($term->term_id)) {
            return;
        }

        // Get existing values
        $order = get_term_meta($term->term_id, 'tcm_category_order', true);
        $enable_consultation = get_term_meta($term->term_id, 'tcm_enable_consultation', true);
        $enable_maintenance = get_term_meta($term->term_id, 'tcm_enable_maintenance', true);
        $enable_fleet = get_term_meta($term->term_id, 'tcm_enable_fleet_management', true);

        ?>
        <tr class="form-field tcm-cart-type-meta-section">
            <td colspan="2">
                <h2 style="padding: 0;">TCM Dropdown Navigator Settings</h2>
                <p class="description">These settings control how this category appears in the dropdown navigator.</p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="tcm_category_order">Display Order</label>
            </th>
            <td>
                <input type="number" name="tcm_category_order" id="tcm_category_order"
                       value="<?php echo esc_attr($order); ?>" min="1" max="999" step="1"
                       style="width: 100px;">
                <p class="description">
                    Order in the dropdown (lower numbers appear first).
                    Leave empty to default to 999.
                </p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label>Service Options</label>
            </th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text">Service Options</legend>

                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="tcm_enable_consultation" id="tcm_enable_consultation"
                               value="1" <?php checked($enable_consultation, '1'); ?>>
                        <strong>Enable "Consultation"</strong>
                        <p class="description" style="margin: 5px 0 0 25px;">
                            Shows "Consultation" option in the second dropdown. Leave unchecked to use global default.
                        </p>
                    </label>

                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="tcm_enable_maintenance" id="tcm_enable_maintenance"
                               value="1" <?php checked($enable_maintenance, '1'); ?>>
                        <strong>Enable "Maintenance"</strong>
                        <p class="description" style="margin: 5px 0 0 25px;">
                            Shows "Maintenance" option in the second dropdown. Leave unchecked to use global default.
                        </p>
                    </label>

                    <label style="display: block; margin-bottom: 10px;">
                        <input type="checkbox" name="tcm_enable_fleet_management" id="tcm_enable_fleet_management"
                               value="1" <?php checked($enable_fleet, '1'); ?>>
                        <strong>Enable "Fleet Management"</strong>
                        <p class="description" style="margin: 5px 0 0 25px;">
                            Shows "Fleet Management" option in the second dropdown. Only appears when explicitly enabled.
                        </p>
                    </label>
                </fieldset>
                <p class="description" style="margin-top: 15px;">
                    Configure service labels and URLs in <a href="<?php echo admin_url('admin.php?page=tcm-dropdown-service-config'); ?>">Service Configuration</a>.
                </p>
            </td>
        </tr>

        <tr class="form-field tcm-cart-type-meta-section">
            <td colspan="2">
                <hr style="margin: 20px 0;">
            </td>
        </tr>
        <?php
    }

    /**
     * Save category meta fields
     */
    public function save_category_fields($term_id) {
        // Only save for Cart Type categories
        if (!$this->is_cart_type_category($term_id)) {
            return;
        }

        // Save category order
        if (isset($_POST['tcm_category_order'])) {
            $order = absint($_POST['tcm_category_order']);
            if ($order > 0) {
                update_term_meta($term_id, 'tcm_category_order', $order);
            } else {
                delete_term_meta($term_id, 'tcm_category_order');
            }
        }

        // Save consultation setting
        // Checked = '1' (enabled), Unchecked = delete meta (use global default)
        if (isset($_POST['tcm_enable_consultation']) && $_POST['tcm_enable_consultation'] === '1') {
            update_term_meta($term_id, 'tcm_enable_consultation', '1');
        } else {
            delete_term_meta($term_id, 'tcm_enable_consultation');
        }

        // Save maintenance setting
        // Checked = '1' (enabled), Unchecked = delete meta (use global default)
        if (isset($_POST['tcm_enable_maintenance']) && $_POST['tcm_enable_maintenance'] === '1') {
            update_term_meta($term_id, 'tcm_enable_maintenance', '1');
        } else {
            delete_term_meta($term_id, 'tcm_enable_maintenance');
        }

        // Save fleet management setting
        // Checked = '1' (enabled), Unchecked = delete meta (disabled - no global default)
        if (isset($_POST['tcm_enable_fleet_management']) && $_POST['tcm_enable_fleet_management'] === '1') {
            update_term_meta($term_id, 'tcm_enable_fleet_management', '1');
        } else {
            delete_term_meta($term_id, 'tcm_enable_fleet_management');
        }
    }

    /**
     * Check if a category is a child of "Cart Types"
     */
    private function is_cart_type_category($term_id) {
        $term = get_term($term_id, 'product_cat');

        if (!$term || is_wp_error($term)) {
            return false;
        }

        // Check if this term has a parent
        if ($term->parent === 0) {
            // Check if THIS is the cart-types parent
            return ($term->slug === 'cart-types');
        }

        // Get parent term
        $parent = get_term($term->parent, 'product_cat');

        if (!$parent || is_wp_error($parent)) {
            return false;
        }

        // Check if parent is "cart-types"
        return ($parent->slug === 'cart-types');
    }
}
