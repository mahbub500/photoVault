<?php
/**
 * Tag Controller - Fixed for Admin Integration
 *
 * @package PhotoVault
 */
namespace PhotoVault\Controllers;

use PhotoVault\Models\Tag;

class TagController {
    
    private $tag_model;
    
    public function __construct() {
        $this->tag_model = new Tag();
    }
    
    /**
     * Add tag to image
     */
    public function add_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $tag_name = sanitize_text_field($_POST['tag_name'] ?? '');
        
        if (empty($tag_name)) {
            wp_send_json_error(['message' => __('Tag name is required', 'photovault')]);
        }
        
        // Check if user has permission
        if (!current_user_can('photovault_edit_images')) {
            wp_send_json_error(['message' => __('You do not have permission to add tags', 'photovault')]);
        }
        
        // Get or create tag
        $tag_id = $this->tag_model->get_or_create($tag_name);
        
        if (!$tag_id) {
            wp_send_json_error(['message' => __('Failed to create tag', 'photovault')]);
        }
        
        // Link tag to image
        $result = $this->tag_model->add_to_image($image_id, $tag_id);
        
        if ($result) {
            $tag = $this->tag_model->get($tag_id);
            wp_send_json_success([
                'tag_id' => $tag_id,
                'tag_name' => $tag->name,
                'tag_slug' => $tag->slug,
                'tag_color' => $tag->color,
                'message' => __('Tag added successfully', 'photovault')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to add tag to image', 'photovault')]);
        }
    }
    
    /**
     * Get all tags with usage count
     */
    public function get_tags() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $orderby = sanitize_text_field($_POST['orderby'] ?? 'name');
        $order = sanitize_text_field($_POST['order'] ?? 'ASC');
        $limit = intval($_POST['limit'] ?? 0);
        
        $tags = $this->tag_model->get_tags([
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'limit' => $limit
        ]);
        
        wp_send_json_success($tags);
    }
    
    /**
     * Get popular tags
     */
    public function get_popular_tags() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $limit = intval($_POST['limit'] ?? 10);
        $tags = $this->tag_model->get_popular($limit);
        
        wp_send_json_success($tags);
    }
    
    /**
     * Remove tag from image
     */
    public function remove_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $tag_id = intval($_POST['tag_id'] ?? 0);
        
        if (!current_user_can('photovault_edit_images')) {
            wp_send_json_error(['message' => __('You do not have permission to remove tags', 'photovault')]);
        }
        
        $result = $this->tag_model->remove_from_image($image_id, $tag_id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Tag removed successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove tag', 'photovault')]);
        }
    }
    
    /**
     * Get images by tag (AJAX)
     */
    public function get_images_by_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        if (!$tag_id) {
            wp_send_json_error(['message' => __('Tag ID is required', 'photovault')]);
        }
        
        // Get tag details
        $tag = $this->tag_model->get($tag_id);
        
        if (!$tag) {
            wp_send_json_error(['message' => __('Tag not found', 'photovault')]);
        }
        
        // Get images for this tag with pagination
        global $wpdb;
        
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name as author_name
             FROM {$wpdb->prefix}pv_images i
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON i.id = it.image_id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE it.tag_id = %d
             AND (i.visibility = 'public' OR i.user_id = %d)
             ORDER BY i.upload_date DESC
             LIMIT %d OFFSET %d",
            $tag_id,
            get_current_user_id(),
            $limit,
            $offset
        ));
        
        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}pv_images i
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON i.id = it.image_id
             WHERE it.tag_id = %d
             AND (i.visibility = 'public' OR i.user_id = %d)",
            $tag_id,
            get_current_user_id()
        ));
        
        // Format images with thumbnail URLs
        foreach ($images as &$image) {
            $image->thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $image->full_url = wp_get_attachment_image_url($image->attachment_id, 'full');
            $image->formatted_date = date_i18n(get_option('date_format'), strtotime($image->upload_date));
        }
        
        wp_send_json_success([
            'tag' => $tag,
            'images' => $images,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total
        ]);
    }
    
    /**
     * Get data for tag view page (for admin template)
     * This method returns data instead of rendering directly
     */
    public function get_tag_view_data() {
        $tag_id = intval($_GET['tag_id'] ?? 0);
        
        if (!$tag_id) {
            return [
                'error' => __('Tag ID is required', 'photovault'),
                'tag' => null,
                'images' => []
            ];
        }
        
        // Get tag
        $tag = $this->tag_model->get($tag_id);
        
        if (!$tag) {
            return [
                'error' => __('Tag not found', 'photovault'),
                'tag' => null,
                'images' => []
            ];
        }
        
        // Get images (initial load - first 20)
        global $wpdb;
        
        // Remove visibility check in admin - show all images with this tag
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name as author_name
             FROM {$wpdb->prefix}pv_images i
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON i.id = it.image_id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE it.tag_id = %d
             ORDER BY i.upload_date DESC
             LIMIT 20",
            $tag_id
        ));
        
        // Format images with proper URLs
        foreach ($images as &$image) {
            // Try to get attachment URLs
            $thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $full_url = wp_get_attachment_image_url($image->attachment_id, 'full');
            
            // Fallback to attachment URL if specific size not available
            if (!$thumbnail_url) {
                $thumbnail_url = wp_get_attachment_url($image->attachment_id);
            }
            if (!$full_url) {
                $full_url = wp_get_attachment_url($image->attachment_id);
            }
            
            $image->thumbnail_url = $thumbnail_url ?: '';
            $image->full_url = $full_url ?: '';
            $image->formatted_date = date_i18n(get_option('date_format'), strtotime($image->upload_date));
        }
        
        return [
            'error' => null,
            'tag' => $tag,
            'images' => $images
        ];
    }
    
    /**
     * Update tag details
     */
    public function update_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to update tags', 'photovault')]);
        }
        
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $color = sanitize_hex_color($_POST['color'] ?? '#667eea');
        
        if (!$tag_id || empty($name)) {
            wp_send_json_error(['message' => __('Tag ID and name are required', 'photovault')]);
        }
        
        $result = $this->tag_model->update($tag_id, [
            'name' => $name,
            'color' => $color
        ]);
        
        if ($result) {
            $tag = $this->tag_model->get($tag_id);
            wp_send_json_success([
                'tag' => $tag,
                'message' => __('Tag updated successfully', 'photovault')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to update tag', 'photovault')]);
        }
    }
    
    /**
     * Delete tag
     */
    public function delete_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to delete tags', 'photovault')]);
        }
        
        $tag_id = intval($_POST['tag_id'] ?? 0);
        
        if (!$tag_id) {
            wp_send_json_error(['message' => __('Tag ID is required', 'photovault')]);
        }
        
        $result = $this->tag_model->delete($tag_id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Tag deleted successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete tag', 'photovault')]);
        }
    }
    
    /**
     * Get tags for specific image
     */
    public function get_image_tags() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Image ID is required', 'photovault')]);
        }
        
        $tags = $this->tag_model->get_image_tags($image_id);
        
        wp_send_json_success($tags);
    }

    /**
     * Get all user images (simple version)
     */
    public function get_all_user_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT i.id, i.attachment_id, i.title, i.upload_date
            FROM {$wpdb->prefix}pv_images i
            WHERE i.user_id = %d
            ORDER BY i.upload_date DESC",
            $user_id
        ));
        
        $formatted_images = [];
        
        foreach ($images as $image) {
            $thumbnail_url = wp_get_attachment_image_url($image->attachment_id, 'thumbnail');
            if (!$thumbnail_url) {
                $thumbnail_url = wp_get_attachment_url($image->attachment_id);
            }
            
            $formatted_images[] = [
                'id' => $image->id,
                'title' => $image->title,
                'thumbnail_url' => $thumbnail_url
            ];
        }
        
        wp_send_json_success($formatted_images);
    }

    /**
     * Bulk assign images to tag
     */
    public function bulk_assign_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('photovault_edit_images')) {
            wp_send_json_error(['message' => __('No permission', 'photovault')]);
        }
        
        $tag_id = intval($_POST['tag_id'] ?? 0);
        $image_ids = $_POST['image_ids'] ?? [];
        
        if (!$tag_id || empty($image_ids)) {
            wp_send_json_error(['message' => __('Missing data', 'photovault')]);
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $success_count = 0;
        
        foreach ($image_ids as $image_id) {
            $image_id = intval($image_id);
            
            // Verify ownership
            $owns = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}pv_images 
                 WHERE id = %d AND user_id = %d",
                $image_id, $user_id
            ));
            
            if ($owns) {
                $result = $this->tag_model->add_to_image($image_id, $tag_id);
                if ($result) $success_count++;
            }
        }
        
        // Get updated count
        $updated_tag = $this->tag_model->get($tag_id);
        
        wp_send_json_success([
            'message' => sprintf('%d images assigned', $success_count),
            'new_count' => $updated_tag->usage_count
        ]);
    }
}