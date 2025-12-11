<?php
/**
 * TCM Dropdown Settings Module
 * Manages dropdown navigator categories, vendor visibility, and data localization
 */

class TCM_Dropdown_Settings {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Localize settings for frontend JavaScript
        add_action('wp_enqueue_scripts', array($this, 'localize_dropdown_settings'), 20);
    }

    /**
     * Get default product types
     * These are the Level 1 categories in the dropdown navigator
     */
    public function get_default_product_types() {
        return array(
            'bakery-carts' => array(
                'slug' => 'bakery-carts',
                'label' => 'Bakery Carts',
                'order' => 1,
            ),
            'cart-models' => array(
                'slug' => 'cart-models',
                'label' => 'Cart Models',
                'order' => 2,
            ),
            'cart-pushers' => array(
                'slug' => 'cart-pushers',
                'label' => 'Cart Pushers',
                'order' => 3,
            ),
            'cart-washers' => array(
                'slug' => 'cart-washers',
                'label' => 'Cart Washers',
                'order' => 4,
            ),
            'corral-carts' => array(
                'slug' => 'corral-carts',
                'label' => 'Corral Carts',
                'order' => 5,
            ),
            'parts-accessories' => array(
                'slug' => 'parts-accessories',
                'label' => 'Parts & Accessories',
                'order' => 6,
            ),
            'safety-vests' => array(
                'slug' => 'safety-vests',
                'label' => 'Safety Vests',
                'order' => 7,
            ),
            'shopping-baskets' => array(
                'slug' => 'shopping-baskets',
                'label' => 'Shopping Baskets',
                'order' => 8,
            ),
            'winter-salt' => array(
                'slug' => 'winter-salt',
                'label' => 'Winter Salt',
                'order' => 9,
            ),
        );
    }

    /**
     * Get all vendors (slugs only)
     * Reuses the vendor list from vendor styles
     */
    public function get_vendor_slugs() {
        return array(
            'administrator',
            'standard-pricing',
            'canadian-tire',
            'costco',
            'loblaws',
        );
    }

    /**
     * Get category settings (labels and ordering)
     * Returns merged default + saved settings
     */
    public function get_category_settings() {
        $defaults = $this->get_default_product_types();
        $saved = get_option('tcm_dropdown_categories', array());

        // Merge saved settings with defaults
        $categories = array();
        foreach ($defaults as $slug => $default_data) {
            if (isset($saved[$slug])) {
                // Use saved data but preserve slug
                $categories[$slug] = wp_parse_args($saved[$slug], $default_data);
            } else {
                // Use default
                $categories[$slug] = $default_data;
            }
        }

        // Sort by order
        uasort($categories, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $categories;
    }

    /**
     * Save category settings
     */
    public function save_category_settings($settings) {
        return update_option('tcm_dropdown_categories', $settings);
    }

    /**
     * Get vendor visibility settings
     * Returns array of vendor => product_type => boolean
     */
    public function get_vendor_visibility() {
        $saved = get_option('tcm_dropdown_vendor_visibility', array());

        // If no saved settings, return defaults (all vendors see all categories)
        if (empty($saved)) {
            return $this->get_default_vendor_visibility();
        }

        return $saved;
    }

    /**
     * Get default vendor visibility (all vendors see all categories)
     */
    public function get_default_vendor_visibility() {
        $vendors = $this->get_vendor_slugs();
        $categories = $this->get_category_settings();
        $visibility = array();

        foreach ($vendors as $vendor_slug) {
            $visibility[$vendor_slug] = array();
            foreach ($categories as $category_slug => $category_data) {
                $visibility[$vendor_slug][$category_slug] = true;
            }
        }

        return $visibility;
    }

    /**
     * Save vendor visibility settings
     */
    public function save_vendor_visibility($settings) {
        return update_option('tcm_dropdown_vendor_visibility', $settings);
    }

    /**
     * Detect current user's vendor slug
     * Reuses logic from class-tcm-user-css.php body class detection
     * Returns vendor slug or false if cannot detect
     */
    public function detect_current_vendor() {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        // First check B2BKing customer group
        if (class_exists('B2bking')) {
            $b2b_group_id = get_user_meta($user_id, 'b2bking_customergroup', true);
            if (!empty($b2b_group_id)) {
                $group_name = get_the_title($b2b_group_id);
                if (!empty($group_name)) {
                    $vendor_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $group_name)));
                    return $vendor_slug;
                }
            }
        }

        // Fallback to WordPress role
        $user_roles = $current_user->roles;
        if (!empty($user_roles)) {
            return $user_roles[0]; // Use first role
        }

        return false;
    }

    /**
     * Get visible categories for current vendor
     * Returns array of category slugs that this vendor can see
     */
    public function get_visible_categories_for_current_vendor() {
        $vendor_slug = $this->detect_current_vendor();

        if (!$vendor_slug) {
            // If cannot detect vendor, return empty array
            // Frontend will show error
            return array();
        }

        return $this->get_visible_categories_for_vendor($vendor_slug);
    }

    /**
     * Get visible categories for a specific vendor
     */
    public function get_visible_categories_for_vendor($vendor_slug) {
        $visibility = $this->get_vendor_visibility();
        $categories = $this->get_category_settings();
        $visible = array();

        if (isset($visibility[$vendor_slug])) {
            foreach ($categories as $category_slug => $category_data) {
                // Check if this vendor can see this category
                if (isset($visibility[$vendor_slug][$category_slug]) && $visibility[$vendor_slug][$category_slug]) {
                    $visible[] = $category_data;
                }
            }
        } else {
            // Vendor not in visibility settings, show all categories (default behavior)
            $visible = array_values($categories);
        }

        return $visible;
    }

    /**
     * Localize settings to JavaScript
     * Makes settings available to frontend.js
     */
    public function localize_dropdown_settings() {
        $vendor_slug = $this->detect_current_vendor();
        $visible_categories = array();

        if ($vendor_slug) {
            $visible_categories = $this->get_visible_categories_for_vendor($vendor_slug);
        }

        wp_localize_script(
            'tcm-vendor-ui-frontend',
            'tcmDropdownSettings',
            array(
                'vendorSlug' => $vendor_slug,
                'vendorDetected' => !empty($vendor_slug),
                'visibleCategories' => $visible_categories,
                'errorMessage' => __('Cannot detect user! Please contact administrator about this message.', 'tcm-vendor-ui'),
            )
        );
    }
}
