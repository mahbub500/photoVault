<?php
/**
 * Share Model
 *
 * @package PhotoVault
 */

namespace PhotoVault\Models;

class Share {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pv_shares';
    }
    
    /**
     * Create new share
     *
     * @param array $data Share data
     * @return int|false Share ID or false
     */
    public function create($data) {
        global $wpdb;
        
        $defaults = [
            'item_type' => 'image',
            'item_id' => 0,
            'shared_by' => get_current_user_id(),
            'shared_with' => 0,
            'permission' => 'view',
            'shared_date' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Check if share already exists
        $existing = $this->get_share($data['item_type'], $data['item_id'], $data['shared_with']);
        if ($existing) {
            // Update existing share
            return $this->update($existing->id, ['permission' => $data['permission']]);
        }
        
        $result = $wpdb->insert($this->table, $data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get all shares for an item
     *
     * @param string $item_type Item type (image or album)
     * @param int $item_id Item ID
     * @return array Shares
     */
    public function get_item_shares($item_type, $item_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as shared_with_name
             FROM {$this->table} s
             LEFT JOIN {$wpdb->users} u ON s.shared_with = u.ID
             WHERE s.item_type = %s AND s.item_id = %d
             ORDER BY s.shared_date DESC",
            $item_type,
            $item_id
        ));
    }
    
    /**
     * Get specific share
     *
     * @param string $item_type Item type
     * @param int $item_id Item ID
     * @param int $user_id User ID
     * @return object|null Share data
     */
    public function get_share($item_type, $item_id, $user_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE item_type = %s AND item_id = %d AND shared_with = %d",
            $item_type,
            $item_id,
            $user_id
        ));
    }
    
    /**
     * Get shares by user (items shared with user)
     *
     * @param int $user_id User ID
     * @param string $item_type Optional item type filter
     * @return array Shares
     */
    public function get_user_shares($user_id, $item_type = '') {
        global $wpdb;
        
        $sql = "SELECT s.*, u.display_name as shared_by_name
                FROM {$this->table} s
                LEFT JOIN {$wpdb->users} u ON s.shared_by = u.ID
                WHERE s.shared_with = %d";
        
        $params = [$user_id];
        
        if (!empty($item_type)) {
            $sql .= " AND s.item_type = %s";
            $params[] = $item_type;
        }
        
        $sql .= " ORDER BY s.shared_date DESC";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names use $wpdb->prefix, all user inputs properly prepared.
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Get shares created by user
     *
     * @param int $user_id User ID
     * @param string $item_type Optional item type filter
     * @return array Shares
     */
    public function get_shared_by_user($user_id, $item_type = '') {
        global $wpdb;
        
        $sql = "SELECT s.*, u.display_name as shared_with_name
                FROM {$this->table} s
                LEFT JOIN {$wpdb->users} u ON s.shared_with = u.ID
                WHERE s.shared_by = %d";
        
        $params = [$user_id];
        
        if (!empty($item_type)) {
            $sql .= " AND s.item_type = %s";
            $params[] = $item_type;
        }
        
        $sql .= " ORDER BY s.shared_date DESC";
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names use $wpdb->prefix, all user inputs properly prepared.
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    /**
     * Update share
     *
     * @param int $share_id Share ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($share_id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $share_id]
        ) !== false;
    }
    
    /**
     * Delete share
     *
     * @param int $share_id Share ID
     * @return bool Success
     */
    public function delete($share_id) {
        global $wpdb;
        
        return $wpdb->delete($this->table, ['id' => $share_id]) !== false;
    }
    
    /**
     * Delete all shares for an item
     *
     * @param string $item_type Item type
     * @param int $item_id Item ID
     * @return bool Success
     */
    public function delete_item_shares($item_type, $item_id) {
        global $wpdb;
        
        return $wpdb->delete($this->table, [
            'item_type' => $item_type,
            'item_id' => $item_id
        ]) !== false;
    }
    
    /**
     * Check if user has access to item
     *
     * @param string $item_type Item type
     * @param int $item_id Item ID
     * @param int $user_id User ID
     * @return bool Has access
     */
    public function user_has_access($item_type, $item_id, $user_id) {
        global $wpdb;
        
        // Check if user is owner
        $table = $item_type === 'image' 
            ? $wpdb->prefix . 'pv_images' 
            : $wpdb->prefix . 'pv_albums';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        if ($owner == $user_id) {
            return true;
        }
        
        // Check if shared with user
        $share = $this->get_share($item_type, $item_id, $user_id);
        
        return $share !== null;
    }
    
    /**
     * Get user permission for item
     *
     * @param string $item_type Item type
     * @param int $item_id Item ID
     * @param int $user_id User ID
     * @return string|null Permission level (view, edit) or null
     */
    public function get_user_permission($item_type, $item_id, $user_id) {
        global $wpdb;
        
        // Check if user is owner
        $table = $item_type === 'image' 
            ? $wpdb->prefix . 'pv_images' 
            : $wpdb->prefix . 'pv_albums';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        if ($owner == $user_id) {
            return 'owner';
        }
        
        // Get shared permission
        $share = $this->get_share($item_type, $item_id, $user_id);
        
        return $share ? $share->permission : null;
    }
    
    /**
     * Get share statistics for user
     *
     * @param int $user_id User ID
     * @return array Statistics
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $shared_by_me = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE shared_by = %d",
            $user_id
        ));
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $shared_with_me = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE shared_with = %d",
            $user_id
        ));
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $images_shared = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE shared_by = %d AND item_type = 'image'",
            $user_id
        ));
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses $wpdb->prefix, safe for interpolation.
        $albums_shared = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} 
             WHERE shared_by = %d AND item_type = 'album'",
            $user_id
        ));
        
        return [
            'shared_by_me' => (int) $shared_by_me,
            'shared_with_me' => (int) $shared_with_me,
            'images_shared' => (int) $images_shared,
            'albums_shared' => (int) $albums_shared
        ];
    }
    
    /**
     * Bulk share items with user
     *
     * @param string $item_type Item type
     * @param array $item_ids Array of item IDs
     * @param int $shared_with User ID to share with
     * @param string $permission Permission level
     * @return int Number of items shared
     */
    public function bulk_share($item_type, $item_ids, $shared_with, $permission = 'view') {
        $shared_count = 0;
        
        foreach ($item_ids as $item_id) {
            $result = $this->create([
                'item_type' => $item_type,
                'item_id' => $item_id,
                'shared_with' => $shared_with,
                'permission' => $permission
            ]);
            
            if ($result) {
                $shared_count++;
            }
        }
        
        return $shared_count;
    }
}