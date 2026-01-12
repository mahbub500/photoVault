<?php
/**
 * Share Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

class ShareController {
    
    public function share() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $item_type = sanitize_text_field($_POST['item_type'] ?? '');
        $item_id = intval($_POST['item_id'] ?? 0);
        $share_with = intval($_POST['share_with'] ?? 0);
        $permission = sanitize_text_field($_POST['permission'] ?? 'view');
        
        if (empty($item_type) || empty($item_id) || empty($share_with)) {
            wp_send_json_error(['message' => __('Invalid share parameters', 'photovault')]);
        }
        
        // Verify ownership
        if (!$this->verify_ownership($item_type, $item_id, $user_id)) {
            wp_send_json_error(['message' => __('You do not own this item', 'photovault')]);
        }
        
        $table = $wpdb->prefix . 'pv_shares';
        $result = $wpdb->insert($table, [
            'item_type' => $item_type,
            'item_id' => $item_id,
            'shared_by' => $user_id,
            'shared_with' => $share_with,
            'permission' => $permission
        ]);
        
        if ($result) {
            wp_send_json_success(['message' => __('Shared successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to share', 'photovault')]);
        }
    }
    
    public function unshare() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $share_id = intval($_POST['share_id'] ?? 0);
        
        // Verify user owns the share
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT shared_by FROM {$wpdb->prefix}pv_shares WHERE id = %d",
            $share_id
        ));
        
        if ($owner != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $deleted = $wpdb->delete("{$wpdb->prefix}pv_shares", ['id' => $share_id]);
        
        if ($deleted) {
            wp_send_json_success(['message' => __('Unshared successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to unshare', 'photovault')]);
        }
    }
    
    private function verify_ownership($item_type, $item_id, $user_id) {
        global $wpdb;
        
        $table = $item_type === 'image' 
            ? $wpdb->prefix . 'pv_images' 
            : $wpdb->prefix . 'pv_albums';
        
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table WHERE id = %d",
            $item_id
        ));
        
        return $owner == $user_id || current_user_can('manage_options');
    }
}