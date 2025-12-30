<?php
/**
 * TCM Category Navigator Module
 * A product category navigation component for WooCommerce
 */

class TCM_Category_Navigator {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_shortcode('category_navigator', array($this, 'render_category_navigator'));
        add_action('wp_enqueue_scripts', array($this, 'register_navigator_assets'));
    }

    /**
     * Register assets
     * Note: Assets are loaded via main plugin file (tcm-vendor-ui.php)
     * This registration is kept for backward compatibility
     */
    public function register_navigator_assets() {
        // Assets are now managed by the main plugin class
        // CSS: assets/css/frontend.css
        // JS: assets/js/frontend.js
    }

    /**
     * Render the navigator shortcode
     */
    public function render_category_navigator($atts) {
        // BYPASS B2BKing category filtering - we handle visibility ourselves
        add_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        add_filter('b2bking_completely_category_restrict', '__return_false');

        // Query categories RIGHT HERE in the shortcode
        $parent = get_term_by('slug', 'cart-types', 'product_cat');

        // NO FALLBACK. If parent doesn't exist, CRITICAL ERROR.
        if (!$parent) {
            // DEBUG: Let's see what categories DO exist
            $all_cats = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false
            ));

            $debug_info = '';
            if (current_user_can('manage_options')) {
                $debug_info = '<h3>DEBUG: Searching ALL ' . count($all_cats) . ' product categories for "cart-types":</h3><ul style="max-height: 400px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;">';
                foreach ($all_cats as $cat) {
                    $highlight = (strpos($cat->slug, 'cart') !== false) ? ' style="background: yellow; font-weight: bold;"' : '';
                    $debug_info .= '<li' . $highlight . '>' . esc_html($cat->name) . ' (slug: <code>' . esc_html($cat->slug) . '</code>, ID: ' . $cat->term_id . ', parent: ' . $cat->parent . ')</li>';
                }
                $debug_info .= '</ul>';
            }

            return '<div style="border: 4px solid red; padding: 20px; background: #ffeeee; margin: 20px 0;">
                <h2 style="color: red; margin-top: 0;">CRITICAL ERROR: Cart Types Category Not Found</h2>
                <p><strong>get_term_by(\'slug\', \'cart-types\', \'product_cat\') returned FALSE</strong></p>
                <p>Go to <strong>Products → Categories</strong> and verify a category exists with EXACT slug <code>cart-types</code></p>
                ' . $debug_info . '
            </div>';
        }

        // Query child categories (B2BKing filtering already bypassed above)
        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'parent' => $parent->term_id,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        if (is_wp_error($terms)) {
            return '<div style="border: 4px solid red; padding: 20px; background: #ffeeee; margin: 20px 0;">
                <h2 style="color: red; margin-top: 0;">CRITICAL ERROR: Cannot Query Categories</h2>
                <p><strong>WooCommerce get_terms() error:</strong> ' . esc_html($terms->get_error_message()) . '</p>
            </div>';
        }

        if (empty($terms)) {
            $debug_info = '';
            if (current_user_can('manage_options')) {
                $debug_info = '<h3>DEBUG INFO:</h3>';
                $debug_info .= '<p>Parent: <strong>' . esc_html($parent->name) . '</strong> (ID: ' . $parent->term_id . ')</p>';
                $debug_info .= '<p>B2BKing bypass filter: ' . (has_filter('b2bking_categories_restrict_filter_abort') ? '✅ Active' : '❌ Not Active') . '</p>';
                $debug_info .= '<p><em>If categories exist but aren\'t showing, check B2BKing visibility checkboxes in category editor.</em></p>';
            }

            return '<div style="border: 4px solid red; padding: 20px; background: #ffeeee; margin: 20px 0;">
                <h2 style="color: red; margin-top: 0;">CRITICAL ERROR: No Cart Type Categories Found</h2>
                <p><strong>The "Cart Types" category has no children.</strong></p>
                <p>Go to <strong>Products → Categories</strong> and create child categories under "Cart Types".</p>
                ' . $debug_info . '
            </div>';
        }

        // Build category data array
        $categories = array();
        foreach ($terms as $term) {
            $order = get_term_meta($term->term_id, 'tcm_category_order', true);
            $enable_fleet = get_term_meta($term->term_id, 'tcm_enable_fleet_management', true);

            $categories[] = array(
                'slug' => $term->slug,
                'label' => $term->name,
                'term_id' => $term->term_id,
                'order' => !empty($order) ? intval($order) : 999,
                'enable_fleet_mgmt' => ($enable_fleet === '1'),
            );
        }

        // Detect current vendor and filter categories
        $dropdown_settings = new TCM_Dropdown_Settings($this->main_plugin);
        $vendor_slug = $dropdown_settings->detect_current_vendor();

        if (!$vendor_slug) {
            return '<div style="border: 4px solid red; padding: 20px; background: #ffeeee; margin: 20px 0;">
                <h2 style="color: red; margin-top: 0;">CRITICAL ERROR: Cannot Detect User</h2>
                <p><strong>You must be logged in to use this dropdown.</strong></p>
                <p>If you are logged in, contact administrator - your user account is not properly configured.</p>
            </div>';
        }

        $visible_categories = $dropdown_settings->get_visible_categories_for_vendor($vendor_slug);

        // Start output buffering
        ob_start();

        // Output HTML
        ?>
        <div class="category-navigator">
          <div class="category-select">
            <label for="level1">Product Type</label>
            <select id="level1" onchange="updateLevel2()">
              <option value="">Select a Product</option>
              <?php foreach ($visible_categories as $category): ?>
                <option value="<?php echo esc_attr($category['slug']); ?>">
                  <?php echo esc_html($category['label']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="category-select">
            <label for="level2">Category</label>
            <select id="level2" disabled onchange="updateLevel3()">
              <option value="">Select a Category</option>
            </select>
          </div>

          <div class="category-select" id="level3-container">
            <div id="contact-message" class="contact-message">
              Please <a href="<?php echo get_permalink(get_page_by_path('contact')); ?>"> contact us </a> for assistance.
            </div>

            <div id="level3-select-container">
              <label for="level3">Parts</label>
              <select id="level3" disabled onchange="updateGoButton()">
                <option value="">Select a Part</option>
              </select>
            </div>
          </div>

          <a class="header-selector-button" id="go-button" href="#" onclick="handleGoClick()">
            GO
          </a>
        </div>

        <script>
        // Pass category data inline
        window.tcmDropdownSettings = {
            vendorSlug: <?php echo json_encode($vendor_slug); ?>,
            vendorDetected: true,
            visibleCategories: <?php echo json_encode(array_values($visible_categories)); ?>
        };
        </script>

        <?php if (current_user_can('manage_options')): ?>
        <div style="margin-top: 20px; padding: 10px 15px; background: #f8f9fa; border-left: 4px solid #0073aa; font-size: 12px; font-family: monospace;">
            <strong style="color: #0073aa;">🔧 Admin Debug Info</strong><br>
            <span style="color: #666;">Vendor:</span> <strong><?php echo esc_html($vendor_slug); ?></strong> |
            <span style="color: #666;">Visible:</span> <strong><?php echo count($visible_categories); ?> categories</strong><br>
            <span style="color: #999; font-size: 11px;"><?php echo esc_html(implode(', ', array_column($visible_categories, 'label'))); ?></span>
        </div>
        <?php endif; ?>
        <?php

        // Return the buffered content
        $output = ob_get_clean();

        // Remove B2BKing bypass filters
        remove_filter('b2bking_categories_restrict_filter_abort', '__return_true');
        remove_filter('b2bking_completely_category_restrict', '__return_false');

        return $output;
    }
}
