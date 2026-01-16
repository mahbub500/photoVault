<?php
/**
 * Tag Model
 *
 * @package PhotoVault
 */

namespace PhotoVault\Models;

class Tag {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pv_tags';
    }
    
    /**
     * Create new tag
     *
     * @param array $data Tag data
     * @return int|false Tag ID or false
     */
    public function create($data) {
        global $wpdb;
        
        $defaults = [
            'name' => '',
            'slug' => '',
            'color' => '#667eea'
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get all tags
     *
     * @param array $params Query parameters
     * @return array Tags
     */
    public function get_tags($params = []) {
        global $wpdb;
        
        $defaults = [
            'search' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 0
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        $where = [];
        $query_params = [];
        
        // Search filter
        if (!empty($params['search'])) {
            $where[] = "name LIKE %s";
            $query_params[] = '%' . $wpdb->esc_like($params['search']) . '%';
        }
        
        // Build query
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = t.id) as usage_count
                FROM {$this->table} t";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        // Order by
        $allowed_orderby = ['name', 'slug', 'usage_count'];
        $orderby = in_array($params['orderby'], $allowed_orderby) ? $params['orderby'] : 'name';
        $order = strtoupper($params['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY {$orderby} {$order}";
        
        // Limit
        if ($params['limit'] > 0) {
            $sql .= " LIMIT " . intval($params['limit']);
        }
        
        if (!empty($query_params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names use $wpdb->prefix, all user inputs properly prepared.
            return $wpdb->get_results($wpdb->prepare($sql, $query_params));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names use $wpdb->prefix, no user inputs in query.
            return $wpdb->get_results($sql);
        }
    }
    
    /**
     * Get single tag
     *
     * @param int $tag_id Tag ID
     * @return object|null Tag data
     */
    public function get($tag_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = t.id) as usage_count
                FROM {$this->table} t
                WHERE t.id = %d";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query properly prepared above.
        return $wpdb->get_row($wpdb->prepare($sql, $tag_id));
    }
    
    /**
     * Get tag by slug
     *
     * @param string $slug Tag slug
     * @return object|null Tag data
     */
    public function get_by_slug($slug) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = t.id) as usage_count
                FROM {$this->table} t
                WHERE t.slug = %s";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query properly prepared above.
        return $wpdb->get_row($wpdb->prepare($sql, $slug));
    }
    
    /**
     * Update tag
     *
     * @param int $tag_id Tag ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($tag_id, $data) {
        global $wpdb;
        
        // Auto-update slug if name changed
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $tag_id]
        ) !== false;
    }
    
    /**
     * Delete tag
     *
     * @param int $tag_id Tag ID
     * @return bool Success
     */
    public function delete($tag_id) {
        global $wpdb;
        
        // Delete tag
        $wpdb->delete($this->table, ['id' => $tag_id]);
        
        // Remove tag associations from images
        $wpdb->delete($wpdb->prefix . 'pv_image_tag', ['tag_id' => $tag_id]);
        
        return true;
    }
    
    /**
     * Get or create tag
     *
     * @param string $name Tag name
     * @return int Tag ID
     */
    public function get_or_create($name) {
        global $wpdb;
        
        $name = trim($name);
        if (empty($name)) {
            return 0;
        }
        
        $slug = sanitize_title($name);
        
        // Try to get existing tag
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $tag_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE slug = %s",
            $slug
        ));
        
        // Create if doesn't exist
        if (!$tag_id) {
            $wpdb->insert($this->table, [
                'name' => $name,
                'slug' => $slug
            ]);
            $tag_id = $wpdb->insert_id;
        }
        
        return $tag_id;
    }
    
    /**
     * Add tag to image
     *
     * @param int $image_id Image ID
     * @param int $tag_id Tag ID
     * @return bool Success
     */
    public function add_to_image($image_id, $tag_id) {
        global $wpdb;
        
        return $wpdb->replace($wpdb->prefix . 'pv_image_tag', [
            'image_id' => $image_id,
            'tag_id' => $tag_id
        ]) !== false;
    }
    
    /**
     * Remove tag from image
     *
     * @param int $image_id Image ID
     * @param int $tag_id Tag ID
     * @return bool Success
     */
    public function remove_from_image($image_id, $tag_id) {
        global $wpdb;
        
        return $wpdb->delete($wpdb->prefix . 'pv_image_tag', [
            'image_id' => $image_id,
            'tag_id' => $tag_id
        ]) !== false;
    }
    
    /**
     * Get tags for image
     *
     * @param int $image_id Image ID
     * @return array Tags
     */
    public function get_image_tags($image_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$this->table} t
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON t.id = it.tag_id
             WHERE it.image_id = %d
             ORDER BY t.name ASC",
            $image_id
        ));
    }
    
    /**
     * Get images for tag
     *
     * @param int $tag_id Tag ID
     * @param int $limit Limit results
     * @return array Images
     */
    public function get_tag_images($tag_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.* FROM {$wpdb->prefix}pv_images i
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON i.id = it.image_id
             WHERE it.tag_id = %d
             ORDER BY i.upload_date DESC
             LIMIT %d",
            $tag_id,
            $limit
        ));
    }
    
    /**
     * Get popular tags
     *
     * @param int $limit Number of tags to return
     * @return array Popular tags
     */
    public function get_popular($limit = 10) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, COUNT(it.image_id) as usage_count
             FROM {$this->table} t
             INNER JOIN {$wpdb->prefix}pv_image_tag it ON t.id = it.tag_id
             GROUP BY t.id
             ORDER BY usage_count DESC
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Update tag usage count
     *
     * @param int $tag_id Tag ID
     * @return bool Success
     */
    public function update_count($tag_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = %d",
            $tag_id
        ));
        
        return $wpdb->update(
            $this->table,
            ['count' => $count],
            ['id' => $tag_id]
        ) !== false;
    }
}