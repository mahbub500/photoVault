<?php
/**
 * Image Model
 *
 * @package PhotoVault
 */

namespace PhotoVault\Models;

class Image {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pv_images';
    }
    
    /**
     * Create new image record
     *
     * @param array $data Image data
     * @return int|false Image ID or false
     */
    public function create($data) {
        global $wpdb;
        
        $defaults = [
            'user_id' => get_current_user_id(),
            'title' => '',
            'description' => '',
            'visibility' => 'private',
            'upload_date' => current_time('mysql'),
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($this->table, $data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get images with filters
     *
     * @param array $params Query parameters
     * @return array Images and pagination info
     */
    public function get_images($params = []) {
        global $wpdb;
        
        $defaults = [
            'user_id' => get_current_user_id(),
            'album_id' => 0,
            'tag_id' => 0,
            'search' => '',
            'page' => 1,
            'per_page' => 20,
            'sort' => 'date_desc',
            'visibility' => '',
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        $offset = ($params['page'] - 1) * $params['per_page'];
        
        // Build query
        $where = [];
        $join = [];
        $query_params = [];
        
        // User filter (own images + shared with user)
        $where[] = "(i.user_id = %d OR s.shared_with = %d)";
        $query_params[] = $params['user_id'];
        $query_params[] = $params['user_id'];
        
        $join[] = "LEFT JOIN {$wpdb->prefix}pv_shares s ON (s.item_type = 'image' AND s.item_id = i.id)";
        
        // Album filter
        if ($params['album_id'] > 0) {
            $join[] = "INNER JOIN {$wpdb->prefix}pv_image_album ia ON i.id = ia.image_id";
            $where[] = "ia.album_id = %d";
            $query_params[] = $params['album_id'];
        }
        
        // Tag filter
        if ($params['tag_id'] > 0) {
            $join[] = "INNER JOIN {$wpdb->prefix}pv_image_tag it ON i.id = it.image_id";
            $where[] = "it.tag_id = %d";
            $query_params[] = $params['tag_id'];
        }
        
        // Search filter
        if (!empty($params['search'])) {
            $where[] = "(i.title LIKE %s OR i.description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        // Visibility filter
        if (!empty($params['visibility'])) {
            $where[] = "i.visibility = %s";
            $query_params[] = $params['visibility'];
        }
        
        // Sorting
        $order_by = $this->get_order_by($params['sort']);
        
        // Build final query
        $sql = "SELECT DISTINCT i.*, p.guid as url 
                FROM {$this->table} i
                LEFT JOIN {$wpdb->posts} p ON i.attachment_id = p.ID
                " . implode(' ', $join);
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " {$order_by} LIMIT %d OFFSET %d";
        $query_params[] = $params['per_page'];
        $query_params[] = $offset;
        
        // Get images
        $images = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT i.id) 
                      FROM {$this->table} i
                      " . implode(' ', $join);
        
        if (!empty($where)) {
            $count_sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $count_params = array_slice($query_params, 0, count($query_params) - 2);
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $count_params));
        
        // Enhance images with additional data
        foreach ($images as &$image) {
            $image->tags = $this->get_image_tags($image->id);
            $image->thumbnail = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $image->albums = $this->get_image_albums($image->id);
        }
        
        return [
            'images' => $images,
            'total' => (int) $total,
            'page' => $params['page'],
            'per_page' => $params['per_page'],
            'total_pages' => ceil($total / $params['per_page']),
        ];
    }
    
    /**
     * Get single image
     *
     * @param int $image_id Image ID
     * @return object|null Image data
     */
    public function get($image_id) {
        global $wpdb;
        
        $sql = "SELECT i.*, p.guid as url 
                FROM {$this->table} i
                LEFT JOIN {$wpdb->posts} p ON i.attachment_id = p.ID
                WHERE i.id = %d";
        
        $image = $wpdb->get_row($wpdb->prepare($sql, $image_id));
        
        if ($image) {
            $image->tags = $this->get_image_tags($image_id);
            $image->thumbnail = wp_get_attachment_image_url($image->attachment_id, 'medium');
            $image->albums = $this->get_image_albums($image_id);
        }
        
        return $image;
    }
    
    /**
     * Update image
     *
     * @param int $image_id Image ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($image_id, $data) {
        global $wpdb;
        
        $data['modified_date'] = current_time('mysql');
        
        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $image_id],
            null,
            ['%d']
        ) !== false;
    }
    
    /**
     * Delete image
     *
     * @param int $image_id Image ID
     * @return bool Success
     */
    public function delete($image_id) {
        global $wpdb;
        
        // Get attachment ID
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attachment_id FROM {$this->table} WHERE id = %d",
            $image_id
        ));
        
        // Delete from database
        $wpdb->delete($this->table, ['id' => $image_id]);
        $wpdb->delete("{$wpdb->prefix}pv_image_album", ['image_id' => $image_id]);
        $wpdb->delete("{$wpdb->prefix}pv_image_tag", ['image_id' => $image_id]);
        $wpdb->delete("{$wpdb->prefix}pv_shares", [
            'item_type' => 'image',
            'item_id' => $image_id
        ]);
        
        // Delete WordPress attachment
        if ($attachment_id) {
            wp_delete_attachment($attachment_id, true);
        }
        
        return true;
    }
    
    /**
     * Add tags to image
     *
     * @param int $image_id Image ID
     * @param array $tags Tag names
     * @return bool Success
     */
    public function add_tags($image_id, $tags) {
        global $wpdb;
        
        foreach ($tags as $tag_name) {
            $tag_name = trim($tag_name);
            if (empty($tag_name)) continue;
            
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
            
            // Update tag count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}pv_tags 
                 SET count = (SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = %d)
                 WHERE id = %d",
                $tag_id,
                $tag_id
            ));
        }
        
        return true;
    }
    
    /**
     * Add image to album
     *
     * @param int $image_id Image ID
     * @param int $album_id Album ID
     * @return bool Success
     */
    public function add_to_album($image_id, $album_id) {
        global $wpdb;
        
        return $wpdb->replace("{$wpdb->prefix}pv_image_album", [
            'image_id' => $image_id,
            'album_id' => $album_id
        ]) !== false;
    }
    
    /**
     * Get image tags
     *
     * @param int $image_id Image ID
     * @return array Tags
     */
    public function get_image_tags($image_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$wpdb->prefix}pv_tags t
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON t.id = it.tag_id
             WHERE it.image_id = %d",
            $image_id
        ));
    }
    
    /**
     * Get image albums
     *
     * @param int $image_id Image ID
     * @return array Albums
     */
    public function get_image_albums($image_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}pv_albums a
             INNER JOIN {$wpdb->prefix}pv_image_album ia ON a.id = ia.album_id
             WHERE ia.image_id = %d",
            $image_id
        ));
    }
    
    /**
     * Check if user owns image
     *
     * @param int $image_id Image ID
     * @param int $user_id User ID
     * @return bool
     */
    public function user_owns_image($image_id, $user_id) {
        global $wpdb;
        
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table} WHERE id = %d",
            $image_id
        ));
        
        return $owner_id == $user_id || current_user_can('manage_options');
    }
    
    /**
     * Get order by clause
     *
     * @param string $sort Sort option
     * @return string SQL ORDER BY clause
     */
    private function get_order_by($sort) {
        $order_map = [
            'date_desc' => 'ORDER BY i.upload_date DESC',
            'date_asc' => 'ORDER BY i.upload_date ASC',
            'title_asc' => 'ORDER BY i.title ASC',
            'title_desc' => 'ORDER BY i.title DESC',
            'size_desc' => 'ORDER BY i.file_size DESC',
            'size_asc' => 'ORDER BY i.file_size ASC',
        ];
        
        return $order_map[$sort] ?? $order_map['date_desc'];
    }
}