<?php
/**
 * Upload Settings Tab
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

settings_fields('photovault_upload');
?>

<div class="photovault-settings-section">
    <h2><?php esc_html_e('Upload Settings', 'photovault'); ?></h2>
    
    <table class="form-table photovault-form-table">
        <tbody>
            <!-- Maximum Upload Size -->
            <tr>
                <th scope="row">
                    <label for="photovault_max_upload_size">
                        <?php esc_html_e('Maximum Upload Size', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                           id="photovault_max_upload_size" 
                           name="photovault_max_upload_size" 
                           value="<?php echo esc_attr($data['upload']['max_upload_size']); ?>" 
                           min="1048576" 
                           step="1048576" 
                           class="regular-text">
                    <p class="description">
                        <?php 
                        $mb = $data['upload']['max_upload_size'] / 1048576;

                        // Translators: %1$s = current upload size in MB, %2$s = server maximum upload size formatted (e.g., "8 MB").
                        printf(
                            /* translators: %1$s = current upload size in MB, %2$s = server max upload size */
                            esc_html__('Current: %1$s MB (in bytes). Server maximum: %2$s', 'photovault'),
                            number_format($mb, 2),
                            esc_html(size_format($data['upload']['server_max']))
                        ); 
                        ?>
                    </p>
                </td>

            </tr>
            
            <!-- Allowed File Types -->
            <tr>
                <th scope="row">
                    <?php esc_html_e('Allowed File Types', 'photovault'); ?>
                </th>
                <td>
                    <fieldset class="photovault-checkbox-group">
                        <legend class="screen-reader-text">
                            <span><?php esc_html_e('Allowed File Types', 'photovault'); ?></span>
                        </legend>
                        
                        <?php foreach ($data['file_types'] as $ext => $label) : ?>
                            <label>
                                <input type="checkbox" 
                                       name="photovault_allowed_types[]" 
                                       value="<?php echo esc_attr($ext); ?>" 
                                       <?php checked(in_array($ext, $data['upload']['allowed_types']), true); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                        
                        <p class="description">
                            <?php esc_html_e('Select which image formats users can upload', 'photovault'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php submit_button(); ?>
</div>
