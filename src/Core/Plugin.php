<?php
namespace PhotoVault\Core;
/**
 * Main Plugin Class - Updated with Video Support
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


use PhotoVault\Admin\MenuManager;
use PhotoVault\Admin\AssetManager;
use PhotoVault\Admin\SettingsManager;
use PhotoVault\Admin\TagManager;
use PhotoVault\Controllers\ImageController;
use PhotoVault\Controllers\VideoController;
use PhotoVault\Controllers\AlbumController;
use PhotoVault\Controllers\TagController;
use PhotoVault\Controllers\ShareController;
use PhotoVault\Controllers\TimelineController;
use PhotoVault\Controllers\SettingsController;
use PhotoVault\Frontend\ShortcodeManager;

class Plugin {
    
    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private static $instance = null;
    
    /**
     * Controllers
     *
     * @var object
     */
    private $image_controller;
    private $video_controller;
    private $album_controller;
    private $tag_controller;
    private $share_controller;
    private $timeline_controller;
    private $settings_controller;
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        // Constructor is private for singleton
    }
    
    /**
     * Initialize plugin
     * Called from main photovault.php file
     */
    public function init() {
        $this->load_textdomain();
        $this->init_controllers();
        $this->init_managers();
    }
    
    /**
     * Load plugin textdomain for translations
     */
    private function load_textdomain() {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomain -- Manual loading for custom distribution outside WordPress.org.
        // load_plugin_textdomain(
        //     'photovault',
        //     false,
        //     dirname(plugin_basename(PHOTOVAULT_PLUGIN_FILE)) . '/languages'
        // );
    }
    
    /**
     * Initialize all controllers
     */
    private function init_controllers() {
        $this->image_controller     = new ImageController();
        $this->video_controller     = new VideoController();
        $this->album_controller     = new AlbumController();
        $this->tag_controller       = new TagController();
        $this->share_controller     = new ShareController();
        $this->timeline_controller  = new TimelineController();
        $this->settings_controller  = new SettingsController();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_managers() {
        // Admin area initialization
        if (is_admin()) {
            new MenuManager();
            new TagManager();
            new SettingsManager();
        }
        new AssetManager();
        
        // Frontend initialization
        new ShortcodeManager();
        
        // Register all AJAX hooks
        $this->register_ajax_hooks();
    }
    
    /**
     * Register all AJAX action hooks
     */
    private function register_ajax_hooks() {
        // Image operations
        add_action('wp_ajax_pv_upload_image', [$this->image_controller, 'upload']);
        add_action('wp_ajax_pv_get_images', [$this->image_controller, 'get_images']);
        add_action('wp_ajax_pv_delete_image', [$this->image_controller, 'delete']);
        add_action('wp_ajax_pv_update_image', [$this->image_controller, 'update']);
        
        // Video operations
        add_action('wp_ajax_pv_upload_video', [$this->video_controller, 'upload']);
        add_action('wp_ajax_pv_get_videos', [$this->video_controller, 'get_videos']);
        add_action('wp_ajax_pv_delete_video', [$this->video_controller, 'delete']);
        add_action('wp_ajax_pv_update_video', [$this->video_controller, 'update']);
        
        // Album operations
        add_action('wp_ajax_pv_create_album', [$this->album_controller, 'create']);
        add_action('wp_ajax_pv_get_albums', [$this->album_controller, 'get_albums']);
        add_action('wp_ajax_pv_update_album', [$this->album_controller, 'update']);
        add_action('wp_ajax_pv_delete_album', [$this->album_controller, 'delete']);
        
        // Tag operations
        add_action('wp_ajax_pv_create_tag', [$this->tag_controller, 'create']);
        add_action('wp_ajax_pv_get_tags', [$this->tag_controller, 'get_tags']);
        add_action('wp_ajax_pv_update_tag', [$this->tag_controller, 'update']);
        add_action('wp_ajax_pv_delete_tag', [$this->tag_controller, 'delete']);
        
        // Share operations
        add_action('wp_ajax_pv_share_item', [$this->share_controller, 'share']);
        add_action('wp_ajax_pv_unshare_item', [$this->share_controller, 'unshare']);
        
        // Timeline operations
        add_action('wp_ajax_pv_get_timeline_images', 
            [$this->timeline_controller, 'get_timeline_images']);
        add_action('wp_ajax_pv_get_timeline_stats', 
            [$this->timeline_controller, 'get_timeline_stats']);
        add_action('wp_ajax_pv_get_images_by_date_range', 
            [$this->timeline_controller, 'get_images_by_date_range']);
    }
    
    /**
     * Get controller instance by name
     *
     * @param string $controller Controller name (image, video, album, tag, share, timeline)
     * @return object|null Controller instance or null if not found
     */
    public function get_controller($controller) {
        $property = $controller . '_controller';
        return property_exists($this, $property) ? $this->$property : null;
    }
    
    /**
     * Get image controller
     *
     * @return ImageController
     */
    public function get_image_controller() {
        return $this->image_controller;
    }
    
    /**
     * Get video controller
     *
     * @return VideoController
     */
    public function get_video_controller() {
        return $this->video_controller;
    }
    
    /**
     * Get album controller
     *
     * @return AlbumController
     */
    public function get_album_controller() {
        return $this->album_controller;
    }
    
    /**
     * Get tag controller
     *
     * @return TagController
     */
    public function get_tag_controller() {
        return $this->tag_controller;
    }
    
    /**
     * Get share controller
     *
     * @return ShareController
     */
    public function get_share_controller() {
        return $this->share_controller;
    }
    
    /**
     * Get timeline controller
     *
     * @return TimelineController
     */
    public function get_timeline_controller() {
        return $this->timeline_controller;
    }
    
    /**
     * Get settings controller
     *
     * @return SettingsController
     */
    public function get_settings_controller() {
        return $this->settings_controller;
    }
}