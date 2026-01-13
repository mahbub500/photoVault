<?php
/**
 * Image Processing Settings Tab
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

settings_fields('photovault_processing');
?>

<div class="photovault-settings-section">
    <h2><?php _e('Image Processing Settings', 'photovault'); ?></h2>
    
    <table class="form-table photovault-form-table">
        <tbody>
            <!-- Image Quality -->
            <tr>
                <th scope="row">
                    <label for="photovault_image_quality">
                        <?php _e('Image Quality', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <div class="photovault-range-wrapper">
                        <input type="range" 
                               id="photovault_image_quality" 
                               name="photovault_image_quality" 
                               value="<?php echo esc_attr($data['processing']['image_quality']); ?>" 
                               min="1" 
                               max="100" 
                               class="photovault-range-input"
                               data-output="quality-value">
                        <output id="quality-value" class="photovault-range-output">
                            <?php echo esc_html($data['processing']['image_quality']); ?>%
                        </output>
                    </div>
                    <p class="description">
                        <?php _e('Quality for processed images (1-100, higher is better quality but larger file size)', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Auto Optimization -->
            <tr>
                <th scope="row">
                    <?php _e('Auto Optimization', 'photovault'); ?>
                </th>
                <td>
                    <label for="photovault_auto_optimize">
                        <input type="checkbox" 
                               id="photovault_auto_optimize"
                               name="photovault_auto_optimize" 
                               value="1" 
                               <?php checked($data['processing']['auto_optimize'], true); ?>>
                        <?php _e('Automatically optimize images on upload', 'photovault'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Reduces file size while maintaining quality', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Thumbnail Size -->
            <tr>
                <th scope="row">
                    <?php _e('Thumbnail Size', 'photovault'); ?>
                </th>
                <td>
                    <div class="photovault-input-group">
                        <label for="photovault_thumbnail_width">
                            <?php _e('Width:', 'photovault'); ?>
                            <input type="number" 
                                   id="photovault_thumbnail_width"
                                   name="photovault_thumbnail_width" 
                                   value="<?php echo esc_attr($data['processing']['thumbnail_width']); ?>" 
                                   min="50" 
                                   max="1000" 
                                   class="small-text">
                            <span class="photovault-unit">px</span>
                        </label>
                    </div>
                    
                    <div class="photovault-input-group">
                        <label for="photovault_thumbnail_height">
                            <?php _e('Height:', 'photovault'); ?>
                            <input type="number" 
                                   id="photovault_thumbnail_height"
                                   name="photovault_thumbnail_height" 
                                   value="<?php echo esc_attr($data['processing']['thumbnail_height']); ?>" 
                                   min="50" 
                                   max="1000" 
                                   class="small-text">
                            <span class="photovault-unit">px</span>
                        </label>
                    </div>
                    
                    <p class="description">
                        <?php _e('Dimensions for thumbnail images', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Thumbnail Quality -->
            <tr>
                <th scope="row">
                    <label for="photovault_thumbnail_quality">
                        <?php _e('Thumbnail Quality', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <div class="photovault-range-wrapper">
                        <input type="range" 
                               id="photovault_thumbnail_quality" 
                               name="photovault_thumbnail_quality" 
                               value="<?php echo esc_attr($data['processing']['thumbnail_quality']); ?>" 
                               min="1" 
                               max="100" 
                               class="photovault-range-input"
                               data-output="thumbnail-quality-value">
                        <output id="thumbnail-quality-value" class="photovault-range-output">
                            <?php echo esc_html($data['processing']['thumbnail_quality']); ?>%
                        </output>
                    </div>
                    <p class="description">
                        <?php _e('Quality for thumbnail images', 'photovault'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php submit_button(); ?>
</div>