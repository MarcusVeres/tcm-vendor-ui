<?php
/**
 * TCM Vendor Debug Module
 * Displays B2BKing group data for inspection and testing
 */

class TCM_Vendor_Debug {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tcm-vendors',
            __('Debug B2BKing Groups', 'tcm-vendor-ui'),
            __('Debug', 'tcm-vendor-ui'),
            'manage_options',
            'tcm-vendor-debug',
            array($this, 'render_debug_page')
        );
    }

    /**
     * Get B2BKing groups
     */
    private function get_b2bking_groups() {
        if (!class_exists('B2bking')) {
            return false;
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
     * Get hardcoded vendors (current system)
     */
    private function get_hardcoded_vendors() {
        return array(
            'administrator' => 'Administrator',
            'standard-pricing' => 'Standard Pricing',
            'canadian-tire' => 'Canadian Tire',
            'costco' => 'Costco',
            'loblaws' => 'Loblaws',
        );
    }

    /**
     * Render debug page
     */
    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $b2b_groups = $this->get_b2bking_groups();
        $hardcoded = $this->get_hardcoded_vendors();

        // Check B2BKing integration status
        $integration_file = TCM_VENDOR_UI_PLUGIN_DIR . 'includes/class-tcm-b2bking-integration.php';
        $integration_active = false;
        $dynamic_vendors = array();

        if (file_exists($integration_file)) {
            require_once($integration_file);
            $integration = new TCM_B2BKing_Integration($this->main_plugin);
            $integration_active = $integration->is_active();
            if ($integration_active) {
                $dynamic_vendors = $integration->get_vendors_with_names();
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if ($integration_active): ?>
                <div class="notice notice-success">
                    <p><strong>✓ B2BKing Integration Active!</strong> Vendors are being loaded dynamically from B2BKing groups.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ B2BKing Integration Inactive.</strong> Using fallback hardcoded vendors.</p>
                </div>
            <?php endif; ?>

            <p>This page shows B2BKing group data and current integration status.</p>

            <?php if ($b2b_groups === false): ?>
                <div class="notice notice-error">
                    <p><strong>B2BKing Not Found!</strong></p>
                    <p>The B2BKing plugin is not installed or activated. This plugin requires B2BKing to function.</p>
                </div>
            <?php else: ?>

                <!-- B2BKing Groups -->
                <h2>B2BKing Customer Groups (Live Data)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Group ID</th>
                            <th>Group Name (Title)</th>
                            <th>Post Name (Slug)</th>
                            <th>Sanitized Slug (Our Method)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($b2b_groups)): ?>
                            <tr>
                                <td colspan="4">No B2BKing groups found. Please create some groups in B2BKing first.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($b2b_groups as $group): ?>
                                <?php
                                // This is how we currently generate slugs from group names in class-tcm-user-css.php
                                $our_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $group->post_title)));
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($group->ID); ?></code></td>
                                    <td><strong><?php echo esc_html($group->post_title); ?></strong></td>
                                    <td><code><?php echo esc_html($group->post_name); ?></code></td>
                                    <td><code><?php echo esc_html($our_slug); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <br><br>

                <!-- Current Hardcoded Vendors -->
                <h2>Current Hardcoded Vendors (Old System)</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Vendor Slug</th>
                            <th>Vendor Name</th>
                            <th>Match Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hardcoded as $slug => $name): ?>
                            <?php
                            // Check if this slug matches any B2BKing group
                            $match_found = false;
                            $match_method = '';
                            if ($b2b_groups) {
                                foreach ($b2b_groups as $group) {
                                    $our_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $group->post_title)));
                                    if ($our_slug === $slug) {
                                        $match_found = true;
                                        $match_method = 'Matches via title → "' . $group->post_title . '"';
                                        break;
                                    } elseif ($group->post_name === $slug) {
                                        $match_found = true;
                                        $match_method = 'Matches via post_name';
                                        break;
                                    }
                                }
                            }
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo esc_html($name); ?></td>
                                <td>
                                    <?php if ($slug === 'administrator'): ?>
                                        <span style="color: #0073aa;">⚠️ WordPress Role (special handling needed)</span>
                                    <?php elseif ($match_found): ?>
                                        <span style="color: #46b450;">✓ <?php echo esc_html($match_method); ?></span>
                                    <?php else: ?>
                                        <span style="color: #dc3232;">✗ No B2BKing group found</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <br><br>

                <!-- Dynamic Vendors (Current System) -->
                <?php if ($integration_active && !empty($dynamic_vendors)): ?>
                    <h2>Current Dynamic Vendors (Active System)</h2>
                    <p>These are the vendors currently being used by the plugin (loaded from B2BKing):</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Vendor Slug</th>
                                <th>Vendor Name</th>
                                <th>Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dynamic_vendors as $slug => $name): ?>
                                <tr>
                                    <td><code><?php echo esc_html($slug); ?></code></td>
                                    <td><strong><?php echo esc_html($name); ?></strong></td>
                                    <td>
                                        <?php if ($slug === 'administrator'): ?>
                                            <span style="color: #0073aa;">WordPress Role (special handling)</span>
                                        <?php else: ?>
                                            <span style="color: #46b450;">✓ B2BKing Group</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <br><br>
                <?php endif; ?>

                <!-- Current User Info -->
                <h2>Current User Info</h2>
                <?php
                $current_user = wp_get_current_user();
                $user_id = $current_user->ID;
                $b2b_group_id = get_user_meta($user_id, 'b2bking_customergroup', true);
                $b2b_group_name = $b2b_group_id ? get_the_title($b2b_group_id) : 'None';
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th style="width: 200px;">User ID</th>
                            <td><?php echo esc_html($user_id); ?></td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td><?php echo esc_html($current_user->user_login); ?></td>
                        </tr>
                        <tr>
                            <th>WordPress Roles</th>
                            <td><?php echo esc_html(implode(', ', $current_user->roles)); ?></td>
                        </tr>
                        <tr>
                            <th>B2BKing Group ID</th>
                            <td><?php echo esc_html($b2b_group_id ? $b2b_group_id : 'None assigned'); ?></td>
                        </tr>
                        <tr>
                            <th>B2BKing Group Name</th>
                            <td><strong><?php echo esc_html($b2b_group_name); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Body Class (Detected)</th>
                            <td>
                                <?php
                                // Simulate what class-tcm-user-css.php does
                                $classes = array();
                                foreach ($current_user->roles as $role) {
                                    $classes[] = 'tcm-' . sanitize_html_class($role);
                                }
                                if ($b2b_group_id) {
                                    $group_name = get_the_title($b2b_group_id);
                                    $vendor_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $group_name)));
                                    $classes[] = 'tcm-vendor-' . $vendor_slug;
                                }
                                echo '<code>' . esc_html(implode(' ', $classes)) . '</code>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <br><br>

                <!-- Next Steps -->
                <div class="notice notice-info">
                    <h3>Welcome to the Debug Page!</h3>
                    <ul>
                        <li>This page shows you what B2BKing sees when you work with your vendors.</li>
                        <li>What we call <strong>vendors<strong> is what B2B King calls <strong>groups</strong>.</li>
                        <li>The <strong>Post Name (Slug)</strong> is not reliable, so we use a <strong>Sanitized Slug</strong> based on the display name (Group Name / Title).</li>
                        <li>The tables below search B2BKing's database and check if the vendor names match our CSS classes.</li>
                        <li>This system is used to control what users see (colors, icons) in the TCM Vendors tab.</li>
                    </ul>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}
