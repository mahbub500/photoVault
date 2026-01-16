<?php
/**
 * Plugin Deactivator
 *
 * @package PhotoVault
 */

namespace PhotoVault\Core;

class Deactivator {
    
    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        self::clear_scheduled_events();
        self::cleanup_temp_files();
        self::flush_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Clear scheduled events
     */
    private static function clear_scheduled_events() {
        // Clear any scheduled cron jobs
        $scheduled_hooks = [
            'photovault_cleanup_temp',
            'photovault_optimize_images',
            'photovault_cleanup_uploads',
        ];
        
        foreach ($scheduled_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * Cleanup temporary files
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/photovault/temp';
        
        if (file_exists($temp_dir)) {
            self::delete_directory_contents($temp_dir);
        }
    }
    
    /**
     * Delete directory contents
     *
     * @param string $dir Directory path
     */
    private static function delete_directory_contents($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $files = array_diff(scandir($dir), ['.', '..', '.htaccess', 'index.php']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory_contents($path);
                $wp_filesystem->rmdir($path);
            } else {
                wp_delete_file($path);
            }
        }
    }
    
    /**
     * Flush cache
     */
    private static function flush_cache() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        self::delete_transients();
    }
    
    /**
     * Delete plugin transients
     */
    private static function delete_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_photovault_%' 
             OR option_name LIKE '_transient_timeout_photovault_%'"
        );
    }
    
    /**
     * Remove capabilities (optional - usually kept for reactivation)
     */
    private static function remove_capabilities() {
        // Get roles
        $roles = ['administrator', 'editor', 'author'];
        
        // Define capabilities
        $capabilities = [
            'photovault_upload_images',
            'photovault_edit_images',
            'photovault_delete_images',
            'photovault_manage_albums',
            'photovault_share_items',
        ];
        
        // Remove capabilities from roles
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Complete uninstall (removes everything)
     * Note: This is NOT called on deactivation
     * Only call this if you want to completely remove the plugin
     */
    public static function uninstall() {
        global $wpdb;
        
        // Delete all plugin options
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'photovault_%'");
        
        // Drop all plugin tables
        $tables = [
            $wpdb->prefix . 'pv_images',
            $wpdb->prefix . 'pv_albums',
            $wpdb->prefix . 'pv_tags',
            $wpdb->prefix . 'pv_image_album',
            $wpdb->prefix . 'pv_image_tag',
            $wpdb->prefix . 'pv_shares',
            $wpdb->prefix . 'pv_upload_queue',
        ];
        
        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names use $wpdb->prefix, safe for interpolation.
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS {$table}"));
        }
        
        // Delete upload directory (optional - be careful!)
        // Uncomment only if you want to delete all uploaded images
        /*
        $upload_dir = wp_upload_dir();
        $photovault_dir = $upload_dir['basedir'] . '/photovault';
        
        if (file_exists($photovault_dir)) {
            self::delete_directory_contents($photovault_dir);
            
            // Initialize WP_Filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            
            $wp_filesystem->rmdir($photovault_dir);
        }
        */
        
        // Remove capabilities
        self::remove_capabilities();
        
        // Clear cache
        wp_cache_flush();
    }
}