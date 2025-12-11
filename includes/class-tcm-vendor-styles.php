<?php
/**
 * TCM Vendor Styles Module
 * Generates dynamic CSS based on vendor customization settings
 */

class TCM_Vendor_Styles {

    private $main_plugin;
    private $option_name = 'tcm_vendor_styles';
    private $b2bking_integration;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_b2bking_integration();
        $this->init_hooks();
    }

    /**
     * Initialize B2BKing integration
     */
    private function init_b2bking_integration() {
        $integration_file = TCM_VENDOR_UI_PLUGIN_DIR . 'includes/class-tcm-b2bking-integration.php';
        if (file_exists($integration_file)) {
            require_once($integration_file);
            $this->b2bking_integration = new TCM_B2BKing_Integration($this->main_plugin);
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_vendor_styles'), 20);
    }

    /**
     * Get default vendor settings
     * Migrated from theme CSS
     */
    public function get_default_vendors() {
        return array(
            'administrator' => array(
                'name' => 'Administrator',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
                'bg_color' => '#2A8EBF',
                'text_color' => '#ffffff',
                'button_bg' => '#0BA04C',
                'button_text' => '#ffffff',
            ),
            'standard-pricing' => array(
                'name' => 'Standard Pricing',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
                'bg_color' => '#0D3692',
                'text_color' => '#ffffff',
                'button_bg' => '#2A8EBF',
                'button_text' => '#ffffff',
            ),
            'canadian-tire' => array(
                'name' => 'Canadian Tire',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/e4f3dc6b134edf591489edcaf9c9209c.png',
                'bg_color' => '#262626',
                'text_color' => '#ffffff',
                'button_bg' => '#F4D52D',
                'button_text' => '#262626',
            ),
            'costco' => array(
                'name' => 'Costco',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/costco.png',
                'bg_color' => '#005DAB',
                'text_color' => '#ffffff',
                'button_bg' => '#E31936',
                'button_text' => '#ffffff',
            ),
            'loblaws' => array(
                'name' => 'Loblaws',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/04/loblaws.png',
                'bg_color' => '#191919',
                'text_color' => '#ffffff',
                'button_bg' => '#DC2626',
                'button_text' => '#ffffff',
            ),
        );
    }

    /**
     * Get vendor settings (returns saved settings or defaults)
     */
    public function get_vendors() {
        // Use false as default to distinguish between "never set" and "set but empty"
        $saved_vendors = get_option($this->option_name, false);

        // Get all available vendors (dynamically from B2BKing or fallback)
        $available_vendors = $this->get_all_available_vendors();

        // Only use defaults on FIRST install (option doesn't exist)
        if ($saved_vendors === false) {
            return $available_vendors;
        }

        // Merge saved settings with available vendors
        // This ensures new B2BKing groups get added with defaults
        foreach ($available_vendors as $slug => $default_settings) {
            // If vendor doesn't exist in saved data, add with defaults
            if (!isset($saved_vendors[$slug])) {
                $saved_vendors[$slug] = $default_settings;
            } else {
                // If vendor exists, merge to ensure all fields are present
                $saved_vendors[$slug] = wp_parse_args($saved_vendors[$slug], $default_settings);
            }
        }

        return $saved_vendors;
    }

    /**
     * Get all available vendors (dynamic from B2BKing or fallback)
     */
    private function get_all_available_vendors() {
        // Try to use B2BKing integration
        if ($this->b2bking_integration && $this->b2bking_integration->is_active()) {
            return $this->get_vendors_from_b2bking();
        }

        // Fallback to hardcoded vendors if B2BKing not available
        return $this->get_default_vendors();
    }

    /**
     * Get vendors dynamically from B2BKing
     */
    private function get_vendors_from_b2bking() {
        $vendors = array();
        $vendor_slugs = $this->b2bking_integration->get_vendor_slugs();

        foreach ($vendor_slugs as $slug) {
            $vendors[$slug] = $this->b2bking_integration->get_default_vendor_styles($slug);
        }

        return $vendors;
    }

    /**
     * Save vendor settings
     */
    public function save_vendors($vendors) {
        return update_option($this->option_name, $vendors);
    }

    /**
     * Generate CSS for a single vendor
     */
    private function generate_vendor_css($slug, $settings) {
        $css = '';

        // Sanitize slug for CSS class
        $class = 'tcm-vendor-' . sanitize_html_class($slug);

        // For administrator, use tcm-administrator instead
        if ($slug === 'administrator') {
            $class = 'tcm-administrator';
        }

        // Logo styles
        if (!empty($settings['logo_url'])) {
            $css .= ".{$class} .hero-logo {\n";
            $css .= "  background-image: url(\"" . esc_url($settings['logo_url']) . "\");\n";
            $css .= "}\n\n";
        }

        // Background and text color
        if (!empty($settings['bg_color']) || !empty($settings['text_color'])) {
            $css .= ".{$class} .branded-bg,\n";
            $css .= ".{$class} .branded-bg > span {\n";
            if (!empty($settings['bg_color'])) {
                $css .= "  background: " . sanitize_hex_color($settings['bg_color']) . " !important;\n";
            }
            if (!empty($settings['text_color'])) {
                $css .= "  color: " . sanitize_hex_color($settings['text_color']) . " !important;\n";
            }
            $css .= "}\n\n";
        }

        // Button styles
        if (!empty($settings['button_bg']) || !empty($settings['button_text'])) {
            $css .= ".{$class} button.wp-element-button,\n";
            $css .= ".{$class} a.wp-element-button {\n";
            if (!empty($settings['button_bg'])) {
                $css .= "  background: " . sanitize_hex_color($settings['button_bg']) . " !important;\n";
            }
            if (!empty($settings['button_text'])) {
                $css .= "  color: " . sanitize_hex_color($settings['button_text']) . " !important;\n";
            }
            $css .= "}\n\n";
        }

        return $css;
    }

    /**
     * Generate CSS for all vendors
     */
    public function generate_all_vendor_css() {
        $vendors = $this->get_vendors();
        $css = "/* TCM Vendor Styles - Dynamically Generated */\n\n";

        foreach ($vendors as $slug => $settings) {
            if (!empty($settings)) {
                $css .= "/* " . esc_html($settings['name']) . " */\n";
                $css .= $this->generate_vendor_css($slug, $settings);
            }
        }

        return $css;
    }

    /**
     * Enqueue vendor styles on frontend
     */
    public function enqueue_vendor_styles() {
        // Generate CSS
        $vendor_css = $this->generate_all_vendor_css();

        // Add inline styles to the main frontend stylesheet
        if (!empty($vendor_css)) {
            wp_add_inline_style('tcm-vendor-ui-frontend', $vendor_css);
        }
    }
}
