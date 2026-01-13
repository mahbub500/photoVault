<?php
/**
 * Watermark Settings Tab
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

settings_fields('photovault_watermark');
?>

<div class="photovault-settings-section">
    <h2><?php _e('Watermark Settings', 'photovault'); ?></h2>
    
    <table class="form-table photovault-form-table">
        <tbody>
            <!-- Enable Watermark -->
            <tr>
                <th scope="row">
                    <?php _e('Enable Watermark', 'photovault'); ?>
                </th>
                <td>
                    <label for="photovault_enable_watermark">
                        <input type="checkbox" 
                               id="photovault_enable_watermark"
                               name="photovault_enable_watermark" 
                               value="1" 
                               <?php checked($data['watermark']['enable_watermark'], true); ?>>
                        <?php _e('Add watermark to images', 'photovault'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Protect your images by adding a watermark', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Watermark Text -->
            <tr>
                <th scope="row">
                    <label for="photovault_watermark_text">
                        <?php _e('Watermark Text', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="photovault_watermark_text" 
                           name="photovault_watermark_text" 
                           value="<?php echo esc_attr($data['watermark']['watermark_text']); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Text to display as watermark', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Watermark Position -->
            <tr>
                <th scope="row">
                    <label for="photovault_watermark_position">
                        <?php _e('Watermark Position', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <select id="photovault_watermark_position" name="photovault_watermark_position">
                        <?php foreach ($data['position_options'] as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" 
                                    <?php selected($data['watermark']['watermark_position'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Where to place the watermark on images', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Watermark Opacity -->
            <tr>
                <th scope="row">
                    <label for="photovault_watermark_opacity">
                        <?php _e('Watermark Opacity', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <div class="photovault-range-wrapper">
                        <input type="range" 
                               id="photovault_watermark_opacity" 
                               name="photovault_watermark_opacity" 
                               value="<?php echo esc_attr($data['watermark']['watermark_opacity']); ?>" 
                               min="0" 
                               max="100" 
                               class="photovault-range-input"
                               data-output="opacity-value">
                        <output id="opacity-value" class="photovault-range-output">
                            <?php echo esc_html($data['watermark']['watermark_opacity']); ?>%
                        </o>
                    </div>
                    <p class="description">
                        <?php _e('Transparency of the watermark (0 = transparent, 100 = opaque)', 'photovault'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php submit_button(); ?>
</div>