<?php
/**
 * TCM Dropdown Parts Configuration
 * Admin interface for mapping cart types to parts categories
 */

class TCM_Dropdown_Parts_Config {

    private $main_plugin;
    private $dropdown_settings;

    public function __construct($main_plugin, $dropdown_settings) {
        $this->main_plugin = $main_plugin;
        $this->dropdown_settings = $dropdown_settings;

        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_post_tcm_save_parts_config', array($this, 'save_parts_config'));
    }

    /**
     * Add admin page under TCM Dropdown Navigator menu
     */
    public function add_admin_page() {
        add_submenu_page(
            'tcm-dropdown-navigator',
            'Parts Configuration',
            'Parts Configuration',
            'manage_options',
            'tcm-dropdown-parts-config',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Get all available parts categories (excludes Cart Types and Cart Models hierarchies)
     */
    private function get_available_parts_categories() {
        // Slugs to exclude (set to false to include)
        $exclude_slugs = array(
            'cart-types' => true,  // Always exclude cart types
            'cart-models' => true  // Set to false if client wants cart models as parts
        );

        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        );

        // Bypass B2BKing filtering
        add_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        add_filter('b2bking_completely_category_restrict', '__return_false');

        $all_categories = get_terms($args);

        remove_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        remove_filter('b2bking_completely_category_restrict', '__return_false');

        if (is_wp_error($all_categories)) {
            return array();
        }

        // Build list of excluded category IDs
        $excluded_ids = array();

        foreach ($exclude_slugs as $slug => $should_exclude) {
            if (!$should_exclude) {
                continue;
            }

            $parent = get_term_by('slug', $slug, 'product_cat');
            if ($parent) {
                $excluded_ids[] = $parent->term_id;

                // Get all children
                $children = get_terms(array(
                    'taxonomy' => 'product_cat',
                    'parent' => $parent->term_id,
                    'hide_empty' => false
                ));

                foreach ($children as $child) {
                    $excluded_ids[] = $child->term_id;
                }
            }
        }

        // Filter out excluded categories
        $available_categories = array();
        foreach ($all_categories as $category) {
            if (!in_array($category->term_id, $excluded_ids)) {
                $available_categories[] = $category;
            }
        }

        return $available_categories;
    }

    /**
     * Get parts categories for a specific cart type
     */
    private function get_parts_for_cart_type($cart_type_term_id) {
        $parts = get_term_meta($cart_type_term_id, 'tcm_parts_categories', true);
        return !empty($parts) && is_array($parts) ? $parts : array();
    }

    /**
     * Initialize default parts configuration
     * Matches the original hard-coded values from frontend.js
     */
    public function initialize_defaults() {
        // Default parts mappings from original frontend.js
        $default_mappings = array(
            'bakery-carts' => array('Specialty Wheels'),
            'cart-pushers' => array(),
            'ladders' => array(),
            'material-carts' => array('Heavy Duty Wheels', 'Light Duty Wheels'),
            'meat-carts' => array('Light Duty Wheels'),
            'mobility-scooters' => array(),
            'produce-carts' => array('Heavy Duty Wheels', 'Light Duty Wheels'),
            'shopping-carts' => array(
                'Ads and Labels', 'Accessories', 'Bumpers', 'Chains', 'Ears',
                'Handles', 'Light Duty Wheels', 'Locks', 'Seats', 'Seatbelts',
                'Security Wheels'
            ),
            'specialty-equipment' => array('Specialty Wheels')
        );

        // Get all cart types
        $cart_types = $this->dropdown_settings->get_cart_type_categories();

        foreach ($cart_types as $cart_type) {
            // Skip if already configured
            $existing = get_term_meta($cart_type['term_id'], 'tcm_parts_categories', true);
            if (!empty($existing)) {
                continue;
            }

            // Get default parts for this cart type slug
            $default_parts_names = isset($default_mappings[$cart_type['slug']])
                ? $default_mappings[$cart_type['slug']]
                : array();

            if (empty($default_parts_names)) {
                continue;
            }

            // Convert part names to category IDs
            $parts_category_ids = array();
            foreach ($default_parts_names as $part_name) {
                $term = get_term_by('name', $part_name, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $parts_category_ids[] = $term->term_id;
                }
            }

            // Save default configuration
            if (!empty($parts_category_ids)) {
                update_term_meta($cart_type['term_id'], 'tcm_parts_categories', $parts_category_ids);
            }
        }

        return true;
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Initialize defaults if not already configured
        $this->initialize_defaults();

        // Get all cart types
        $cart_types = $this->dropdown_settings->get_cart_type_categories();

        // Get all available parts categories
        $available_categories = $this->get_available_parts_categories();

        ?>
        <div class="wrap">
            <h1>Dropdown Navigator - Parts Configuration</h1>

            <p class="description" style="font-size: 14px; margin-bottom: 20px;">
                This page controls which product categories appear as "Parts" options in the third dropdown
                when a user selects a specific cart type and chooses "Parts" from the second dropdown.
                Each cart type can have its own set of parts categories.
            </p>

            <div class="notice notice-info">
                <p><strong>How this works:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>For each cart type, select which product categories should appear as "Parts" options</li>
                    <li>When a user selects a cart type and chooses "Parts", they'll see these categories</li>
                    <li>This replaces the hard-coded parts lists in the JavaScript</li>
                    <li>Categories can be assigned to multiple cart types (e.g., "Wheels" can appear for multiple cart types)</li>
                </ul>
            </div>

            <?php if (empty($cart_types)): ?>
                <div class="notice notice-warning">
                    <p><strong>No cart types found!</strong></p>
                    <p>Please create cart type categories first.</p>
                    <p><a href="<?php echo admin_url('admin.php?page=tcm-dropdown-visibility'); ?>" class="button">
                        Go to Visibility Dashboard
                    </a></p>
                </div>
            <?php elseif (empty($available_categories)): ?>
                <div class="notice notice-warning">
                    <p><strong>No parts categories found!</strong></p>
                    <p>Please create some product categories to use as parts.</p>
                    <p><a href="<?php echo admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'); ?>" class="button">
                        Go to Categories
                    </a></p>
                </div>
            <?php else: ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('tcm_save_parts_config', 'tcm_parts_config_nonce'); ?>
                    <input type="hidden" name="action" value="tcm_save_parts_config">

                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 250px;">Cart Type</th>
                                <th>Parts Categories</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_types as $cart_type): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($cart_type['label']); ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            Slug: <?php echo esc_html($cart_type['slug']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $selected_parts = $this->get_parts_for_cart_type($cart_type['term_id']);
                                        ?>
                                        <select name="parts_config[<?php echo esc_attr($cart_type['term_id']); ?>][]"
                                                multiple
                                                style="width: 100%; min-height: 200px;">
                                            <?php foreach ($available_categories as $category): ?>
                                                <option value="<?php echo esc_attr($category->term_id); ?>"
                                                    <?php echo in_array($category->term_id, $selected_parts) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($category->name); ?>
                                                    <?php if ($category->parent): ?>
                                                        <?php
                                                        $parent = get_term($category->parent, 'product_cat');
                                                        if ($parent && !is_wp_error($parent)) {
                                                            echo ' (' . esc_html($parent->name) . ')';
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="description">
                                            Hold Ctrl (Windows) or Cmd (Mac) to select multiple categories.
                                            Selected: <strong><?php echo count($selected_parts); ?></strong>
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top: 20px;">
                        <?php submit_button('Save Parts Configuration', 'primary', 'submit', false); ?>
                    </p>
                </form>

                <div style="margin-top: 30px;">
                    <h3>Quick Reference</h3>
                    <p>Currently configured parts:</p>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($cart_types as $cart_type): ?>
                            <?php
                            $parts = $this->get_parts_for_cart_type($cart_type['term_id']);
                            $parts_names = array();
                            foreach ($parts as $part_id) {
                                $term = get_term($part_id, 'product_cat');
                                if ($term && !is_wp_error($term)) {
                                    $parts_names[] = $term->name;
                                }
                            }
                            ?>
                            <li>
                                <strong><?php echo esc_html($cart_type['label']); ?>:</strong>
                                <?php echo !empty($parts_names) ? esc_html(implode(', ', $parts_names)) : '<em>No parts configured</em>'; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>
        </div>

        <style>
        select[multiple] {
            padding: 5px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        select[multiple] option {
            padding: 5px;
        }
        select[multiple] option:checked {
            background: #2271b1;
            color: #fff;
        }
        </style>
        <?php
    }

    /**
     * Save parts configuration
     */
    public function save_parts_config() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Verify nonce
        if (!isset($_POST['tcm_parts_config_nonce']) || !wp_verify_nonce($_POST['tcm_parts_config_nonce'], 'tcm_save_parts_config')) {
            wp_die('Security check failed');
        }

        $parts_config = isset($_POST['parts_config']) ? $_POST['parts_config'] : array();

        // Save parts configuration for each cart type
        foreach ($parts_config as $cart_type_id => $parts_category_ids) {
            $cart_type_id = intval($cart_type_id);

            // Sanitize category IDs
            $sanitized_ids = array_map('intval', (array) $parts_category_ids);

            // Update term meta
            update_term_meta($cart_type_id, 'tcm_parts_categories', $sanitized_ids);
        }

        // Redirect back with success message
        wp_redirect(add_query_arg(
            array(
                'page' => 'tcm-dropdown-parts-config',
                'updated' => 'true'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}
