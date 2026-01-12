<?php
/**
 * PhotoVault - Additional Features & Shortcodes
 * File: includes/class-photovault-shortcodes.php
 * 
 * Add this to your main plugin file:
 * require_once PHOTOVAULT_PLUGIN_DIR . 'includes/class-photovault-shortcodes.php';
 */

if (!defined('ABSPATH')) {
    exit;
}

class PhotoVault_Shortcodes {
    
    public function __construct() {
        add_shortcode('photovault_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('photovault_album', array($this, 'album_shortcode'));
        add_shortcode('photovault_upload', array($this, 'upload_shortcode'));
        add_shortcode('photovault_timeline', array($this, 'timeline_shortcode'));
        
        // Add AJAX handlers for missing endpoints
        add_action('wp_ajax_pv_delete_album', array($this, 'ajax_delete_album'));
        add_action('wp_ajax_pv_update_album', array($this, 'ajax_update_album'));
    }
    
    /**
     * Gallery shortcode
     * Usage: [photovault_gallery limit="12" columns="3" user_id="current"]
     */
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'columns' => 3,
            'user_id' => 'current',
            'album_id' => '',
            'tag' => '',
            'show_title' => 'yes',
            'lightbox' => 'yes'
        ), $atts);
        
        ob_start();
        ?>
        <div class="photovault-gallery" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <div class="pv-gallery-grid" 
                 data-limit="<?php echo esc_attr($atts['limit']); ?>"
                 data-user-id="<?php echo esc_attr($atts['user_id']); ?>"
                 data-album-id="<?php echo esc_attr($atts['album_id']); ?>"
                 data-tag="<?php echo esc_attr($atts['tag']); ?>">
                <div class="pv-loading"><span class="spinner"></span></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const $grid = $('.pv-gallery-grid');
            const userId = $grid.data('user-id') === 'current' ? '<?php echo get_current_user_id(); ?>' : $grid.data('user-id');
            
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'pv_get_images',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                user_id: userId,
                album_id: $grid.data('album-id'),
                tag: $grid.data('tag'),
                limit: $grid.data('limit')
            }, function(response) {
                $grid.find('.pv-loading').remove();
                
                if (response.success) {
                    response.data.forEach(image => {
                        $grid.append(`
                            <div class="pv-gallery-item">
                                <a href="${image.url}" data-lightbox="photovault">
                                    <img src="${image.thumbnail || image.url}" alt="${image.title || ''}">
                                    <?php if ($atts['show_title'] === 'yes'): ?>
                                    <div class="pv-gallery-title">${image.title || 'Untitled'}</div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        `);
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Album shortcode
     * Usage: [photovault_album id="5"]
     */
    public function album_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'show_info' => 'yes'
        ), $atts);
        
        if (empty($atts['id'])) {
            return '<p>Album ID required</p>';
        }
        
        global $wpdb;
        $album = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pv_albums WHERE id = %d",
            $atts['id']
        ));
        
        if (!$album) {
            return '<p>Album not found</p>';
        }
        
        ob_start();
        ?>
        <div class="photovault-album">
            <?php if ($atts['show_info'] === 'yes'): ?>
            <div class="pv-album-header">
                <h2><?php echo esc_html($album->name); ?></h2>
                <?php if ($album->description): ?>
                <p><?php echo esc_html($album->description); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php echo do_shortcode('[photovault_gallery album_id="' . $album->id . '"]'); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Upload form shortcode
     * Usage: [photovault_upload]
     */
    public function upload_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>You must be logged in to upload images.</p>';
        }
        
        $atts = shortcode_atts(array(
            'max_files' => 10,
            'allowed_types' => 'jpg,jpeg,png,gif',
            'redirect' => ''
        ), $atts);
        
        ob_start();
        ?>
        <div class="photovault-upload-form">
            <form id="pv-frontend-upload-form" enctype="multipart/form-data">
                <div class="pv-form-group">
                    <label>Select Images</label>
                    <input type="file" name="images[]" id="pv-frontend-files" multiple accept="image/*" required>
                    <small>Maximum <?php echo esc_html($atts['max_files']); ?> files. Allowed: <?php echo esc_html($atts['allowed_types']); ?></small>
                </div>
                
                <div class="pv-form-group">
                    <label>Album</label>
                    <select name="album_id" id="pv-frontend-album">
                        <option value="">No Album</option>
                    </select>
                </div>
                
                <div class="pv-form-group">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="tags" placeholder="beach, vacation, summer">
                </div>
                
                <div class="pv-form-group">
                    <label>Visibility</label>
                    <select name="visibility">
                        <option value="private">Private</option>
                        <option value="shared">Shared</option>
                        <option value="public">Public</option>
                    </select>
                </div>
                
                <div class="pv-upload-preview"></div>
                
                <button type="submit" class="pv-btn pv-btn-primary">
                    <span class="pv-upload-text">Upload Images</span>
                    <span class="pv-upload-spinner" style="display:none;">Uploading...</span>
                </button>
                
                <div class="pv-upload-result"></div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load albums
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'pv_get_albums',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    response.data.forEach(album => {
                        $('#pv-frontend-album').append(`<option value="${album.id}">${album.name}</option>`);
                    });
                }
            });
            
            // Preview files
            $('#pv-frontend-files').on('change', function(e) {
                const files = Array.from(e.target.files);
                const $preview = $('.pv-upload-preview');
                $preview.empty();
                
                files.slice(0, <?php echo $atts['max_files']; ?>).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $preview.append(`
                            <div class="pv-preview-item">
                                <img src="${e.target.result}" alt="${file.name}">
                            </div>
                        `);
                    };
                    reader.readAsDataURL(file);
                });
            });
            
            // Handle upload
            $('#pv-frontend-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                const files = $('#pv-frontend-files')[0].files;
                const albumId = $('#pv-frontend-album').val();
                const tags = $('input[name="tags"]').val();
                const visibility = $('select[name="visibility"]').val();
                
                $('.pv-upload-text').hide();
                $('.pv-upload-spinner').show();
                
                let uploaded = 0;
                const total = Math.min(files.length, <?php echo $atts['max_files']; ?>);
                
                for (let i = 0; i < total; i++) {
                    const formData = new FormData();
                    formData.append('action', 'pv_upload_image');
                    formData.append('nonce', '<?php echo wp_create_nonce('photovault_nonce'); ?>');
                    formData.append('file', files[i]);
                    formData.append('title', files[i].name.replace(/\.[^/.]+$/, ""));
                    formData.append('visibility', visibility);
                    if (albumId) formData.append('album_id', albumId);
                    if (tags) formData.append('tags', tags.split(','));
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function() {
                            uploaded++;
                            if (uploaded === total) {
                                $('.pv-upload-text').show();
                                $('.pv-upload-spinner').hide();
                                $('.pv-upload-result').html('<div class="pv-success">Successfully uploaded ' + total + ' images!</div>');
                                $('#pv-frontend-upload-form')[0].reset();
                                $('.pv-upload-preview').empty();
                                
                                <?php if (!empty($atts['redirect'])): ?>
                                setTimeout(() => {
                                    window.location.href = '<?php echo esc_url($atts['redirect']); ?>';
                                }, 2000);
                                <?php endif; ?>
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Timeline shortcode
     * Usage: [photovault_timeline view="month"]
     */
    public function timeline_shortcode($atts) {
        $atts = shortcode_atts(array(
            'view' => 'month',
            'limit' => 100
        ), $atts);
        
        ob_start();
        ?>
        <div class="photovault-timeline-frontend">
            <div class="pv-timeline-container"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'pv_get_timeline',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    const container = $('.pv-timeline-container');
                    const grouped = {};
                    
                    response.data.forEach(item => {
                        const date = new Date(item.date);
                        const key = '<?php echo $atts['view']; ?>' === 'year' 
                            ? date.getFullYear()
                            : date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
                        
                        if (!grouped[key]) grouped[key] = 0;
                        grouped[key] += parseInt(item.count);
                    });
                    
                    Object.keys(grouped).sort().reverse().forEach(key => {
                        container.append(`
                            <div class="pv-timeline-item">
                                <h3>${key}</h3>
                                <span class="pv-count">${grouped[key]} images</span>
                            </div>
                        `);
                    });
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Delete album
     */
    public function ajax_delete_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $album_id = intval($_POST['album_id']);
        
        // Verify ownership
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}pv_albums WHERE id = %d",
            $album_id
        ));
        
        if ($owner != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Delete album (images remain)
        $wpdb->delete("{$wpdb->prefix}pv_albums", array('id' => $album_id));
        $wpdb->delete("{$wpdb->prefix}pv_image_album", array('album_id' => $album_id));
        $wpdb->delete("{$wpdb->prefix}pv_shares", array('item_type' => 'album', 'item_id' => $album_id));
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Update album
     */
    public function ajax_update_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $album_id = intval($_POST['album_id']);
        
        // Verify ownership
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}pv_albums WHERE id = %d",
            $album_id
        ));
        
        if ($owner != $user_id && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $wpdb->update(
            "{$wpdb->prefix}pv_albums",
            array(
                'name' => sanitize_text_field($_POST['name']),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private')
            ),
            array('id' => $album_id)
        );
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Database error');
        }
    }
}

// Initialize
new PhotoVault_Shortcodes();