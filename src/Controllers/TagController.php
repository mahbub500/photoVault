<?php
/**
 * Tag Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

class TagController {
    
    public function add_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $image_id = intval($_POST['image_id'] ?? 0);
        $tag_name = sanitize_text_field($_POST['tag_name'] ?? '');
        
        if (empty($tag_name)) {
            wp_send_json_error(['message' => __('Tag name is required', 'photovault')]);
        }
        
        $slug = sanitize_title($tag_name);
        
        // Get or create tag
        $tag_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}pv_tags WHERE slug = %s",
            $slug
        ));
        
        if (!$tag_id) {
            $wpdb->insert("{$wpdb->prefix}pv_tags", [
                'name' => $tag_name,
                'slug' => $slug
            ]);
            $tag_id = $wpdb->insert_id;
        }
        
        // Link tag to image
        $wpdb->replace("{$wpdb->prefix}pv_image_tag", [
            'image_id' => $image_id,
            'tag_id' => $tag_id
        ]);
        
        wp_send_json_success([
            'tag_id' => $tag_id,
            'tag_name' => $tag_name,
            'message' => __('Tag added successfully', 'photovault')
        ]);
    }
    
    public function get_tags() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        
        $tags = $wpdb->get_results(
            "SELECT t.*, COUNT(it.image_id) as usage_count 
             FROM {$wpdb->prefix}pv_tags t
             LEFT JOIN {$wpdb->prefix}pv_image_tag it ON t.id = it.tag_id
             GROUP BY t.id
             ORDER BY t.name ASC"
        );
        
        wp_send_json_success($tags);
    }
    
    public function remove_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $image_id = intval($_POST['image_id'] ?? 0);
        $tag_id = intval($_POST['tag_id'] ?? 0);
        
        $deleted = $wpdb->delete("{$wpdb->prefix}pv_image_tag", [
            'image_id' => $image_id,
            'tag_id' => $tag_id
        ]);
        
        if ($deleted) {
            wp_send_json_success(['message' => __('Tag removed successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove tag', 'photovault')]);
        }
    }
}