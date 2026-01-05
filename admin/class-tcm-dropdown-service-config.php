<?php
/**
 * TCM Dropdown Service Configuration
 * Admin interface for configuring global service settings (Consultation, Maintenance, Fleet Management)
 */

class TCM_Dropdown_Service_Config {

    private $main_plugin;
    private $dropdown_settings;

    public function __construct($main_plugin, $dropdown_settings) {
        $this->main_plugin = $main_plugin;
        $this->dropdown_settings = $dropdown_settings;

        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_post_tcm_save_service_config', array($this, 'save_service_config'));
    }

    /**
     * Add admin page under TCM Dropdown Navigator menu
     */
    public function add_admin_page() {
        add_submenu_page(
            'tcm-dropdown-navigator',
            'Service Configuration',
            'Service Configuration',
            'manage_options',
            'tcm-dropdown-service-config',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Get default service configuration
     */
    private function get_default_services() {
        return array(
            'consultation' => array(
                'label' => 'Consultation',
                'url' => '/contact/',
                'enabled_by_default' => true
            ),
            'maintenance' => array(
                'label' => 'Maintenance',
                'url' => '/maintenance/',
                'enabled_by_default' => true
            ),
            'fleet-management' => array(
                'label' => 'Fleet Management',
                'url' => '/fleet-management/',
                'enabled_by_default' => false // Only enabled when checkbox is checked
            )
        );
    }

    /**
     * Get service configuration
     */
    private function get_service_config() {
        $saved = get_option('tcm_dropdown_services', false);

        if ($saved === false) {
            // First time - return defaults
            return $this->get_default_services();
        }

        // Merge with defaults to ensure all services exist
        $defaults = $this->get_default_services();
        return array_merge($defaults, $saved);
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        $services = $this->get_service_config();
        $updated = isset($_GET['updated']) && $_GET['updated'] === 'true';

        ?>
        <div class="wrap">
            <h1>Dropdown Navigator - Service Configuration</h1>

            <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                This page configures the service options that appear in the second dropdown of the navigator
                (Consultation, Maintenance, and Fleet Management). Set global labels and URLs here, then
                enable/disable services per cart type in the category editor.
            </p>

            <?php if ($updated): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Service configuration saved successfully!</strong></p>
                </div>
            <?php endif; ?>

            <div class="notice notice-info">
                <p><strong>How this works:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Configure global service settings here (labels and URLs are the same for all cart types)</li>
                    <li>Enable/disable services per cart type in <strong>Products → Categories</strong> (edit each cart type category)</li>
                    <li>Services with "Enabled by Default" will appear for all cart types unless explicitly disabled</li>
                    <li>Fleet Management requires the checkbox to be enabled in category settings</li>
                </ul>
            </div>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('tcm_save_service_config', 'tcm_service_config_nonce'); ?>
                <input type="hidden" name="action" value="tcm_save_service_config">

                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($services as $service_key => $service): ?>
                            <tr>
                                <th scope="row" style="padding-left: 0;">
                                    <h3 style="margin: 0;">
                                        <?php echo esc_html(ucwords(str_replace('-', ' ', $service_key))); ?>
                                    </h3>
                                </th>
                                <td style="padding-left: 20px;">
                                    <table class="widefat" style="max-width: 600px;">
                                        <tr>
                                            <th style="width: 150px; padding-left: 12px;">Label:</th>
                                            <td style="padding-left: 12px;">
                                                <input type="text"
                                                       name="services[<?php echo esc_attr($service_key); ?>][label]"
                                                       value="<?php echo esc_attr($service['label']); ?>"
                                                       class="regular-text"
                                                       required>
                                                <p class="description">Text shown in the dropdown</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="padding-left: 12px;">URL:</th>
                                            <td style="padding-left: 12px;">
                                                <input type="text"
                                                       name="services[<?php echo esc_attr($service_key); ?>][url]"
                                                       value="<?php echo esc_attr($service['url']); ?>"
                                                       class="regular-text"
                                                       placeholder="/page-slug/"
                                                       required>
                                                <p class="description">Relative URL (e.g., /contact/) or full URL</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th style="padding-left: 12px;">Default Visibility:</th>
                                            <td style="padding-left: 12px;">
                                                <label>
                                                    <input type="checkbox"
                                                           name="services[<?php echo esc_attr($service_key); ?>][enabled_by_default]"
                                                           value="1"
                                                           <?php checked(!empty($service['enabled_by_default'])); ?>>
                                                    Enabled by default for all cart types
                                                </label>
                                                <p class="description">
                                                    <?php if ($service_key === 'fleet-management'): ?>
                                                        Fleet Management requires per-category checkbox (uncheck this to hide from all)
                                                    <?php else: ?>
                                                        Shows for all cart types unless disabled in category settings
                                                    <?php endif; ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <?php submit_button('Save Service Configuration', 'primary', 'submit', false); ?>
                    <a href="<?php echo admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'); ?>"
                       class="button"
                       style="margin-left: 10px;">
                        Manage Categories
                    </a>
                </p>
            </form>

            <hr style="margin: 40px 0;">

            <h2>Service Visibility by Cart Type</h2>
            <p class="description">
                To enable/disable specific services for individual cart types, edit the category and check/uncheck the service checkboxes.
            </p>

            <?php
            $cart_types = $this->dropdown_settings->get_cart_type_categories();
            if (!empty($cart_types)):
            ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Cart Type</th>
                            <th style="text-align: center;">Consultation</th>
                            <th style="text-align: center;">Maintenance</th>
                            <th style="text-align: center;">Fleet Management</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_types as $cart_type): ?>
                            <tr>
                                <td><strong><?php echo esc_html($cart_type['label']); ?></strong></td>
                                <td style="text-align: center;">
                                    <?php
                                    $enabled = get_term_meta($cart_type['term_id'], 'tcm_enable_consultation', true);
                                    echo ($enabled === '1') ? '✅ Enabled' : (($enabled === '0') ? '❌ Disabled' : '🟢 Default');
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $enabled = get_term_meta($cart_type['term_id'], 'tcm_enable_maintenance', true);
                                    echo ($enabled === '1') ? '✅ Enabled' : (($enabled === '0') ? '❌ Disabled' : '🟢 Default');
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php
                                    $enabled = get_term_meta($cart_type['term_id'], 'tcm_enable_fleet_management', true);
                                    echo ($enabled === '1') ? '✅ Enabled' : '❌ Disabled';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('term.php?taxonomy=product_cat&tag_ID=' . $cart_type['term_id'] . '&post_type=product'); ?>"
                                       class="button button-small">
                                        Edit Category
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save service configuration
     */
    public function save_service_config() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Verify nonce
        if (!isset($_POST['tcm_service_config_nonce']) || !wp_verify_nonce($_POST['tcm_service_config_nonce'], 'tcm_save_service_config')) {
            wp_die('Security check failed');
        }

        $services = isset($_POST['services']) ? $_POST['services'] : array();
        $sanitized_services = array();

        foreach ($services as $service_key => $service_data) {
            $sanitized_services[$service_key] = array(
                'label' => sanitize_text_field($service_data['label']),
                'url' => esc_url_raw($service_data['url']),
                'enabled_by_default' => isset($service_data['enabled_by_default']) ? true : false
            );
        }

        // Save configuration
        update_option('tcm_dropdown_services', $sanitized_services);

        // Redirect back with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'tcm-dropdown-service-config',
                'updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}
