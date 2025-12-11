<?php
/**
 * TCM User CSS Module
 * Adds custom CSS classes based on user roles and B2BKing customer groups
 */

class TCM_User_CSS {

    private $main_plugin;

    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_filter('body_class', array($this, 'add_user_based_classes'));
    }

    /**
     * Detect user role and B2BKing customer group and add to body classes
     */
    public function add_user_based_classes($classes) {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID;
            $user_roles = $current_user->roles;

            // WordPress role
            foreach ($user_roles as $role) {
                $classes[] = 'tcm-' . sanitize_html_class($role);
            }

            // B2BKing customer group
            $b2b_group_id = get_user_meta($user_id, 'b2bking_customergroup', true);
            if (!empty($b2b_group_id)) {
                // OPTIONAL :: Group ID
                // $classes[] = 'tcm-vendor-group-' . sanitize_html_class($b2b_group_id);

                // OPTIONAL :: Group Name
                $group_name = get_the_title($b2b_group_id);
                if (!empty($group_name)) {
                    $classes[] = 'tcm-vendor-' . sanitize_html_class(strtolower(str_replace(' ', '-', $group_name)));
                }
            }

            $classes[] = 'tcm-logged-in';
        } else {
            $classes[] = 'tcm-guest';
        }

        return $classes;
    }
}
