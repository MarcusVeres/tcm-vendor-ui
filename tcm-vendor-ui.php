<?php
/**
 * Plugin Name: TCM Vendor UI
 * Plugin URI: https://tcmlimited.com
 * Description: Custom UI components for TCM vendor portal including category navigation and user-based styling
 * Version: 1.0.2
 * Author: Marcus & Claude
 * Author URI: https://tcmlimited.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tcm-vendor-ui
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TCM_VENDOR_UI_VERSION', '1.0.2');
define('TCM_VENDOR_UI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCM_VENDOR_UI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCM_VENDOR_UI_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class TCM_Vendor_UI {

    /**
     * Holds class instances
     */
    private $user_css;
    private $category_navigator;
    private $vendor_styles;
    private $vendor_admin;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize component classes
        add_action('init', array($this, 'init_components'), 10);
    }

    /**
     * Initialize component classes
     */
    public function init_components() {
        // Load User CSS component
        $user_css_file = TCM_VENDOR_UI_PLUGIN_DIR . 'includes/class-tcm-user-css.php';
        if (file_exists($user_css_file)) {
            require_once($user_css_file);
            $this->user_css = new TCM_User_CSS($this);
        }

        // Load Category Navigator component
        $navigator_file = TCM_VENDOR_UI_PLUGIN_DIR . 'includes/class-tcm-category-navigator.php';
        if (file_exists($navigator_file)) {
            require_once($navigator_file);
            $this->category_navigator = new TCM_Category_Navigator($this);
        }

        // Load Vendor Styles component
        $vendor_styles_file = TCM_VENDOR_UI_PLUGIN_DIR . 'includes/class-tcm-vendor-styles.php';
        if (file_exists($vendor_styles_file)) {
            require_once($vendor_styles_file);
            $this->vendor_styles = new TCM_Vendor_Styles($this);
        }

        // Load Vendor Admin component (admin only)
        if (is_admin()) {
            $vendor_admin_file = TCM_VENDOR_UI_PLUGIN_DIR . 'admin/class-tcm-vendor-admin.php';
            if (file_exists($vendor_admin_file) && isset($this->vendor_styles)) {
                require_once($vendor_admin_file);
                $this->vendor_admin = new TCM_Vendor_Admin($this, $this->vendor_styles);
            }
        }
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('tcm-vendor-ui', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize frontend functionality
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        add_option('tcm_vendor_ui_version', TCM_VENDOR_UI_VERSION);
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        wp_enqueue_style(
            'tcm-vendor-ui-frontend',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            TCM_VENDOR_UI_VERSION
        );

        wp_enqueue_script(
            'tcm-vendor-ui-frontend',
            TCM_VENDOR_UI_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            TCM_VENDOR_UI_VERSION,
            true
        );
    }
}

// Initialize the plugin
new TCM_Vendor_UI();
