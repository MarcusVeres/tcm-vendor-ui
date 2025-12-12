<?php
/**
 * TCM Vendor Admin Module
 * Admin interface for managing vendor styles
 */

class TCM_Vendor_Admin {

    private $main_plugin;
    private $vendor_styles;

    public function __construct($main_plugin, $vendor_styles) {
        $this->main_plugin = $main_plugin;
        $this->vendor_styles = $vendor_styles;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('TCM Vendors', 'tcm-vendor-ui'),
            __('TCM Vendors', 'tcm-vendor-ui'),
            'manage_options',
            'tcm-vendors',
            array($this, 'render_admin_page'),
            'dashicons-cart',
            31
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tcm_vendor_settings', 'tcm_vendor_styles', array(
            'sanitize_callback' => array($this, 'sanitize_vendor_settings')
        ));
    }

    /**
     * Sanitize vendor settings
     */
    public function sanitize_vendor_settings($input) {
        $sanitized = array();

        if (!is_array($input)) {
            return $sanitized;
        }

        foreach ($input as $slug => $settings) {
            $sanitized[$slug] = array(
                'name' => sanitize_text_field($settings['name']),
                'logo_url' => esc_url_raw($settings['logo_url']),
                'bg_color' => sanitize_hex_color($settings['bg_color']),
                'text_color' => sanitize_hex_color($settings['text_color']),
                'button_bg' => sanitize_hex_color($settings['button_bg']),
                'button_text' => sanitize_hex_color($settings['button_text']),
            );
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our vendors page (now top-level menu)
        if ($hook !== 'toplevel_page_tcm-vendors') {
            return;
        }

        // WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // WordPress media uploader
        wp_enqueue_media();

        // Custom admin styles
        wp_enqueue_style(
            'tcm-vendor-admin',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TCM_VENDOR_UI_VERSION
        );

        // Custom admin script
        wp_enqueue_script(
            'tcm-vendor-admin',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            TCM_VENDOR_UI_VERSION,
            true
        );
    }

    /**
     * Enqueue dropdown admin assets
     * Used by dropdown admin pages
     */
    public static function enqueue_dropdown_admin_assets($hook) {
        // Only load on dropdown navigator pages
        if ($hook !== 'toplevel_page_tcm-dropdown-navigator' && $hook !== 'tcm-dropdown-navigator_page_tcm-dropdown-category-settings') {
            return;
        }

        // jQuery UI Sortable (for drag-and-drop)
        wp_enqueue_script('jquery-ui-sortable');

        // Dropdown admin styles
        wp_enqueue_style(
            'tcm-dropdown-admin',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/css/dropdown-admin.css',
            array(),
            TCM_VENDOR_UI_VERSION
        );

        // Admin script (reuse existing admin.js which now includes drag-drop)
        wp_enqueue_script(
            'tcm-vendor-admin',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            TCM_VENDOR_UI_VERSION,
            true
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current vendor settings
        $vendors = $this->vendor_styles->get_vendors();

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('tcm_vendor_settings-options')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'tcm-vendor-ui') . '</p></div>';
        }

        ?>
        <div class="wrap tcm-vendor-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p><?php _e('Customize the branding for each vendor group. Changes will be reflected on the frontend immediately.', 'tcm-vendor-ui'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('tcm_vendor_settings');
                do_settings_sections('tcm_vendor_settings');
                ?>

                <table class="wp-list-table widefat fixed striped tcm-vendor-table">
                    <thead>
                        <tr>
                            <th class="column-name"><?php _e('Vendor Name', 'tcm-vendor-ui'); ?></th>
                            <th class="column-logo"><?php _e('Logo URL', 'tcm-vendor-ui'); ?></th>
                            <th class="column-color"><?php _e('Background Color', 'tcm-vendor-ui'); ?></th>
                            <th class="column-color"><?php _e('Text Color', 'tcm-vendor-ui'); ?></th>
                            <th class="column-color"><?php _e('Button Background', 'tcm-vendor-ui'); ?></th>
                            <th class="column-color"><?php _e('Button Text', 'tcm-vendor-ui'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $slug => $settings): ?>
                            <tr>
                                <!-- Vendor Name (read-only for now) -->
                                <td class="column-name">
                                    <strong><?php echo esc_html($settings['name']); ?></strong>
                                    <input type="hidden" name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][name]" value="<?php echo esc_attr($settings['name']); ?>">
                                    <div class="vendor-slug"><?php echo esc_html($slug); ?></div>
                                </td>

                                <!-- Logo URL with Media Uploader -->
                                <td class="column-logo">
                                    <?php if (!empty($settings['logo_url'])): ?>
                                        <div class="tcm-logo-preview">
                                            <img src="<?php echo esc_url($settings['logo_url']); ?>" alt="Logo preview" style="max-width: 100px; max-height: 50px; margin-top: 5px;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="tcm-logo-input-group">
                                        <input
                                            type="text"
                                            name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][logo_url]"
                                            value="<?php echo esc_url($settings['logo_url']); ?>"
                                            class="regular-text tcm-logo-url"
                                            placeholder="https://..."
                                        >
                                        <button type="button" class="button tcm-upload-logo" data-slug="<?php echo esc_attr($slug); ?>">
                                            <?php _e('Upload', 'tcm-vendor-ui'); ?>
                                        </button>
                                    </div>
                                </td>

                                <!-- Background Color -->
                                <td class="column-color">
                                    <input
                                        type="text"
                                        name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][bg_color]"
                                        value="<?php echo esc_attr($settings['bg_color']); ?>"
                                        class="tcm-color-picker"
                                    >
                                </td>

                                <!-- Text Color -->
                                <td class="column-color">
                                    <input
                                        type="text"
                                        name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][text_color]"
                                        value="<?php echo esc_attr($settings['text_color']); ?>"
                                        class="tcm-color-picker"
                                    >
                                </td>

                                <!-- Button Background -->
                                <td class="column-color">
                                    <input
                                        type="text"
                                        name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][button_bg]"
                                        value="<?php echo esc_attr($settings['button_bg']); ?>"
                                        class="tcm-color-picker"
                                    >
                                </td>

                                <!-- Button Text -->
                                <td class="column-color">
                                    <input
                                        type="text"
                                        name="tcm_vendor_styles[<?php echo esc_attr($slug); ?>][button_text]"
                                        value="<?php echo esc_attr($settings['button_text']); ?>"
                                        class="tcm-color-picker"
                                    >
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <?php submit_button(__('Save All Vendor Settings', 'tcm-vendor-ui'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary tcm-reset-defaults" style="margin-left: 10px;">
                        <?php _e('Reset to Defaults', 'tcm-vendor-ui'); ?>
                    </button>
                </p>
            </form>

            <div class="tcm-vendor-info">
                <h2><?php _e('How It Works', 'tcm-vendor-ui'); ?></h2>
                <ul>
                    <li><?php _e('Each vendor gets unique branding based on their customer group.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('Logo images should be approximately 360x90 pixels for best results.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('Color changes are applied immediately to all vendor pages.', 'tcm-vendor-ui'); ?></li>
                    <li><?php _e('The vendor slug determines the CSS class applied to the body.', 'tcm-vendor-ui'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
