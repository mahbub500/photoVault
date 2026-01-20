<?php
/**
 * Enhanced Album Controller
 *
 * @package PhotoVault
 */
namespace PhotoVault\Controllers;

use PhotoVault\Models\Album;
use PhotoVault\Helpers\ImageHelper;

class AlbumController {
    
    private $album_model;
    
    public function __construct() {
        $this->album_model = new Album();
        $this->register_ajax_actions();
    }
    
    /**
     * Register AJAX actions
     */
    private function register_ajax_actions() {
        add_action('wp_ajax_pv_create_album', [$this, 'create']);
        add_action('wp_ajax_pv_get_albums', [$this, 'get_albums']);
        add_action('wp_ajax_pv_get_album_details', [$this, 'get_album_details']);
        add_action('wp_ajax_pv_update_album', [$this, 'update']);
        add_action('wp_ajax_pv_delete_album', [$this, 'delete']);
        add_action('wp_ajax_pv_remove_image_from_album', [$this, 'remove_image_from_album']);
        add_action('wp_ajax_pv_add_image_to_album', [$this, 'add_image_to_album']);
        add_action('wp_ajax_pv_add_multiple_images', [$this, 'add_multiple_images']);
        add_action('wp_ajax_pv_set_album_cover', [$this, 'set_album_cover']);
        add_action('wp_ajax_pv_reorder_album_images', [$this, 'reorder_images']);
        add_action('wp_ajax_pv_get_album_images', [$this, 'get_album_images']);
        add_action('wp_ajax_pv_duplicate_album', [$this, 'duplicate_album']);
        add_action('wp_ajax_pv_search_albums', [$this, 'search_albums']);
        add_action('wp_ajax_pv_get_album_stats', [$this, 'get_album_stats']);
    }
    
    /**
     * Helper: Get image URL
     */
    private function get_image_url($attachment_id, $size = 'medium') {
        if (class_exists('PhotoVault\\Helpers\\ImageHelper')) {
            return ImageHelper::get_image_url($attachment_id, $size);
        }
        
        $url = wp_get_attachment_image_url($attachment_id, $size);
        if (!$url) {
            $url = wp_get_attachment_url($attachment_id);
        }
        
        return $url ?: '';
    }
    
    /**
     * Helper: Process album data for response
     */
    private function process_album_data($album) {
        if (!$album) {
            return null;
        }
        
        if (empty($album->cover_image_url) && !empty($album->cover_image_id)) {
            global $wpdb;
            $table_images = $wpdb->prefix . 'pv_images';
            
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT attachment_id FROM {$table_images} WHERE id = %d",
                $album->cover_image_id
            ));
            
            if ($attachment_id) {
                $album->cover_image_url = $this->get_image_url($attachment_id, 'medium');
            }
        }
        
        // Ensure numeric values
        $album->image_count = isset($album->image_count) ? (int) $album->image_count : 0;
        $album->id = (int) $album->id;
        $album->user_id = (int) $album->user_id;
        
        return $album;
    }
    
    /**
     * Helper: Process albums array
     */
    private function process_albums_data($albums) {
        if (!$albums || !is_array($albums)) {
            return [];
        }
        
        return array_map([$this, 'process_album_data'], $albums);
    }
    
    /**
     * Helper: Validate album access
     */
    private function validate_album_access($album_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $this->album_model->user_owns_album($album_id, $user_id);
    }
    
    /**
     * Create album
     */
    public function create() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            if (!current_user_can('photovault_manage_albums')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $name = sanitize_text_field($_POST['name'] ?? '');
            
            if (empty($name)) {
                wp_send_json_error(['message' => __('Album name is required', 'photovault')], 400);
            }
            
            $slug = sanitize_title($name);
            $user_id = get_current_user_id();
            
            // Check for duplicate slug
            $existing = $this->album_model->get_by_slug($slug, $user_id);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
            
            $data = [
                'user_id' => $user_id,
                'name' => $name,
                'slug' => $slug,
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'visibility' => in_array($_POST['visibility'] ?? '', ['private', 'shared', 'public']) 
                    ? $_POST['visibility'] 
                    : 'private',
                'sort_order' => sanitize_text_field($_POST['sort_order'] ?? 'date_desc')
            ];
            
            $album_id = $this->album_model->create($data);
            
            if ($album_id) {
                $album = $this->album_model->get_album_details($album_id);
                wp_send_json_success([
                    'album_id' => $album_id,
                    'album' => $this->process_album_data($album),
                    'message' => __('Album created successfully', 'photovault')
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to create album', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Album Create Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'photovault')], 500);
        }
    }
    
    /**
     * Get albums
     */
    public function get_albums() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $user_id = get_current_user_id();
            $params = ['user_id' => $user_id];
            
            // Admin can see all albums
            if (current_user_can('manage_options') && !empty($_POST['all_albums'])) {
                unset($params['user_id']);
            }
            
            // Filter by visibility
            if (!empty($_POST['visibility'])) {
                $params['visibility'] = sanitize_text_field($_POST['visibility']);
            }
            
            $albums = $this->album_model->get_albums($params);
            $albums = $this->process_albums_data($albums);
            
            wp_send_json_success($albums);
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Albums Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading albums', 'photovault')], 500);
        }
    }
    
    /**
     * Search albums
     */
    public function search_albums() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $search_term = sanitize_text_field($_POST['search'] ?? '');
            $user_id = get_current_user_id();
            
            $albums = $this->album_model->search_albums($search_term, $user_id);
            $albums = $this->process_albums_data($albums);
            
            wp_send_json_success($albums);
            
        } catch (Exception $e) {
            error_log('PhotoVault Search Albums Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Search failed', 'photovault')], 500);
        }
    }
    
    /**
     * Get album details
     */
    public function get_album_details() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            
            if (!$album_id) {
                wp_send_json_error(['message' => __('Invalid album ID', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $album = $this->album_model->get_album_details($album_id);
            
            if ($album) {
                $album = $this->process_album_data($album);
                wp_send_json_success($album);
            } else {
                wp_send_json_error(['message' => __('Album not found', 'photovault')], 404);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Album Details Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading album', 'photovault')], 500);
        }
    }
    
    /**
     * Get album images
     */
    public function get_album_images() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            
            if (!$album_id) {
                wp_send_json_error(['message' => __('Invalid album ID', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            global $wpdb;
            $table_images = $wpdb->prefix . 'pv_images';
            $table_image_album = $wpdb->prefix . 'pv_image_album';
            
            $images_data = $wpdb->get_results($wpdb->prepare(
                "SELECT i.*, ia.position 
                 FROM {$table_image_album} ia
                 LEFT JOIN {$table_images} i ON ia.image_id = i.id
                 WHERE ia.album_id = %d
                 ORDER BY ia.position ASC, ia.added_date DESC",
                $album_id
            ));
            
            $images = [];
            if ($images_data) {
                foreach ($images_data as $img) {
                    $images[] = [
                        'id' => (int) $img->id,
                        'attachment_id' => (int) $img->attachment_id,
                        'title' => $img->title ?: __('Untitled', 'photovault'),
                        'description' => $img->description,
                        'thumbnail_url' => $this->get_image_url($img->attachment_id, 'thumbnail'),
                        'medium_url' => $this->get_image_url($img->attachment_id, 'medium'),
                        'large_url' => $this->get_image_url($img->attachment_id, 'large'),
                        'full_url' => $this->get_image_url($img->attachment_id, 'full'),
                        'position' => (int) $img->position
                    ];
                }
            }
            
            wp_send_json_success($images);
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Album Images Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading images', 'photovault')], 500);
        }
    }
    
    /**
     * Update album
     */
    public function update() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            
            if (!$album_id) {
                wp_send_json_error(['message' => __('Invalid album ID', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $data = [];
            
            if (isset($_POST['name'])) {
                $name = sanitize_text_field($_POST['name']);
                if (empty($name)) {
                    wp_send_json_error(['message' => __('Album name cannot be empty', 'photovault')], 400);
                }
                $data['name'] = $name;
            }
            
            if (isset($_POST['description'])) {
                $data['description'] = sanitize_textarea_field($_POST['description']);
            }
            
            if (isset($_POST['visibility'])) {
                $visibility = sanitize_text_field($_POST['visibility']);
                if (in_array($visibility, ['private', 'shared', 'public'])) {
                    $data['visibility'] = $visibility;
                }
            }
            
            if (isset($_POST['sort_order'])) {
                $data['sort_order'] = sanitize_text_field($_POST['sort_order']);
            }
            
            if (empty($data)) {
                wp_send_json_error(['message' => __('No data to update', 'photovault')], 400);
            }
            
            $result = $this->album_model->update($album_id, $data);
            
            if ($result !== false) {
                $album = $this->album_model->get_album_details($album_id);
                wp_send_json_success([
                    'album' => $this->process_album_data($album),
                    'message' => __('Album updated successfully', 'photovault')
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to update album', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Update Album Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error updating album', 'photovault')], 500);
        }
    }
    
    /**
     * Delete album
     */
    public function delete() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            
            if (!$album_id) {
                wp_send_json_error(['message' => __('Invalid album ID', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            if ($this->album_model->delete($album_id)) {
                wp_send_json_success(['message' => __('Album deleted successfully', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to delete album', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Delete Album Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error deleting album', 'photovault')], 500);
        }
    }
    
    /**
     * Add image to album
     */
    public function add_image_to_album() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            $position = intval($_POST['position'] ?? 0);
            
            if (!$album_id || !$image_id) {
                wp_send_json_error(['message' => __('Missing required data', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $result = $this->album_model->add_image($album_id, $image_id, $position);
            
            if ($result !== false) {
                global $wpdb;
                $table_images = $wpdb->prefix . 'pv_images';
                $image = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_images} WHERE id = %d",
                    $image_id
                ));
                
                $image_data = null;
                if ($image) {
                    $image_data = [
                        'id' => (int) $image->id,
                        'attachment_id' => (int) $image->attachment_id,
                        'title' => $image->title,
                        'thumbnail_url' => $this->get_image_url($image->attachment_id, 'thumbnail'),
                        'medium_url' => $this->get_image_url($image->attachment_id, 'medium')
                    ];
                }
                
                wp_send_json_success([
                    'message' => __('Image added to album', 'photovault'),
                    'image' => $image_data
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to add image or image already in album', 'photovault')], 400);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Add Image Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error adding image', 'photovault')], 500);
        }
    }
    
    /**
     * Add multiple images to album
     */
    public function add_multiple_images() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            $image_ids = isset($_POST['image_ids']) ? array_map('intval', (array) $_POST['image_ids']) : [];
            
            if (!$album_id || empty($image_ids)) {
                wp_send_json_error(['message' => __('Missing required data', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $added = 0;
            $skipped = 0;
            
            foreach ($image_ids as $image_id) {
                $result = $this->album_model->add_image($album_id, $image_id);
                if ($result !== false) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
            
            wp_send_json_success([
                'added' => $added,
                'skipped' => $skipped,
                'message' => sprintf(
                    __('%d images added, %d skipped', 'photovault'),
                    $added,
                    $skipped
                )
            ]);
            
        } catch (Exception $e) {
            error_log('PhotoVault Add Multiple Images Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error adding images', 'photovault')], 500);
        }
    }
    
    /**
     * Remove image from album
     */
    public function remove_image_from_album() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            
            if (!$album_id || !$image_id) {
                wp_send_json_error(['message' => __('Missing required data', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            if ($this->album_model->remove_image($album_id, $image_id)) {
                wp_send_json_success(['message' => __('Image removed from album', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to remove image', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Remove Image Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error removing image', 'photovault')], 500);
        }
    }
    
    /**
     * Set album cover image
     */
    public function set_album_cover() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            $image_id = intval($_POST['image_id'] ?? 0);
            
            if (!$album_id || !$image_id) {
                wp_send_json_error(['message' => __('Missing required data', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            if ($this->album_model->set_cover_image($album_id, $image_id)) {
                wp_send_json_success(['message' => __('Cover image updated', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to update cover image', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Set Cover Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error setting cover', 'photovault')], 500);
        }
    }
    
    /**
     * Reorder images in album
     */
    public function reorder_images() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            $image_order = json_decode(stripslashes($_POST['image_order'] ?? '[]'), true);
            
            if (!$album_id || !is_array($image_order)) {
                wp_send_json_error(['message' => __('Invalid data', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            if ($this->album_model->reorder_images($album_id, $image_order)) {
                wp_send_json_success(['message' => __('Images reordered successfully', 'photovault')]);
            } else {
                wp_send_json_error(['message' => __('Failed to reorder images', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Reorder Images Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error reordering images', 'photovault')], 500);
        }
    }
    
    /**
     * Duplicate album
     */
    public function duplicate_album() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $album_id = intval($_POST['album_id'] ?? 0);
            
            if (!$album_id) {
                wp_send_json_error(['message' => __('Invalid album ID', 'photovault')], 400);
            }
            
            if (!$this->validate_album_access($album_id)) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')], 403);
            }
            
            $new_album_id = $this->album_model->duplicate($album_id);
            
            if ($new_album_id) {
                $album = $this->album_model->get_album_details($new_album_id);
                wp_send_json_success([
                    'album_id' => $new_album_id,
                    'album' => $this->process_album_data($album),
                    'message' => __('Album duplicated successfully', 'photovault')
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to duplicate album', 'photovault')], 500);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Duplicate Album Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error duplicating album', 'photovault')], 500);
        }
    }
    
    /**
     * Get album statistics
     */
    public function get_album_stats() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $user_id = get_current_user_id();
            $stats = $this->album_model->get_user_stats($user_id);
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            error_log('PhotoVault Get Stats Error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error loading statistics', 'photovault')], 500);
        }
    }
}