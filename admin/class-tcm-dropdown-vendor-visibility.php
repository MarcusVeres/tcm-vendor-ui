<?php
/**
 * TCM Dropdown Vendor Visibility Admin
 * Admin interface for controlling which product types each vendor can see
 */

class TCM_Dropdown_Vendor_Visibility {

    private $main_plugin;
    private $dropdown_settings;

    public function __construct($main_plugin, $dropdown_settings) {
        $this->main_plugin = $main_plugin;
        $this->dropdown_settings = $dropdown_settings;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array('TCM_Vendor_Admin', 'enqueue_dropdown_admin_assets'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Top-level menu
        add_menu_page(
            __('TCM Dropdown Navigator', 'tcm-vendor-ui'),
            __('TCM Dropdown Navigator', 'tcm-vendor-ui'),
            'manage_options',
            'tcm-dropdown-navigator',
            array($this, 'render_admin_page'),
            'dashicons-arrow-down-alt2',
            30
        );

        // Vendor Visibility submenu (same slug as parent to replace first item)
        add_submenu_page(
            'tcm-dropdown-navigator',
            __('Vendor Visibility', 'tcm-vendor-ui'),
            __('Vendor Visibility', 'tcm-vendor-ui'),
            'manage_options',
            'tcm-dropdown-navigator',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tcm_dropdown_vendor_visibility', 'tcm_dropdown_vendor_visibility', array(
            'sanitize_callback' => array($this, 'sanitize_visibility_settings')
        ));
    }

    /**
     * Sanitize visibility settings
     */
    public function sanitize_visibility_settings($input) {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        $vendors = $this->dropdown_settings->get_vendor_slugs();
        $categories = $this->dropdown_settings->get_category_settings();

        foreach ($vendors as $vendor_slug) {
            $sanitized[$vendor_slug] = array();
            foreach ($categories as $category_slug => $category_data) {
                // Checkbox: if set, it's checked (true), otherwise false
                $sanitized[$vendor_slug][$category_slug] = isset($input[$vendor_slug][$category_slug]);
            }
        }

        return $sanitized;
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current settings
        $vendors = $this->dropdown_settings->get_vendor_slugs();
        $categories = $this->dropdown_settings->get_category_settings();
        $visibility = $this->dropdown_settings->get_vendor_visibility();

        // Get vendor names from vendor styles
        $vendor_styles = get_option('tcm_vendor_styles', array());

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('tcm_dropdown_vendor_visibility-options')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Vendor visibility settings saved successfully!', 'tcm-vendor-ui') . '</p></div>';
        }

        ?>
        <div class="wrap tcm-dropdown-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p><?php _e('Control which product types each vendor can see in the category dropdown navigator. Checked boxes mean the vendor can see that product type.', 'tcm-vendor-ui'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('tcm_dropdown_vendor_visibility');
                do_settings_sections('tcm_dropdown_vendor_visibility');
                ?>

                <div class="tcm-dropdown-table-wrapper">
                    <table class="wp-list-table widefat fixed striped tcm-dropdown-table">
                        <thead>
                            <tr>
                                <th class="column-vendor"><?php _e('Vendor', 'tcm-vendor-ui'); ?></th>
                                <?php foreach ($categories as $category_slug => $category_data): ?>
                                    <th class="column-category">
                                        <?php echo esc_html($category_data['label']); ?>
                                        <div class="category-slug"><?php echo esc_html($category_slug); ?></div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors as $vendor_slug): ?>
                                <tr>
                                    <!-- Vendor column (frozen) -->
                                    <td class="column-vendor">
                                        <strong>
                                            <?php
                                            // Get vendor name from vendor styles, fallback to slug
                                            $vendor_name = isset($vendor_styles[$vendor_slug]['name'])
                                                ? $vendor_styles[$vendor_slug]['name']
                                                : ucwords(str_replace('-', ' ', $vendor_slug));
                                            echo esc_html($vendor_name);
                                            ?>
                                        </strong>
                                        <div class="vendor-slug"><?php echo esc_html($vendor_slug); ?></div>
                                    </td>

                                    <!-- Category checkboxes -->
                                    <?php foreach ($categories as $category_slug => $category_data): ?>
                                        <td class="column-category">
                                            <input
                                                type="checkbox"
                                                name="tcm_dropdown_vendor_visibility[<?php echo esc_attr($vendor_slug); ?>][<?php echo esc_attr($category_slug); ?>]"
                                                value="1"
                                                <?php checked(
                                                    isset($visibility[$vendor_slug][$category_slug]) && $visibility[$vendor_slug][$category_slug]
                                                ); ?>
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="submit">
                    <?php submit_button(__('Save Vendor Visibility', 'tcm-vendor-ui'), 'primary', 'submit', false); ?>
                </p>
            </form>

            <div class="tcm-dropdown-info">
                <h2><?php _e('How It Works', 'tcm-vendor-ui'); ?></h2>
                <ul>
                    <li><?php _e('Check the boxes for product types that each vendor should see in the dropdown navigator.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('Unchecked boxes mean that product type will be hidden from that vendor.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('The vendor column stays visible when scrolling horizontally (frozen column).', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('By default, all vendors can see all product types.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('To customize the product type labels and ordering, use the "Category Settings" page.', 'tcm-vendor-ui'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
