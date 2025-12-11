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
     * Get visible categories for current vendor
     */
    private function get_visible_categories() {
        // Check if TCM_Dropdown_Settings class exists
        if (!class_exists('TCM_Dropdown_Settings')) {
            // Fallback to all default categories if dropdown settings not available
            return $this->get_default_categories();
        }

        // Create instance of dropdown settings (lightweight, just data access)
        $dropdown_settings = new TCM_Dropdown_Settings($this->main_plugin);

        // Get visible categories for current vendor
        $visible_categories = $dropdown_settings->get_visible_categories_for_current_vendor();

        // If empty (vendor not detected or no categories), return all default categories
        if (empty($visible_categories)) {
            return $this->get_default_categories();
        }

        return $visible_categories;
    }

    /**
     * Get default categories (fallback)
     */
    private function get_default_categories() {
        return array(
            array('slug' => 'bakery-carts', 'label' => 'Bakery Carts', 'order' => 1),
            array('slug' => 'cart-pushers', 'label' => 'Cart Pushers', 'order' => 2),
            array('slug' => 'ladders', 'label' => 'Ladders', 'order' => 3),
            array('slug' => 'material-carts', 'label' => 'Material Handling Cart', 'order' => 4),
            array('slug' => 'meat-carts', 'label' => 'Meat Carts', 'order' => 5),
            array('slug' => 'mobility-scooters', 'label' => 'Mobility Scooters', 'order' => 6),
            array('slug' => 'produce-carts', 'label' => 'Produce Carts', 'order' => 7),
            array('slug' => 'shopping-carts', 'label' => 'Shopping Carts', 'order' => 8),
            array('slug' => 'specialty-equipment', 'label' => 'Specialty Equipment', 'order' => 9),
        );
    }

    /**
     * Render the navigator shortcode
     */
    public function render_category_navigator($atts) {
        // Get visible categories for current vendor
        $visible_categories = $this->get_visible_categories();

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
        <?php

        // Return the buffered content
        return ob_get_clean();
    }
}
