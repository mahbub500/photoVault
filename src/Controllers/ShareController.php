<?php
/**
 * Share Controller - Complete Implementation
 *
 * @package PhotoVault
 */
namespace PhotoVault\Controllers;

use PhotoVault\Models\Share;

class ShareController {
    
    private $share_model;
    
    public function __construct() {
        $this->share_model = new Share();
        $this->register_ajax_actions();
    }
    
    /**
     * Register AJAX actions
     */
    private function register_ajax_actions() {
        add_action('wp_ajax_pv_share_item', [$this, 'share_item']);
        add_action('wp_ajax_pv_unshare_item', [$this, 'unshare_item']);
        add_action('wp_ajax_pv_get_item_shares', [$this, 'get_item_shares']);
        add_action('wp_ajax_pv_get_shared_with_me', [$this, 'get_shared_with_me']);
        add_action('wp_ajax_pv_search_users', [$this, 'search_users']);
        add_action('wp_ajax_pv_update_share_permission', [$this, 'update_share_permission']);
    }
    
    /**
     * Share item (album or image)
     */
    public function share_item() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $item_type = sanitize_text_field($_POST['item_type'] ?? '');
            $item_id = intval($_POST['item_id'] ?? 0);
            $share_with = intval($_POST['share_with'] ?? 0);
            $permission = sanitize_text_field($_POST['permission'] ?? 'view');
            
            // Validate inputs
            if (!in_array($item_type, ['album', 'image'])) {
                wp_send_json_error(['message' => __('Invalid item type', 'photovault')], 400);
            }
            
            if (!$item_id || !$share_with) {
                wp_send_json_error(['message' => __('Invalid parameters', 'photovault')], 400);
            }
            
            if (!in_array($permission, ['view', 'edit'])) {
                $permission = 'view';
            }
            
            // Verify ownership
            $user_id = get_current_user_id();
            if (!$this->verify_ownership($item_type, $item_id, $user_id)) {
                wp_send_json_error(['message' => __('You do not own this item', 'photovault')], 403);
            }
            
            // Check if sharing with self
            if ($share_with == $user_id) {
                wp_send_json_error(['message' => __('Cannot share with yourself', 'photovault')], 400);
            }
            
            // Create share
            $share_id = $this->share_model->create([
                'item_type' => $item_type,
                'item_id' => $item_id,
                'shared_by' => $user_id,
                'shared_with' => $share_with,
                'permission' => $permission
            ]);
            
            if ($share_id) {
                // Get user info
                $user = get_userdata($share_with);
                
                wp_send_json_success([
                    'share_id' => $share_id,
                    'shared_with_name' => $user ? $user->display_name : '',
                    'message' => sprintf(
                        __('Shared with %s successfully', 'photovault'),
                        $user ? $user->display_name : 'user'
                    )
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to share item', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Share Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'photovault')], 500);
        }
    }
    
    /**
     * Unshare item
     */
    public function unshare_item() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $share_id = intval($_POST['share_id'] ?? 0);
            
            if (!$share_id) {
                wp_send_json_error(['message' => __('Invalid share ID', 'photovault')], 400);
            }
            
            // Get share details
            global $wpdb;
            $table = $wpdb->prefix . 'pv_shares';
            $share = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $share_id
            ));
            
            if (!$share) {
                wp_send_json_error(['message' => __('Share not found', 'photovault')], 404);
            }
            
            // Verify permission
            $user_id = get_current_user_id();
            if ($share->shared_by != $user_id && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            // Delete share
            if ($this->share_model->delete($share_id)) {
                wp_send_json_success(['message' => __('Share removed successfully', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to remove share', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Unshare Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'photovault')], 500);
        }
    }
    
    /**
     * Get all shares for an item
     */
    public function get_item_shares() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $item_type = sanitize_text_field($_POST['item_type'] ?? '');
            $item_id = intval($_POST['item_id'] ?? 0);
            
            if (!$item_id || !in_array($item_type, ['album', 'image'])) {
                wp_send_json_error(['message' => __('Invalid parameters', 'photovault')], 400);
            }
            
            // Verify ownership
            $user_id = get_current_user_id();
            if (!$this->verify_ownership($item_type, $item_id, $user_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            // Get shares
            $shares = $this->share_model->get_item_shares($item_type, $item_id);
            
            wp_send_json_success($shares);
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Shares Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading shares', 'photovault')], 500);
        }
    }
    
    /**
     * Get items shared with current user
     */
    public function get_shared_with_me() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $item_type = sanitize_text_field($_POST['item_type'] ?? '');
            $user_id = get_current_user_id();
            
            $shares = $this->share_model->get_user_shares($user_id, $item_type);
            
            wp_send_json_success($shares);
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Shared With Me Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading shared items', 'photovault')], 500);
        }
    }
    
    /**
     * Search users for sharing
     */
    public function search_users() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $search = sanitize_text_field($_POST['search'] ?? '');
            $current_user_id = get_current_user_id();
            
            if (strlen($search) < 2) {
                wp_send_json_success([]);
                return;
            }
            
            // Search users
            $users = get_users([
                'search' => '*' . $search . '*',
                'search_columns' => ['user_login', 'user_email', 'display_name'],
                'number' => 10,
                'exclude' => [$current_user_id] // Exclude current user
            ]);
            
            $results = [];
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'username' => $user->user_login
                ];
            }
            
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            error_log('PhotoVault Search Users Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error searching users', 'photovault')], 500);
        }
    }
    
    /**
     * Update share permission
     */
    public function update_share_permission() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $share_id = intval($_POST['share_id'] ?? 0);
            $permission = sanitize_text_field($_POST['permission'] ?? 'view');
            
            if (!$share_id || !in_array($permission, ['view', 'edit'])) {
                wp_send_json_error(['message' => __('Invalid parameters', 'photovault')], 400);
            }
            
            // Get share
            global $wpdb;
            $table = $wpdb->prefix . 'pv_shares';
            $share = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $share_id
            ));
            
            if (!$share) {
                wp_send_json_error(['message' => __('Share not found', 'photovault')], 404);
            }
            
            // Verify ownership
            $user_id = get_current_user_id();
            if ($share->shared_by != $user_id && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            // Update permission
            if ($this->share_model->update($share_id, ['permission' => $permission])) {
                wp_send_json_success(['message' => __('Permission updated', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to update permission', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Update Permission Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'photovault')], 500);
        }
    }
    
    /**
     * Verify item ownership
     */
    private function verify_ownership($item_type, $item_id, $user_id) {
        global $wpdb;
        
        $table = $item_type === 'image' 
            ? $wpdb->prefix . 'pv_images' 
            : $wpdb->prefix . 'pv_albums';
        
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        return $owner == $user_id || current_user_can('manage_options');
    }
}