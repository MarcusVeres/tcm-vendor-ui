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
        // Start output buffering
        ob_start();

        // Output HTML
        ?>
        <div class="category-navigator">
          <div class="category-select">
            <label for="level1">Product Type</label>
            <select id="level1" onchange="updateLevel2()">
              <option value="">Select a Product</option>
              <option value="bakery-carts">Bakery Carts</option>
              <option value="cart-pushers">Cart Pushers</option>
              <option value="ladders">Ladders</option>
              <option value="material-carts">Material Handling Cart</option>
              <option value="meat-carts">Meat Carts</option>
              <option value="mobility-scooters">Mobility Scooters</option>
              <option value="produce-carts">Produce Carts</option>
              <option value="shopping-carts">Shopping Carts</option>
              <option value="specialty-equipment">Specialty Equipment</option>
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
