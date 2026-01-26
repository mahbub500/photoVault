<?php
/**
 * Video Model
 *
 * @package PhotoVault
 */

namespace PhotoVault\Models;

class Video {
    
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pv_videos';
    }
    
    /**
     * Create new video record
     *
     * @param array $data Video data
     * @return int|false Video ID or false
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
     * Get videos with filters
     *
     * @param array $params Query parameters
     * @return array Videos and pagination info
     */
    public function get_videos($params = []) {
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
        
        // Table names
        $shares_table = $wpdb->prefix . 'pv_shares';
        $video_album_table = $wpdb->prefix . 'pv_video_album';
        $video_tag_table = $wpdb->prefix . 'pv_video_tag';
        
        // User filter (own videos + shared with user)
        $where[] = "(v.user_id = %d OR s.shared_with = %d)";
        $query_params[] = $params['user_id'];
        $query_params[] = $params['user_id'];
        
        $join[] = "LEFT JOIN {$shares_table} s ON (s.item_type = 'video' AND s.item_id = v.id)";
        
        // Album filter
        if ($params['album_id'] > 0) {
            $join[] = "INNER JOIN {$video_album_table} va ON v.id = va.video_id";
            $where[] = "va.album_id = %d";
            $query_params[] = $params['album_id'];
        }
        
        // Tag filter
        if ($params['tag_id'] > 0) {
            $join[] = "INNER JOIN {$video_tag_table} vt ON v.id = vt.video_id";
            $where[] = "vt.tag_id = %d";
            $query_params[] = $params['tag_id'];
        }
        
        // Search filter
        if (!empty($params['search'])) {
            $where[] = "(v.title LIKE %s OR v.description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($params['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        // Visibility filter
        if (!empty($params['visibility'])) {
            $where[] = "v.visibility = %s";
            $query_params[] = $params['visibility'];
        }
        
        // Sorting
        $order_by = $this->get_order_by($params['sort']);
        
        // Build final query
        $sql = "SELECT DISTINCT v.*, p.guid as url 
                FROM {$this->table} v
                LEFT JOIN {$wpdb->posts} p ON v.attachment_id = p.ID
                " . implode(' ', $join);
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " {$order_by} LIMIT %d OFFSET %d";
        $query_params[] = $params['per_page'];
        $query_params[] = $offset;
        
        // Get videos
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $videos = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT v.id) 
                      FROM {$this->table} v
                      " . implode(' ', $join);
        
        if (!empty($where)) {
            $count_sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $count_params = array_slice($query_params, 0, count($query_params) - 2);
        
        if (!empty($count_params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = $wpdb->get_var($wpdb->prepare($count_sql, $count_params));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $total = $wpdb->get_var($count_sql);
        }
        
        // Enhance videos with additional data
        foreach ($videos as &$video) {
            $video->tags = $this->get_video_tags($video->id);
            $video->thumbnail = $this->get_video_thumbnail($video);
            $video->albums = $this->get_video_albums($video->id);
            $video->formatted_duration = $this->format_duration($video->duration);
        }
        
        return [
            'videos' => $videos,
            'total' => (int) $total,
            'page' => $params['page'],
            'per_page' => $params['per_page'],
            'total_pages' => ceil($total / $params['per_page']),
        ];
    }
    
    /**
     * Get single video
     *
     * @param int $video_id Video ID
     * @return object|null Video data
     */
    public function get($video_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT v.*, p.guid as url 
            FROM {$this->table} v
            LEFT JOIN {$wpdb->posts} p ON v.attachment_id = p.ID
            WHERE v.id = %d",
            $video_id
        );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $video = $wpdb->get_row($sql);
        
        if ($video) {
            $video->tags = $this->get_video_tags($video_id);
            $video->thumbnail = $this->get_video_thumbnail($video);
            $video->albums = $this->get_video_albums($video_id);
            $video->formatted_duration = $this->format_duration($video->duration);
        }
        
        return $video;
    }
    
    /**
     * Update video
     *
     * @param int $video_id Video ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($video_id, $data) {
        global $wpdb;
        
        $data['modified_date'] = current_time('mysql');
        
        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $video_id],
            null,
            ['%d']
        ) !== false;
    }
    
    /**
     * Delete video
     *
     * @param int $video_id Video ID
     * @return bool Success
     */
    public function delete($video_id) {
        global $wpdb;
        
        // Get attachment IDs
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $video_data = $wpdb->get_row($wpdb->prepare(
            "SELECT attachment_id, thumbnail_attachment_id FROM {$this->table} WHERE id = %d",
            $video_id
        ));
        
        // Delete from database
        $wpdb->delete($this->table, ['id' => $video_id]);
        $wpdb->delete($wpdb->prefix . 'pv_video_album', ['video_id' => $video_id]);
        $wpdb->delete($wpdb->prefix . 'pv_video_tag', ['video_id' => $video_id]);
        $wpdb->delete($wpdb->prefix . 'pv_shares', [
            'item_type' => 'video',
            'item_id' => $video_id
        ]);
        
        // Delete WordPress attachments
        if ($video_data) {
            if ($video_data->attachment_id) {
                wp_delete_attachment($video_data->attachment_id, true);
            }
            if ($video_data->thumbnail_attachment_id) {
                wp_delete_attachment($video_data->thumbnail_attachment_id, true);
            }
        }
        
        return true;
    }
    
    /**
     * Add tags to video
     *
     * @param int $video_id Video ID
     * @param array $tags Tag names
     * @return bool Success
     */
    public function add_tags($video_id, $tags) {
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
                $wpdb->insert($wpdb->prefix . 'pv_tags', [
                    'name' => $tag_name,
                    'slug' => $slug
                ]);
                $tag_id = $wpdb->insert_id;
            }
            
            // Link tag to video
            $wpdb->replace($wpdb->prefix . 'pv_video_tag', [
                'video_id' => $video_id,
                'tag_id' => $tag_id
            ]);
            
            // Update tag count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}pv_tags 
                 SET count = (
                     SELECT COUNT(*) FROM {$wpdb->prefix}pv_image_tag WHERE tag_id = %d
                 ) + (
                     SELECT COUNT(*) FROM {$wpdb->prefix}pv_video_tag WHERE tag_id = %d
                 )
                 WHERE id = %d",
                $tag_id,
                $tag_id,
                $tag_id
            ));
        }
        
        return true;
    }
    
    /**
     * Add video to album
     *
     * @param int $video_id Video ID
     * @param int $album_id Album ID
     * @return bool Success
     */
    public function add_to_album($video_id, $album_id) {
        global $wpdb;
        
        return $wpdb->replace($wpdb->prefix . 'pv_video_album', [
            'video_id' => $video_id,
            'album_id' => $album_id
        ]) !== false;
    }
    
    /**
     * Get video tags
     *
     * @param int $video_id Video ID
     * @return array Tags
     */
    public function get_video_tags($video_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$wpdb->prefix}pv_tags t
             INNER JOIN {$wpdb->prefix}pv_video_tag vt ON t.id = vt.tag_id
             WHERE vt.video_id = %d",
            $video_id
        ));
    }
    
    /**
     * Get video albums
     *
     * @param int $video_id Video ID
     * @return array Albums
     */
    public function get_video_albums($video_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}pv_albums a
             INNER JOIN {$wpdb->prefix}pv_video_album va ON a.id = va.album_id
             WHERE va.video_id = %d",
            $video_id
        ));
    }
    
    /**
     * Get video thumbnail
     *
     * @param object $video Video object
     * @return string|null Thumbnail URL
     */
    private function get_video_thumbnail($video) {
        if ($video->thumbnail_attachment_id) {
            $thumbnail = wp_get_attachment_image_url($video->thumbnail_attachment_id, 'medium');
            if ($thumbnail) {
                return $thumbnail;
            }
        }
        
        // Fallback to custom path
        $upload_dir = wp_upload_dir();
        $thumbnail_path = $upload_dir['basedir'] . '/photovault/video-thumbnails/' . $video->id . '.jpg';
        
        if (file_exists($thumbnail_path)) {
            return $upload_dir['baseurl'] . '/photovault/video-thumbnails/' . $video->id . '.jpg';
        }
        
        return null;
    }
    
    /**
     * Format video duration
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration (HH:MM:SS or MM:SS)
     */
    private function format_duration($seconds) {
        if (!$seconds) {
            return '00:00';
        }
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } else {
            return sprintf('%02d:%02d', $minutes, $seconds);
        }
    }
    
    /**
     * Check if user owns video
     *
     * @param int $video_id Video ID
     * @param int $user_id User ID
     * @return bool
     */
    public function user_owns_video($video_id, $user_id) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table} WHERE id = %d",
            $video_id
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
            'date_desc' => 'ORDER BY v.upload_date DESC',
            'date_asc' => 'ORDER BY v.upload_date ASC',
            'title_asc' => 'ORDER BY v.title ASC',
            'title_desc' => 'ORDER BY v.title DESC',
            'size_desc' => 'ORDER BY v.file_size DESC',
            'size_asc' => 'ORDER BY v.file_size ASC',
            'duration_desc' => 'ORDER BY v.duration DESC',
            'duration_asc' => 'ORDER BY v.duration ASC',
        ];
        
        return $order_map[$sort] ?? $order_map['date_desc'];
    }
}