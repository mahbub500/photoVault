<?php
/**
 * Plugin Name: PhotoVault
 * Plugin URI: https://github.com/mahbubmr500/photovault
 * Description: A powerful private photo gallery and album management system for WordPress.
 * Version: 1.0.1
 * Author: mahbubmr500
 * Author URI: https://github.com/mahbubmr500
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: photovault
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

namespace PhotoVault;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PHOTOVAULT_VERSION', '1.0.1');
define('PHOTOVAULT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PHOTOVAULT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PHOTOVAULT_PLUGIN_FILE', __FILE__);
define('PHOTOVAULT_DB_VERSION', '1.0');

/**
 * Composer autoloader or manual fallback
 */
if (file_exists(PHOTOVAULT_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once PHOTOVAULT_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Manual PSR-4 autoloader if Composer not available
    spl_autoload_register(function ($class) {
        $prefix = 'PhotoVault\\';
        $base_dir = PHOTOVAULT_PLUGIN_DIR . 'src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Bootstrap the plugin
if (class_exists('PhotoVault\\Core\\Plugin')) {
    $photovault = Core\Plugin::get_instance();
    $photovault->init();
}

// Activation hook
register_activation_hook(__FILE__, function() {
    if (class_exists('PhotoVault\\Core\\Activator')) {
        Core\Activator::activate();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    if (class_exists('PhotoVault\\Core\\Deactivator')) {
        Core\Deactivator::deactivate();
    }
});