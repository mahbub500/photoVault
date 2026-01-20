<?php
/**
 * Album Model - Works with existing database schema
 *
 * @package PhotoVault
 */
namespace PhotoVault\Models;

class Album {
    private $table_albums;
    private $table_images;
    private $table_image_album;
    
    public function __construct() {
        global $wpdb;
        $this->table_albums = $wpdb->prefix . 'pv_albums';
        $this->table_images = $wpdb->prefix . 'pv_images';
        $this->table_image_album = $wpdb->prefix . 'pv_image_album';
    }
    
    /**
     * Create new album
     */
    public function create($data) {
        global $wpdb;
        
        // Ensure unique slug
        $slug = isset($data['slug']) ? $data['slug'] : sanitize_title($data['name']);
        $data['slug'] = $this->generate_unique_slug($slug, $data['user_id']);
        
        // Insert album (MySQL will auto-set created_date and modified_date)
        $wpdb->insert($this->table_albums, [
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => isset($data['description']) ? $data['description'] : '',
            'visibility' => isset($data['visibility']) ? $data['visibility'] : 'private',
            'sort_order' => isset($data['sort_order']) ? $data['sort_order'] : 'date_desc'
        ]);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Generate unique slug
     */
    private function generate_unique_slug($slug, $user_id, $iteration = 0) {
        global $wpdb;
        
        $test_slug = $iteration > 0 ? $slug . '-' . $iteration : $slug;
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_albums} 
             WHERE slug = %s AND user_id = %d",
            $test_slug, $user_id
        ));
        
        if ($exists) {
            return $this->generate_unique_slug($slug, $user_id, $iteration + 1);
        }
        
        return $test_slug;
    }
    
    /**
     * Get albums with filters
     */
    public function get_albums($params = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $values = [];
        
        if (isset($params['user_id'])) {
            $where[] = 'a.user_id = %d';
            $values[] = $params['user_id'];
        }
        
        if (isset($params['visibility'])) {
            $where[] = 'a.visibility = %s';
            $values[] = $params['visibility'];
        }
        
        if (isset($params['search'])) {
            $where[] = '(a.name LIKE %s OR a.description LIKE %s)';
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }
        
        $where_clause = implode(' AND ', $where);
        $order_by = isset($params['order_by']) ? $params['order_by'] : 'a.created_date DESC';
        
        $query = "SELECT 
            a.id,
            a.user_id,
            a.name,
            a.slug,
            a.description,
            a.visibility,
            a.sort_order,
            a.cover_image_id,
            a.created_date,
            a.modified_date,
            COUNT(DISTINCT ia.image_id) as image_count,
            DATE_FORMAT(a.created_date, '%%M %%d, %%Y') as created_at,
            DATE_FORMAT(a.modified_date, '%%M %%d, %%Y') as modified_at
        FROM {$this->table_albums} a
        LEFT JOIN {$this->table_image_album} ia ON a.id = ia.album_id
        WHERE {$where_clause}
        GROUP BY a.id
        ORDER BY {$order_by}";
        
        if (isset($params['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d", $params['limit']);
        }
        
        if (isset($params['offset'])) {
            $query .= $wpdb->prepare(" OFFSET %d", $params['offset']);
        }
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query);
        
        if ($results) {
            foreach ($results as $album) {
                $album->image_count = (int) $album->image_count;
                $album->cover_image_url = $this->get_album_cover_url($album->id, $album->cover_image_id);
            }
        }
        
        return $results;
    }
    
    /**
     * Search albums
     */
    public function search_albums($search_term, $user_id = null) {
        $params = ['search' => $search_term];
        
        if ($user_id) {
            $params['user_id'] = $user_id;
        }
        
        return $this->get_albums($params);
    }
    
    /**
     * Get album cover image URL
     */
    private function get_album_cover_url($album_id, $cover_image_id = null) {
        global $wpdb;
        
        $attachment_id = null;
        
        // Try to get cover image if set
        if ($cover_image_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT attachment_id FROM {$this->table_images} WHERE id = %d",
                $cover_image_id
            ));
        }
        
        // If no cover image, get first image from album
        if (!$attachment_id) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT i.attachment_id 
                 FROM {$this->table_image_album} ia
                 LEFT JOIN {$this->table_images} i ON ia.image_id = i.id
                 WHERE ia.album_id = %d
                 ORDER BY ia.position ASC, ia.added_date DESC
                 LIMIT 1",
                $album_id
            ));
        }
        
        if ($attachment_id) {
            $wp_image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            if ($wp_image_url) {
                return $wp_image_url;
            }
            
            // Fallback to custom path
            $upload_dir = wp_upload_dir();
            return $upload_dir['baseurl'] . '/photovault/thumbnails/' . $attachment_id . '.jpg';
        }
        
        return null;
    }
    
    /**
     * Get single album with details
     */
    public function get_album_details($album_id) {
        global $wpdb;
        
        $album = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                a.*,
                COUNT(DISTINCT ia.image_id) as image_count,
                DATE_FORMAT(a.created_date, '%%M %%d, %%Y at %%h:%%i %%p') as created_at,
                DATE_FORMAT(a.modified_date, '%%M %%d, %%Y at %%h:%%i %%p') as modified_at
            FROM {$this->table_albums} a
            LEFT JOIN {$this->table_image_album} ia ON a.id = ia.album_id
            WHERE a.id = %d
            GROUP BY a.id",
            $album_id
        ));
        
        if (!$album) {
            return null;
        }
        
        $album->image_count = (int) $album->image_count;
        $album->cover_image_url = $this->get_album_cover_url($album_id, $album->cover_image_id);
        
        return $album;
    }
    
    /**
     * Update album
     */
    public function update($id, $data) {
        global $wpdb;
        
        // MySQL will auto-update modified_date via ON UPDATE CURRENT_TIMESTAMP
        
        if (isset($data['name'])) {
            $current_album = $wpdb->get_row($wpdb->prepare(
                "SELECT slug, user_id FROM {$this->table_albums} WHERE id = %d",
                $id
            ));
            
            $new_slug = sanitize_title($data['name']);
            
            if ($current_album && $new_slug !== $current_album->slug) {
                $data['slug'] = $this->generate_unique_slug($new_slug, $current_album->user_id);
            }
        }
        
        return $wpdb->update($this->table_albums, $data, ['id' => $id]);
    }
    
    /**
     * Delete album
     */
    public function delete($id) {
        global $wpdb;
        
        // Delete image-album relationships first
        $wpdb->delete($this->table_image_album, ['album_id' => $id]);
        
        // Delete album
        return $wpdb->delete($this->table_albums, ['id' => $id]);
    }
    
    /**
     * Check if user owns album
     */
    public function user_owns_album($album_id, $user_id) {
        global $wpdb;
        
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_albums} WHERE id = %d", 
            $album_id
        ));
        
        return $owner == $user_id || current_user_can('manage_options');
    }
    
    /**
     * Add image to album
     */
    public function add_image($album_id, $image_id, $position = 0) {
        global $wpdb;
        
        // Check if already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_image_album} 
             WHERE album_id = %d AND image_id = %d",
            $album_id, $image_id
        ));
        
        if ($exists) {
            return false;
        }
        
        // Get next position if not specified
        if ($position == 0) {
            $max_position = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(position) FROM {$this->table_image_album} WHERE album_id = %d",
                $album_id
            ));
            $position = ($max_position ?: 0) + 1;
        }
        
        // Insert relationship (MySQL auto-sets added_date)
        $result = $wpdb->insert($this->table_image_album, [
            'album_id' => $album_id,
            'image_id' => $image_id,
            'position' => $position
        ]);
        
        // If this is the first image, set it as cover
        $image_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_image_album} WHERE album_id = %d",
            $album_id
        ));
        
        if ($image_count == 1) {
            $this->set_cover_image($album_id, $image_id);
        }
        
        return $result;
    }
    
    /**
     * Remove image from album
     */
    public function remove_image($album_id, $image_id) {
        global $wpdb;
        
        // Check if this is the cover image
        $is_cover = $wpdb->get_var($wpdb->prepare(
            "SELECT cover_image_id FROM {$this->table_albums} WHERE id = %d",
            $album_id
        ));
        
        $result = $wpdb->delete($this->table_image_album, [
            'album_id' => $album_id,
            'image_id' => $image_id
        ]);
        
        // If we removed the cover image, set a new one
        if ($is_cover == $image_id) {
            $new_cover = $wpdb->get_var($wpdb->prepare(
                "SELECT image_id FROM {$this->table_image_album} 
                 WHERE album_id = %d 
                 ORDER BY position ASC, added_date DESC 
                 LIMIT 1",
                $album_id
            ));
            
            if ($new_cover) {
                $this->set_cover_image($album_id, $new_cover);
            } else {
                $wpdb->update(
                    $this->table_albums,
                    ['cover_image_id' => null],
                    ['id' => $album_id]
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Set cover image
     */
    public function set_cover_image($album_id, $image_id) {
        global $wpdb;
        
        // Verify image is in album
        $in_album = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_image_album} 
             WHERE album_id = %d AND image_id = %d",
            $album_id, $image_id
        ));
        
        if (!$in_album) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_albums,
            ['cover_image_id' => $image_id],
            ['id' => $album_id]
        );
    }
    
    /**
     * Reorder images in album
     */
    public function reorder_images($album_id, $image_order) {
        global $wpdb;
        
        foreach ($image_order as $position => $image_id) {
            $wpdb->update(
                $this->table_image_album,
                ['position' => $position + 1],
                ['album_id' => $album_id, 'image_id' => $image_id]
            );
        }
        
        return true;
    }
    
    /**
     * Get album by slug
     */
    public function get_by_slug($slug, $user_id = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_albums} WHERE slug = %s";
        $params = [$slug];
        
        if ($user_id) {
            $query .= " AND user_id = %d";
            $params[] = $user_id;
        }
        
        return $wpdb->get_row($wpdb->prepare($query, $params));
    }
    
    /**
     * Duplicate album
     */
    public function duplicate($album_id) {
        global $wpdb;
        
        $album = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_albums} WHERE id = %d",
            $album_id
        ));
        
        if (!$album) {
            return false;
        }
        
        // Create new album
        $new_slug = $this->generate_unique_slug($album->slug . '-copy', $album->user_id);
        
        $wpdb->insert($this->table_albums, [
            'user_id' => $album->user_id,
            'name' => $album->name . ' (Copy)',
            'slug' => $new_slug,
            'description' => $album->description,
            'visibility' => $album->visibility,
            'sort_order' => $album->sort_order
        ]);
        
        $new_album_id = $wpdb->insert_id;
        
        if (!$new_album_id) {
            return false;
        }
        
        // Copy all images with their positions
        $images = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_image_album} WHERE album_id = %d",
            $album_id
        ));
        
        if ($images) {
            foreach ($images as $image) {
                $wpdb->insert($this->table_image_album, [
                    'album_id' => $new_album_id,
                    'image_id' => $image->image_id,
                    'position' => $image->position
                ]);
            }
            
            // Set cover image if original had one
            if ($album->cover_image_id) {
                $wpdb->update(
                    $this->table_albums,
                    ['cover_image_id' => $album->cover_image_id],
                    ['id' => $new_album_id]
                );
            }
        }
        
        return $new_album_id;
    }
    
    /**
     * Get user statistics
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        $total_albums = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_albums} WHERE user_id = %d",
            $user_id
        ));
        
        $total_images_in_albums = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ia.image_id)
             FROM {$this->table_image_album} ia
             LEFT JOIN {$this->table_albums} a ON ia.album_id = a.id
             WHERE a.user_id = %d",
            $user_id
        ));
        
        $visibility_breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT visibility, COUNT(*) as count
             FROM {$this->table_albums}
             WHERE user_id = %d
             GROUP BY visibility",
            $user_id
        ), OBJECT_K);
        
        $recent_albums = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, created_date
             FROM {$this->table_albums}
             WHERE user_id = %d
             ORDER BY created_date DESC
             LIMIT 5",
            $user_id
        ));
        
        return [
            'total_albums' => (int) $total_albums,
            'total_images_in_albums' => (int) $total_images_in_albums,
            'private_albums' => isset($visibility_breakdown['private']) ? (int) $visibility_breakdown['private']->count : 0,
            'shared_albums' => isset($visibility_breakdown['shared']) ? (int) $visibility_breakdown['shared']->count : 0,
            'public_albums' => isset($visibility_breakdown['public']) ? (int) $visibility_breakdown['public']->count : 0,
            'recent_albums' => $recent_albums
        ];
    }
    
    /**
     * Get albums by multiple IDs
     */
    public function get_by_ids($album_ids) {
        global $wpdb;
        
        if (empty($album_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($album_ids), '%d'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_albums} WHERE id IN ({$placeholders})",
            $album_ids
        ));
    }
    
    /**
     * Bulk delete albums
     */
    public function bulk_delete($album_ids, $user_id) {
        global $wpdb;
        
        if (empty($album_ids)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($album_ids), '%d'));
        $params = array_merge($album_ids, [$user_id]);
        
        // Delete relationships
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_image_album} 
             WHERE album_id IN (
                 SELECT id FROM {$this->table_albums} 
                 WHERE id IN ({$placeholders}) AND user_id = %d
             )",
            $params
        ));
        
        // Delete albums
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_albums} 
             WHERE id IN ({$placeholders}) AND user_id = %d",
            $params
        ));
    }
}