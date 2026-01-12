<?php
/**
 * Plugin Activator
 *
 * @package PhotoVault
 */

namespace PhotoVault\Core;

class Activator {
    
    /**
     * Activate plugin
     */
    public static function activate() {
        self::create_tables();
        self::create_upload_directory();
        self::set_default_options();
        self::set_default_capabilities();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Images table
        $table_images = $wpdb->prefix . 'pv_images';
        $sql_images = "CREATE TABLE $table_images (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT '',
            description text,
            upload_date datetime DEFAULT CURRENT_TIMESTAMP,
            modified_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            visibility varchar(20) DEFAULT 'private',
            file_size bigint(20) DEFAULT 0,
            width int(11) DEFAULT 0,
            height int(11) DEFAULT 0,
            mime_type varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY attachment_id (attachment_id),
            KEY upload_date (upload_date),
            KEY visibility (visibility)
        ) $charset_collate;";
        
        // Albums table
        $table_albums = $wpdb->prefix . 'pv_albums';
        $sql_albums = "CREATE TABLE $table_albums (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            cover_image_id bigint(20),
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            modified_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            visibility varchar(20) DEFAULT 'private',
            sort_order varchar(50) DEFAULT 'date_desc',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY slug (slug),
            KEY visibility (visibility)
        ) $charset_collate;";
        
        // Tags table
        $table_tags = $wpdb->prefix . 'pv_tags';
        $sql_tags = "CREATE TABLE $table_tags (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            color varchar(7) DEFAULT '#667eea',
            count bigint(20) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY name (name)
        ) $charset_collate;";
        
        // Image-Album relationship
        $table_image_album = $wpdb->prefix . 'pv_image_album';
        $sql_image_album = "CREATE TABLE $table_image_album (
            image_id bigint(20) NOT NULL,
            album_id bigint(20) NOT NULL,
            position int(11) DEFAULT 0,
            added_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (image_id, album_id),
            KEY album_id (album_id),
            KEY position (position)
        ) $charset_collate;";
        
        // Image-Tag relationship
        $table_image_tag = $wpdb->prefix . 'pv_image_tag';
        $sql_image_tag = "CREATE TABLE $table_image_tag (
            image_id bigint(20) NOT NULL,
            tag_id bigint(20) NOT NULL,
            added_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (image_id, tag_id),
            KEY tag_id (tag_id)
        ) $charset_collate;";
        
        // Sharing table
        $table_shares = $wpdb->prefix . 'pv_shares';
        $sql_shares = "CREATE TABLE $table_shares (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            item_type varchar(20) NOT NULL,
            item_id bigint(20) NOT NULL,
            shared_by bigint(20) NOT NULL,
            shared_with bigint(20) NOT NULL,
            permission varchar(20) DEFAULT 'view',
            shared_date datetime DEFAULT CURRENT_TIMESTAMP,
            expires_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY item_lookup (item_type, item_id),
            KEY shared_with (shared_with),
            KEY shared_by (shared_by)
        ) $charset_collate;";
        
        // Upload queue table (for batch uploads)
        $table_upload_queue = $wpdb->prefix . 'pv_upload_queue';
        $sql_upload_queue = "CREATE TABLE $table_upload_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            processed_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Create all tables
        dbDelta($sql_images);
        dbDelta($sql_albums);
        dbDelta($sql_tags);
        dbDelta($sql_image_album);
        dbDelta($sql_image_tag);
        dbDelta($sql_shares);
        dbDelta($sql_upload_queue);
        
        // Update database version
        update_option('photovault_db_version', PHOTOVAULT_DB_VERSION);
    }
    
    /**
     * Create upload directory
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $photovault_dir = $upload_dir['basedir'] . '/photovault';
        
        if (!file_exists($photovault_dir)) {
            wp_mkdir_p($photovault_dir);
            
            // Create subdirectories
            wp_mkdir_p($photovault_dir . '/thumbnails');
            wp_mkdir_p($photovault_dir . '/original');
            wp_mkdir_p($photovault_dir . '/temp');
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>";
            
            file_put_contents($photovault_dir . '/.htaccess', $htaccess_content);
            
            // Add index.php to prevent directory listing
            file_put_contents($photovault_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($photovault_dir . '/thumbnails/index.php', '<?php // Silence is golden');
            file_put_contents($photovault_dir . '/original/index.php', '<?php // Silence is golden');
            file_put_contents($photovault_dir . '/temp/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = [
            'photovault_max_upload_size' => 10485760, // 10MB
            'photovault_allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'photovault_thumbnail_width' => 300,
            'photovault_thumbnail_height' => 300,
            'photovault_thumbnail_quality' => 85,
            'photovault_enable_watermark' => false,
            'photovault_watermark_text' => get_bloginfo('name'),
            'photovault_watermark_position' => 'bottom-right',
            'photovault_watermark_opacity' => 50,
            'photovault_default_visibility' => 'private',
            'photovault_enable_exif' => true,
            'photovault_items_per_page' => 20,
            'photovault_enable_comments' => false,
            'photovault_enable_likes' => false,
            'photovault_image_quality' => 85,
            'photovault_auto_optimize' => true,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Set default capabilities
     */
    private static function set_default_capabilities() {
        // Get roles
        $admin = get_role('administrator');
        $editor = get_role('editor');
        $author = get_role('author');
        
        // Define capabilities
        $capabilities = [
            'photovault_upload_images',
            'photovault_edit_images',
            'photovault_delete_images',
            'photovault_manage_albums',
            'photovault_share_items',
        ];
        
        // Add capabilities to roles
        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
        
        if ($editor) {
            foreach ($capabilities as $cap) {
                $editor->add_cap($cap);
            }
        }
        
        if ($author) {
            $author->add_cap('photovault_upload_images');
            $author->add_cap('photovault_edit_images');
            $author->add_cap('photovault_delete_images');
            $author->add_cap('photovault_manage_albums');
        }
    }
}