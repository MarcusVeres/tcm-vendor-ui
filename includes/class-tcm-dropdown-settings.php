<?php
/**
 * TCM Dropdown Settings Module
 * Manages dropdown navigator categories, vendor visibility, and data localization
 */

class TCM_Dropdown_Settings {

    private $main_plugin;
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
        // Data is now passed inline via shortcode (see class-tcm-category-navigator.php)
        // No longer using wp_localize_script() due to hook timing issues
    }

    /**
     * Get cart type categories from WooCommerce
     * Replaces hard-coded get_default_product_types()
     */
    public function get_cart_type_categories() {
        // BYPASS B2BKing category filtering - we handle visibility ourselves
        add_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        add_filter('b2bking_completely_category_restrict', '__return_false');

        // Get parent "Cart Types" category
        $parent = get_term_by('slug', 'cart-types', 'product_cat');

        if (!$parent) {
            // Fallback to hard-coded defaults if parent doesn't exist
            return $this->get_default_product_types();
        }

        // Get all child categories (B2BKing filtering already bypassed above)
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'parent' => $parent->term_id,
            'hide_empty' => false,
            'orderby' => 'name', // Use simple ordering for now
            'order' => 'ASC'
        ));

        if (is_wp_error($terms) || empty($terms)) {
            // Fallback to hard-coded defaults if query fails
            return $this->get_default_product_types();
        }

        // Transform to expected format
        $categories = array();
        foreach ($terms as $term) {
            $order = get_term_meta($term->term_id, 'tcm_category_order', true);
            $enable_fleet_mgmt = get_term_meta($term->term_id, 'tcm_enable_fleet_management', true);
            $parts_category_ids = get_term_meta($term->term_id, 'tcm_parts_categories', true);

            // Get parts category details
            $parts = array();
            if (!empty($parts_category_ids) && is_array($parts_category_ids)) {
                foreach ($parts_category_ids as $part_id) {
                    $part_term = get_term($part_id, 'product_cat');
                    if ($part_term && !is_wp_error($part_term)) {
                        $parts[] = array(
                            'slug' => $part_term->slug,
                            'name' => $part_term->name,
                            'term_id' => $part_term->term_id
                        );
                    }
                }
            }

            $categories[$term->slug] = array(
                'slug' => $term->slug,
                'label' => $term->name,
                'term_id' => $term->term_id,
                'order' => !empty($order) ? intval($order) : 999,
                'enable_fleet_mgmt' => ($enable_fleet_mgmt === '1'),
                'parts' => $parts
            );
        }

        // Remove B2BKing bypass filters
        remove_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        remove_filter('b2bking_completely_category_restrict', '__return_false');

        // If no categories found, fallback to defaults
        if (empty($categories)) {
            return $this->get_default_product_types();
        }

        return $categories;
    }

    /**
     * Get default product types (DEPRECATED - kept for backward compatibility)
     * These are the Level 1 categories in the dropdown navigator
     * @deprecated Use get_cart_type_categories() instead
     */
    public function get_default_product_types() {
        return array(
            'bakery-carts' => array(
                'slug' => 'bakery-carts',
                'label' => 'Bakery Carts',
                'order' => 1,
            ),
            'cart-pushers' => array(
                'slug' => 'cart-pushers',
                'label' => 'Cart Pushers',
                'order' => 2,
            ),
            'ladders' => array(
                'slug' => 'ladders',
                'label' => 'Ladders',
                'order' => 3,
            ),
            'material-carts' => array(
                'slug' => 'material-carts',
                'label' => 'Material Handling Cart',
                'order' => 4,
            ),
            'meat-carts' => array(
                'slug' => 'meat-carts',
                'label' => 'Meat Carts',
                'order' => 5,
            ),
            'mobility-scooters' => array(
                'slug' => 'mobility-scooters',
                'label' => 'Mobility Scooters',
                'order' => 6,
            ),
            'produce-carts' => array(
                'slug' => 'produce-carts',
                'label' => 'Produce Carts',
                'order' => 7,
            ),
            'shopping-carts' => array(
                'slug' => 'shopping-carts',
                'label' => 'Shopping Carts',
                'order' => 8,
            ),
            'specialty-equipment' => array(
                'slug' => 'specialty-equipment',
                'label' => 'Specialty Equipment',
                'order' => 9,
            ),
        );
    }

    /**
     * Get all vendors (slugs only)
     * Dynamically from B2BKing or fallback to hardcoded
     */
    public function get_vendor_slugs() {
        // Try to use B2BKing integration
        if ($this->b2bking_integration && $this->b2bking_integration->is_active()) {
            return $this->b2bking_integration->get_vendor_slugs();
        }

        // Fallback to hardcoded vendors if B2BKing not available
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
     * Now reads from WooCommerce categories instead of saved options
     */
    public function get_category_settings() {
        // Read from WooCommerce instead of saved options
        return $this->get_cart_type_categories();
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
        // Use false as default to distinguish between "never set" and "set but empty"
        $saved = get_option('tcm_dropdown_vendor_visibility', false);

        // Only use defaults on FIRST install (option doesn't exist)
        // If option exists but is empty array, that's a valid saved state (user unchecked everything)
        if ($saved === false) {
            return $this->get_default_vendor_visibility();
        }

        // Merge saved settings with defaults for any new categories that might have been added
        $defaults = $this->get_default_vendor_visibility();
        $vendors = $this->get_vendor_slugs();
        $categories = $this->get_category_settings();

        foreach ($vendors as $vendor_slug) {
            // If vendor doesn't exist in saved data, add with defaults
            if (!isset($saved[$vendor_slug])) {
                $saved[$vendor_slug] = $defaults[$vendor_slug];
                continue;
            }

            // Check for new categories and add them with default visibility (true)
            foreach ($categories as $category_slug => $category_data) {
                if (!isset($saved[$vendor_slug][$category_slug])) {
                    $saved[$vendor_slug][$category_slug] = true;
                }
            }
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
     * Convert vendor slug to B2BKing group ID
     *
     * @param string $vendor_slug Vendor slug (e.g., 'canadian-tire')
     * @return int|false Group ID or false if not found
     */
    public function get_b2bking_group_id_from_slug($vendor_slug) {
        // Special case: administrator is not a B2BKing group
        if ($vendor_slug === 'administrator') {
            return false;
        }

        if (!$this->b2bking_integration || !$this->b2bking_integration->is_active()) {
            return false;
        }

        $groups = $this->b2bking_integration->get_b2bking_groups();

        foreach ($groups as $group) {
            $slug = $this->b2bking_integration->group_to_slug($group);
            if ($slug === $vendor_slug) {
                return $group->ID; // B2BKing group post ID
            }
        }

        return false;
    }

    /**
     * Check if a category is visible to a specific B2BKing group
     *
     * Simple logic:
     * - '1' = visible
     * - '0' = hidden
     * - '' (empty) = visible (default)
     *
     * @param int $term_id Category term ID
     * @param int $group_id B2BKing group ID
     * @return bool True if visible, false if hidden
     */
    public function is_category_visible_to_group($term_id, $group_id) {
        // If B2BKing not active, default to visible
        if (!class_exists('B2bking')) {
            return true;
        }

        // Check the meta value for this specific group
        $meta_key = "b2bking_group_{$group_id}";
        $meta_value = get_term_meta($term_id, $meta_key, true);

        // '0' = explicitly hidden
        if ($meta_value === '0') {
            return false;
        }

        // '1' or '' (empty) = visible
        return true;
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
     * Now uses B2BKing term meta instead of custom options
     */
    public function get_visible_categories_for_vendor($vendor_slug) {
        // Get all cart type categories
        $categories = $this->get_cart_type_categories();

        // Special case: administrator sees everything
        if ($vendor_slug === 'administrator') {
            return array_values($categories);
        }

        // Get B2BKing group ID for this vendor
        $group_id = $this->get_b2bking_group_id_from_slug($vendor_slug);

        if (!$group_id) {
            // Vendor has no B2BKing group (shouldn't happen, but default to showing all)
            return array_values($categories);
        }

        // Filter categories by B2BKing visibility
        $visible = array();
        foreach ($categories as $category) {
            $term_id = isset($category['term_id']) ? $category['term_id'] : 0;
            $is_visible = $this->is_category_visible_to_group($term_id, $group_id);

            if ($is_visible) {
                $visible[] = $category;
            }
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
                'debugInfo' => array(
                    'totalCategories' => count($this->get_cart_type_categories()),
                    'visibleCount' => count($visible_categories),
                )
            )
        );
    }
}
