<?php
/**
 * TCM Dropdown Category Settings Admin
 * Admin interface for managing category labels and drag-and-drop ordering
 */

class TCM_Dropdown_Category_Settings {

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
        // DEPRECATED: Category Settings submenu removed
        // Category ordering and labels are now managed via WooCommerce category meta fields
        // See: admin/class-tcm-cart-type-meta.php for meta field implementation

        /* add_submenu_page(
            'tcm-dropdown-navigator',
            __('Category Settings', 'tcm-vendor-ui'),
            __('Category Settings', 'tcm-vendor-ui'),
            'manage_options',
            'tcm-dropdown-category-settings',
            array($this, 'render_admin_page')
        ); */
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tcm_dropdown_category_settings', 'tcm_dropdown_categories', array(
            'sanitize_callback' => array($this, 'sanitize_category_settings')
        ));
    }

    /**
     * Sanitize category settings
     */
    public function sanitize_category_settings($input) {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        foreach ($input as $slug => $settings) {
            $sanitized[$slug] = array(
                'slug' => sanitize_key($settings['slug']),
                'label' => sanitize_text_field($settings['label']),
                'order' => intval($settings['order']),
            );
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
        $categories = $this->dropdown_settings->get_category_settings();

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('tcm_dropdown_category_settings-options')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Category settings saved successfully!', 'tcm-vendor-ui') . '</p></div>';
        }

        ?>
        <div class="wrap tcm-dropdown-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p><?php _e('Customize the product type labels and drag to reorder them. The order here determines the order in the dropdown navigator.', 'tcm-vendor-ui'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('tcm_dropdown_category_settings');
                do_settings_sections('tcm_dropdown_category_settings');
                ?>

                <table class="wp-list-table widefat fixed striped tcm-category-settings-table">
                    <thead>
                        <tr>
                            <th class="column-drag"><?php _e('Order', 'tcm-vendor-ui'); ?></th>
                            <th class="column-slug"><?php _e('Slug', 'tcm-vendor-ui'); ?></th>
                            <th class="column-label"><?php _e('Display Label', 'tcm-vendor-ui'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tcm-sortable-categories">
                        <?php foreach ($categories as $slug => $settings): ?>
                            <tr data-slug="<?php echo esc_attr($slug); ?>">
                                <!-- Drag handle -->
                                <td class="column-drag">
                                    <span class="dashicons dashicons-menu drag-handle"></span>
                                    <input
                                        type="hidden"
                                        name="tcm_dropdown_categories[<?php echo esc_attr($slug); ?>][order]"
                                        value="<?php echo esc_attr($settings['order']); ?>"
                                        class="category-order-input"
                                    >
                                    <input
                                        type="hidden"
                                        name="tcm_dropdown_categories[<?php echo esc_attr($slug); ?>][slug]"
                                        value="<?php echo esc_attr($slug); ?>"
                                    >
                                </td>

                                <!-- Slug (read-only) -->
                                <td class="column-slug">
                                    <code><?php echo esc_html($slug); ?></code>
                                </td>

                                <!-- Label (editable) -->
                                <td class="column-label">
                                    <input
                                        type="text"
                                        name="tcm_dropdown_categories[<?php echo esc_attr($slug); ?>][label]"
                                        value="<?php echo esc_attr($settings['label']); ?>"
                                        class="regular-text"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <?php submit_button(__('Save Category Settings', 'tcm-vendor-ui'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary tcm-reset-category-defaults" style="margin-left: 10px;">
                        <?php _e('Reset to Defaults', 'tcm-vendor-ui'); ?>
                    </button>
                </p>
            </form>

            <div class="tcm-dropdown-info">
                <h2><?php _e('How It Works', 'tcm-vendor-ui'); ?></h2>
                <ul>
                    <li><?php _e('Drag rows using the drag handle to reorder product types.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('The display label is what users see in the dropdown navigator.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('The slug is the internal identifier and cannot be changed.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('Changes take effect immediately after saving.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('To control which vendors see which categories, use the "Vendor Visibility" page.', 'tcm-vendor-ui'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
