<?php
/**
 * Settings Page Template
 *
 * @package PhotoVault
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap photovault-settings-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php
    // Show success message if settings were saved
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified by WordPress settings API via settings_fields().
    if (!empty($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully!', 'photovault'); ?></p>
        </div>
        <?php
    }
    ?>
    
    <h2 class="nav-tab-wrapper">
        <?php foreach ($data['tabs'] as $tab_key => $tab_label) : ?>
            <a href="?page=photovault-settings&tab=<?php echo esc_attr($tab_key); ?>" 
               class="nav-tab <?php echo $data['active_tab'] === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </h2>
    
    <form method="post" action="options.php" class="photovault-settings-form">
        <?php
        // Render the appropriate tab
        switch ($data['active_tab']) {
            case 'upload':
                include PHOTOVAULT_PLUGIN_DIR . 'src/Admin/Views/settings/upload.php';
                break;
            case 'processing':
                include PHOTOVAULT_PLUGIN_DIR . 'src/Admin/Views/settings/processing.php';
                break;
            case 'watermark':
                include PHOTOVAULT_PLUGIN_DIR . 'src/Admin/Views/settings/watermark.php';
                break;
            default:
                include PHOTOVAULT_PLUGIN_DIR . 'src/Admin/Views/settings/general.php';
        }
        ?>
    </form>
</div>