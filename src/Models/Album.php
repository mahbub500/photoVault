<?php
/**
 * Album Model - Compatible with existing database schema
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
        
        $defaults = [
            'created_date' => current_time('mysql'),
            'modified_date' => current_time('mysql'),
            'visibility' => 'private',
            'sort_order' => 'date_desc'
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $wpdb->insert($this->table_albums, $data);
        return $wpdb->insert_id;
    }
    
    /**
     * Get albums with filters
     */
    public function get_albums($params = []) {
        global $wpdb;
        
        $where = ['1=1'];
        $values = [];
        
        // Filter by user
        if (isset($params['user_id'])) {
            $where[] = 'a.user_id = %d';
            $values[] = $params['user_id'];
        }
        
        // Filter by visibility
        if (isset($params['visibility'])) {
            $where[] = 'a.visibility = %s';
            $values[] = $params['visibility'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get basic album data first
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
            DATE_FORMAT(a.created_date, '%%M %%d, %%Y') as created_at
        FROM {$this->table_albums} a
        LEFT JOIN {$this->table_image_album} ia ON a.id = ia.album_id
        WHERE {$where_clause}
        GROUP BY a.id
        ORDER BY a.created_date DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Process each album to get proper cover image URL
        if ($results) {
            foreach ($results as $album) {
                $album->image_count = (int) $album->image_count;
                $album->cover_image_url = $this->get_album_cover_url($album->id, $album->cover_image_id);
            }
        }
        
        return $results;
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
        
        // If we have an attachment ID, get the thumbnail URL
        if ($attachment_id) {
            // Try to get WordPress attachment URL first
            $wp_image_url = wp_get_attachment_image_url($attachment_id, 'medium');
            if ($wp_image_url) {
                return $wp_image_url;
            }
            
            // Fallback to custom path
            $upload_dir = wp_upload_dir();
            return $upload_dir['baseurl'] . '/photovault/thumbnails/' . $attachment_id . '.jpg';
        }
        
        // Return null if no image found (will use default in JavaScript)
        return null;
    }
    
    /**
     * Get single album with details
     */
    public function get_album_details($album_id) {
        global $wpdb;
        
        // Get album data with image count
        $album = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                a.*,
                COUNT(DISTINCT ia.image_id) as image_count,
                DATE_FORMAT(a.created_date, '%%M %%d, %%Y at %%h:%%i %%p') as created_at
            FROM {$this->table_albums} a
            LEFT JOIN {$this->table_image_album} ia ON a.id = ia.album_id
            WHERE a.id = %d
            GROUP BY a.id",
            $album_id
        ));
        
        if (!$album) {
            return null;
        }
        
        // Get album images with their details
        $images_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                i.id,
                i.attachment_id,
                i.title,
                i.description,
                ia.position,
                ia.added_date,
                (a.cover_image_id = i.id) as is_cover
            FROM {$this->table_image_album} ia
            LEFT JOIN {$this->table_images} i ON ia.image_id = i.id
            LEFT JOIN {$this->table_albums} a ON ia.album_id = a.id
            WHERE ia.album_id = %d
            ORDER BY ia.position ASC, ia.added_date DESC",
            $album_id
        ));
        
        // Process images to get proper URLs
        $images = [];
        if ($images_data) {
            foreach ($images_data as $img) {
                // Try to get WordPress attachment URLs first
                $thumbnail_url = wp_get_attachment_image_url($img->attachment_id, 'medium');
                $full_url = wp_get_attachment_image_url($img->attachment_id, 'large');
                
                // Fallback to custom paths if WordPress attachment not found
                if (!$thumbnail_url) {
                    $upload_dir = wp_upload_dir();
                    $thumbnail_url = $upload_dir['baseurl'] . '/photovault/thumbnails/' . $img->attachment_id . '.jpg';
                    $full_url = $upload_dir['baseurl'] . '/photovault/original/' . $img->attachment_id . '.jpg';
                }
                
                $images[] = (object) [
                    'id' => $img->id,
                    'attachment_id' => $img->attachment_id,
                    'title' => $img->title ?: 'Untitled',
                    'description' => $img->description,
                    'thumbnail_url' => $thumbnail_url,
                    'image_url' => $full_url,
                    'position' => $img->position,
                    'is_cover' => (bool) $img->is_cover
                ];
            }
        }
        
        $album->images = $images;
        
        return $album;
    }
    
    /**
     * Update album
     */
    public function update($id, $data) {
        global $wpdb;
        
        $data['modified_date'] = current_time('mysql');
        
        if (isset($data['name'])) {
            $data['slug'] = sanitize_title($data['name']);
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
        
        // Check if image already exists in album
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_image_album} 
             WHERE album_id = %d AND image_id = %d",
            $album_id, $image_id
        ));
        
        if ($exists) {
            return false;
        }
        
        // If no position specified, add to end
        if ($position == 0) {
            $max_position = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(position) FROM {$this->table_image_album} WHERE album_id = %d",
                $album_id
            ));
            $position = ($max_position ?: 0) + 1;
        }
        
        $result = $wpdb->insert($this->table_image_album, [
            'album_id' => $album_id,
            'image_id' => $image_id,
            'position' => $position,
            'added_date' => current_time('mysql')
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
            ['cover_image_id' => $image_id, 'modified_date' => current_time('mysql')],
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
}