<?php
/**
 * TCM B2BKing Integration
 * Dynamically reads customer groups from B2BKing plugin
 */

class TCM_B2BKing_Integration {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
    }

    /**
     * Check if B2BKing is active and available
     */
    public function is_active() {
        return class_exists('B2bking');
    }

    /**
     * Get all B2BKing customer groups
     */
    public function get_b2bking_groups() {
        if (!$this->is_active()) {
            return array();
        }

        $groups = get_posts(array(
            'post_type' => 'b2bking_group',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        return $groups;
    }

    /**
     * Convert B2BKing group to vendor slug
     * Uses the same method as class-tcm-user-css.php for consistency
     */
    public function group_to_slug($group) {
        if (is_object($group) && isset($group->post_title)) {
            $title = $group->post_title;
        } elseif (is_string($group)) {
            $title = $group;
        } else {
            return '';
        }

        return sanitize_html_class(strtolower(str_replace(' ', '-', $title)));
    }

    /**
     * Get all vendor slugs (B2BKing groups + administrator)
     * Returns array of slugs only
     */
    public function get_vendor_slugs() {
        $slugs = array();

        // Add administrator first (WordPress role, not B2BKing group)
        $slugs[] = 'administrator';

        // Add all B2BKing groups
        $groups = $this->get_b2bking_groups();
        foreach ($groups as $group) {
            $slug = $this->group_to_slug($group);
            if (!empty($slug)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Get vendor name from slug
     * Returns the friendly display name
     */
    public function get_vendor_name($slug) {
        // Special case: administrator
        if ($slug === 'administrator') {
            return 'Administrator';
        }

        // Find matching B2BKing group
        $groups = $this->get_b2bking_groups();
        foreach ($groups as $group) {
            if ($this->group_to_slug($group) === $slug) {
                return $group->post_title;
            }
        }

        // Fallback: capitalize slug
        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * Get all vendors with names
     * Returns associative array: slug => name
     */
    public function get_vendors_with_names() {
        $vendors = array();

        // Add administrator
        $vendors['administrator'] = 'Administrator';

        // Add all B2BKing groups
        $groups = $this->get_b2bking_groups();
        foreach ($groups as $group) {
            $slug = $this->group_to_slug($group);
            if (!empty($slug)) {
                $vendors[$slug] = $group->post_title;
            }
        }

        return $vendors;
    }

    /**
     * Get default vendor styles for a specific vendor
     * Returns sensible defaults for new vendors
     */
    public function get_default_vendor_styles($slug) {
        // Administrator gets special default styling
        if ($slug === 'administrator') {
            return array(
                'name' => 'Administrator',
                'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
                'bg_color' => '#2A8EBF',
                'text_color' => '#ffffff',
                'button_bg' => '#0BA04C',
                'button_text' => '#ffffff',
            );
        }

        // All other vendors get default TCM styling
        return array(
            'name' => $this->get_vendor_name($slug),
            'logo_url' => 'http://shop.tcmlimited.com/wp-content/uploads/2025/03/tcm-square-logo.png',
            'bg_color' => '#0D3692',
            'text_color' => '#ffffff',
            'button_bg' => '#2A8EBF',
            'button_text' => '#ffffff',
        );
    }

    /**
     * Check if B2BKing is installed but not activated
     */
    public function is_installed_but_inactive() {
        // Check if B2BKing plugin file exists
        $plugin_file = WP_PLUGIN_DIR . '/b2bking-wholesale-for-woocommerce/b2bking.php';
        return file_exists($plugin_file) && !$this->is_active();
    }
}
