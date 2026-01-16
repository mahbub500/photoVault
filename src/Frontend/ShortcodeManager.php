<?php
/**
 * Shortcode Manager
 *
 * @package PhotoVault
 */

namespace PhotoVault\Frontend;

class ShortcodeManager {
    
    public function __construct() {
        add_shortcode('photovault_gallery', [$this, 'gallery_shortcode']);
        add_shortcode('photovault_album', [$this, 'album_shortcode']);
        add_shortcode('photovault_upload', [$this, 'upload_shortcode']);
        add_shortcode('photovault_timeline', [$this, 'timeline_shortcode']);
    }
    
    /**
     * Gallery shortcode
     */
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts([
            'limit'     => 12,
            'columns'   => 3,
            'user_id'   => 'current',
            'album_id'  => '',
            'tag'       => '',
            'show_title'=> 'yes'
        ], $atts);

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
            const userId = $grid.data('user-id') === 'current'
                ? '<?php echo esc_js( get_current_user_id() ); ?>'
                : $grid.data('user-id');

            $.post('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                action: 'pv_get_images',
                nonce: '<?php echo esc_js( wp_create_nonce('photovault_nonce') ); ?>',
                user_id: userId,
                album_id: $grid.data('album-id'),
                tag: $grid.data('tag'),
                per_page: $grid.data('limit')
            }, function(response) {
                $grid.find('.pv-loading').remove();

                if (response.success && response.data.images) {
                    response.data.images.forEach(image => {
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
     */
    public function album_shortcode($atts) {
        $atts = shortcode_atts([
            'id'        => '',
            'show_info' => 'yes'
        ], $atts);

        if (empty($atts['id'])) {
            return '<p>' . esc_html__('Album ID required', 'photovault') . '</p>';
        }

        global $wpdb;
        $album = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}pv_albums WHERE id = %d", $atts['id'])
        );

        if (!$album) {
            return '<p>' . esc_html__('Album not found', 'photovault') . '</p>';
        }

        ob_start();
        ?>
        <div class="photovault-album">
            <?php if ($atts['show_info'] === 'yes'): ?>
                <div class="pv-album-header">
                    <h2><?php echo esc_html($album->name); ?></h2>

                    <?php if (!empty($album->description)): ?>
                        <p><?php echo esc_html($album->description); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php echo do_shortcode('[photovault_gallery album_id="' . esc_attr($album->id) . '"]'); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Upload form shortcode
     */
    public function upload_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to upload images.', 'photovault') . '</p>';
        }

        $atts = shortcode_atts([
            'max_files' => 10,
            'redirect'  => ''
        ], $atts);

        ob_start();
        ?>
        <div class="photovault-upload-form">
            <form id="pv-frontend-upload-form" enctype="multipart/form-data">
                <div class="pv-form-group">
                    <label>
                        <?php echo esc_html__('Select Images', 'photovault'); ?>
                    </label>
                    <input type="file" name="images[]" id="pv-frontend-files" multiple accept="image/*" required>
                    
                    <?php
                    // translators: %d is the maximum number of files allowed to upload.
                    printf(
                        '<small>%s</small>',
                        esc_html(sprintf(__('Maximum %d files', 'photovault'), $atts['max_files']))
                    );
                    ?>
                </div>


                <div class="pv-form-group">
                    <label><?php echo esc_html__('Album', 'photovault'); ?></label>
                    <select name="album_id" id="pv-frontend-album">
                        <option value=""><?php echo esc_html__('No Album', 'photovault'); ?></option>
                    </select>
                </div>

                <div class="pv-form-group">
                    <label><?php echo esc_html__('Tags (comma separated)', 'photovault'); ?></label>
                    <input type="text" name="tags" placeholder="<?php echo esc_attr__('beach, vacation, summer', 'photovault'); ?>">
                </div>

                <div class="pv-form-group">
                    <label><?php echo esc_html__('Visibility', 'photovault'); ?></label>
                    <select name="visibility">
                        <option value="private"><?php echo esc_html__('Private', 'photovault'); ?></option>
                        <option value="shared"><?php echo esc_html__('Shared', 'photovault'); ?></option>
                        <option value="public"><?php echo esc_html__('Public', 'photovault'); ?></option>
                    </select>
                </div>

                <div class="pv-upload-preview"></div>

                <button type="submit" class="pv-btn pv-btn-primary">
                    <span class="pv-upload-text"><?php echo esc_html__('Upload Images', 'photovault'); ?></span>
                    <span class="pv-upload-spinner" style="display:none;"></span>
                </button>

                <div class="pv-upload-result"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Timeline shortcode
     */
    public function timeline_shortcode($atts) {
        $atts = shortcode_atts([
            'view' => 'month'
        ], $atts);

        ob_start();
        ?>
        <div class="photovault-timeline-frontend">
            <div class="pv-timeline-container"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $.post('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                action: 'pv_get_timeline',
                nonce: '<?php echo esc_js( wp_create_nonce('photovault_nonce') ); ?>',
                view: '<?php echo esc_js( $atts['view'] ); ?>'
            }, function(response) {
                if (response.success) {
                    const container = $('.pv-timeline-container');
                    response.data.forEach(item => {
                        container.append(`
                            <div class="pv-timeline-item">
                                <h3>${new Date(item.date).toLocaleDateString()}</h3>
                                <span class="pv-count">${item.count} images</span>
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
}
