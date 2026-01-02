<?php
/**
 * TCM Dropdown Visibility Dashboard
 * Read-only display of category visibility across vendors
 * Links to B2BKing settings for editing
 */

class TCM_Dropdown_Visibility_Dashboard {

    private $main_plugin;
    private $dropdown_settings;

    public function __construct($main_plugin, $dropdown_settings) {
        $this->main_plugin = $main_plugin;
        $this->dropdown_settings = $dropdown_settings;

        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    /**
     * Add admin page under TCM Dropdown Navigator menu
     * Uses same slug as parent to replace auto-created submenu
     */
    public function add_admin_page() {
        add_submenu_page(
            'tcm-dropdown-navigator',
            'Visibility Dashboard',
            'Visibility Dashboard',
            'manage_options',
            'tcm-dropdown-navigator', // Same slug as parent to replace auto-created submenu
            array($this, 'render_dashboard_page')
        );
    }

    /**
     * Render the visibility dashboard
     */
    public function render_dashboard_page() {
        // Get all cart type categories
        $categories = $this->dropdown_settings->get_cart_type_categories();

        // Get all vendors
        $b2bking_integration = new TCM_B2BKing_Integration($this->main_plugin);
        $vendors = $b2bking_integration->get_vendors_with_names();

        ?>
        <div class="wrap">
            <h1>Dropdown Navigator - Category Visibility</h1>

            <div class="notice notice-info">
                <p><strong>How to change visibility settings:</strong></p>
                <ol>
                    <li>Go to <strong>Products → Categories</strong></li>
                    <li>Click on the category you want to edit</li>
                    <li>Scroll down to the <strong>"B2BKing Category Visibility"</strong> or <strong>"Group Visibility"</strong> section</li>
                    <li>Check/uncheck the customer groups that should see this category</li>
                    <li>Click <strong>"Update"</strong></li>
                    <li>Refresh this page to see updated visibility</li>
                </ol>
                <p><em>Note: This dashboard is read-only. All changes must be made via the category edit screen. If all checkboxes are unchecked, the category is visible to everyone by default.</em></p>
            </div>

            <?php if (empty($categories)): ?>
                <div class="notice notice-warning">
                    <p><strong>No "Cart Types" categories found!</strong></p>
                    <p>Please create a parent category called "Cart Types" and add child categories for each product type.</p>
                    <p><a href="<?php echo admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'); ?>" class="button button-primary">
                        Go to Categories
                    </a></p>
                </div>
            <?php else: ?>

                <h2>Visibility Matrix</h2>
                <p class="description">
                    ✅ = Visible to vendor &nbsp;&nbsp; ❌ = Hidden from vendor &nbsp;&nbsp; 🟢 = Default (not set, visible by default)
                </p>

                <div style="overflow-x: auto; margin-top: 20px;">
                    <table class="wp-list-table widefat striped" style="min-width: 800px; table-layout: fixed;">
                        <colgroup>
                            <col style="width: 220px;">
                            <?php foreach ($vendors as $vendor_slug => $vendor_name): ?>
                                <col style="width: 110px;">
                            <?php endforeach; ?>
                            <col style="width: 120px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th style="position: sticky; left: 0; background: #f0f0f1; z-index: 10; box-shadow: 2px 0 4px rgba(0,0,0,0.1);">
                                    Category
                                </th>
                                <?php foreach ($vendors as $vendor_slug => $vendor_name): ?>
                                    <th style="text-align: center;">
                                        <?php echo esc_html($vendor_name); ?>
                                    </th>
                                <?php endforeach; ?>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $row_index = 0;
                            foreach ($categories as $category):
                                $bg_color = ($row_index % 2 === 0) ? '#fff' : '#f9f9f9';
                                $row_index++;
                            ?>
                                <tr>
                                    <td style="position: sticky; left: 0; background: <?php echo $bg_color; ?>; z-index: 9; box-shadow: 2px 0 4px rgba(0,0,0,0.1);">
                                        <strong><?php echo esc_html($category['label']); ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            Order: <?php echo esc_html($category['order']); ?>
                                            <?php if (!empty($category['enable_fleet_mgmt'])): ?>
                                                | Fleet Mgmt: ✓
                                            <?php endif; ?>
                                        </small>
                                    </td>

                                    <?php foreach ($vendors as $vendor_slug => $vendor_name): ?>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php
                                            $visibility = $this->check_category_visibility($category, $vendor_slug);
                                            ?>
                                            <span style="font-size: 18px;"><?php echo $visibility['icon']; ?></span>
                                            <span style="font-size: 11px; color: #666; margin-left: 4px;">
                                                <?php echo esc_html($visibility['label']); ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>

                                    <td style="text-align: center;">
                                        <a href="<?php echo admin_url('term.php?taxonomy=product_cat&tag_ID=' . $category['term_id'] . '&post_type=product'); ?>"
                                           class="button button-small">
                                            Edit Category
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; // End categories loop ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 30px;">
                    <h3>Quick Stats</h3>
                    <ul>
                        <li><strong><?php echo count($categories); ?></strong> cart type categories configured</li>
                        <li><strong><?php echo count($vendors); ?></strong> vendor groups</li>
                        <li><strong><?php echo count($categories) * count($vendors); ?></strong> total visibility settings</li>
                    </ul>
                </div>

                <div style="margin-top: 30px;">
                    <h3>Debug Information</h3>
                    <details>
                        <summary>Click to expand - Raw B2BKing Meta Values</summary>
                        <pre style="background: #f5f5f5; padding: 15px; overflow: auto;"><?php
                            echo "=== RAW B2BKING META VALUES ===\n\n";

                            foreach ($categories as $category) {
                                echo "Category: {$category['label']} (ID: {$category['term_id']})\n";
                                echo "Fleet Mgmt Meta: " . get_term_meta($category['term_id'], 'tcm_enable_fleet_management', true) . "\n";

                                foreach ($vendors as $vendor_slug => $vendor_name) {
                                    $group_id = $this->dropdown_settings->get_b2bking_group_id_from_slug($vendor_slug);
                                    if ($group_id) {
                                        $meta_key = "b2bking_group_{$group_id}";
                                        $meta_value = get_term_meta($category['term_id'], $meta_key, true);
                                        $display_value = ($meta_value === '') ? 'EMPTY' : $meta_value;
                                        echo "  {$vendor_name} (group {$group_id}): {$meta_key} = '{$display_value}'\n";
                                    } else {
                                        echo "  {$vendor_name}: No B2BKing group\n";
                                    }
                                }
                                echo "\n";
                            }

                            echo "\n=== CATEGORIES DATA ===\n";
                            print_r($categories);

                            echo "\n=== VENDORS DATA ===\n";
                            print_r($vendors);
                        ?></pre>
                    </details>
                </div>

            <?php endif; ?>

        </div>

        <style>
            .wp-list-table th,
            .wp-list-table td {
                padding: 12px;
            }
            .wp-list-table td:first-child {
                min-width: 200px;
            }
        </style>
        <?php
    }

    /**
     * Check if a category is visible to a specific vendor
     * Returns array with icon and label
     */
    private function check_category_visibility($category, $vendor_slug) {
        // Administrator sees everything
        if ($vendor_slug === 'administrator') {
            return array('icon' => '✅', 'label' => 'Visible');
        }

        // Get B2BKing group ID for this vendor
        $group_id = $this->dropdown_settings->get_b2bking_group_id_from_slug($vendor_slug);

        if (!$group_id) {
            // No B2BKing group, default to visible
            return array('icon' => '🟢', 'label' => 'Default');
        }

        // Check B2BKing term meta directly to see what's actually stored
        $meta_key = "b2bking_group_{$group_id}";
        $meta_value = get_term_meta($category['term_id'], $meta_key, true);

        if ($meta_value === '') {
            // Not set, default behavior (visible)
            return array('icon' => '🟢', 'label' => 'Default');
        } elseif ($meta_value === '1') {
            // Explicitly visible
            return array('icon' => '✅', 'label' => 'Visible');
        } else {
            // Explicitly hidden
            return array('icon' => '❌', 'label' => 'Hidden');
        }
    }
}
