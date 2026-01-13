<?php
/**
 * General Settings Tab
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

settings_fields('photovault_general');
?>

<div class="photovault-settings-section">
    <h2><?php _e('General Settings', 'photovault'); ?></h2>
    
    <table class="form-table photovault-form-table">
        <tbody>
            <!-- Items Per Page -->
            <tr>
                <th scope="row">
                    <label for="photovault_items_per_page">
                        <?php _e('Items Per Page', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                           id="photovault_items_per_page" 
                           name="photovault_items_per_page" 
                           value="<?php echo esc_attr($data['general']['items_per_page']); ?>" 
                           min="1" 
                           max="100" 
                           class="small-text">
                    <p class="description">
                        <?php _e('Number of images to display per page in galleries', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Default Visibility -->
            <tr>
                <th scope="row">
                    <label for="photovault_default_visibility">
                        <?php _e('Default Visibility', 'photovault'); ?>
                    </label>
                </th>
                <td>
                    <select id="photovault_default_visibility" name="photovault_default_visibility">
                        <?php foreach ($data['visibility_options'] as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" 
                                    <?php selected($data['general']['default_visibility'], $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Default visibility for newly uploaded images', 'photovault'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- Features -->
            <tr>
                <th scope="row">
                    <?php _e('Features', 'photovault'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php _e('Features', 'photovault'); ?></span>
                        </legend>
                        
                        <label for="photovault_enable_exif">
                            <input type="checkbox" 
                                   id="photovault_enable_exif"
                                   name="photovault_enable_exif" 
                                   value="1" 
                                   <?php checked($data['general']['enable_exif'], true); ?>>
                            <?php _e('Enable EXIF data extraction', 'photovault'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Extract and display camera information from uploaded images', 'photovault'); ?>
                        </p>
                        
                        <br>
                        
                        <label for="photovault_enable_comments">
                            <input type="checkbox" 
                                   id="photovault_enable_comments"
                                   name="photovault_enable_comments" 
                                   value="1" 
                                   <?php checked($data['general']['enable_comments'], true); ?>>
                            <?php _e('Enable comments on images', 'photovault'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Allow users to comment on images', 'photovault'); ?>
                        </p>
                        
                        <br>
                        
                        <label for="photovault_enable_likes">
                            <input type="checkbox" 
                                   id="photovault_enable_likes"
                                   name="photovault_enable_likes" 
                                   value="1" 
                                   <?php checked($data['general']['enable_likes'], true); ?>>
                            <?php _e('Enable likes/favorites', 'photovault'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Allow users to like and favorite images', 'photovault'); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php submit_button(); ?>
</div>