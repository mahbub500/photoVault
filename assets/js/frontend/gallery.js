/**
 * PhotoVault - Frontend Gallery JavaScript
 * File: assets/js/frontend/gallery.js
 */

(function($) {
    'use strict';

    const PhotoVaultFrontend = {
        
        init: function() {
            this.initLightbox();
            this.initLazyLoad();
            this.initUploadForm();
        },

        /**
         * Simple lightbox functionality
         */
        initLightbox: function() {
            $(document).on('click', 'a[data-lightbox="photovault"]', function(e) {
                e.preventDefault();
                const imgSrc = $(this).attr('href');
                const title = $(this).find('img').attr('alt') || '';
                
                PhotoVaultFrontend.openLightbox(imgSrc, title);
            });

            // Close lightbox on click
            $(document).on('click', '.pv-lightbox-overlay', function(e) {
                if ($(e.target).hasClass('pv-lightbox-overlay') || $(e.target).hasClass('pv-lightbox-close')) {
                    PhotoVaultFrontend.closeLightbox();
                }
            });

            // Close on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.pv-lightbox-overlay').length) {
                    PhotoVaultFrontend.closeLightbox();
                }
            });
        },

        openLightbox: function(imgSrc, title) {
            const lightbox = $(`
                <div class="pv-lightbox-overlay">
                    <div class="pv-lightbox-content">
                        <button class="pv-lightbox-close">&times;</button>
                        <img src="${imgSrc}" alt="${title}">
                        ${title ? `<div class="pv-lightbox-title">${title}</div>` : ''}
                    </div>
                </div>
            `);
            
            $('body').append(lightbox);
            lightbox.fadeIn(300);
        },

        closeLightbox: function() {
            $('.pv-lightbox-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Lazy load images
         */
        initLazyLoad: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            const src = img.getAttribute('data-src');
                            
                            if (src) {
                                img.src = src;
                                img.removeAttribute('data-src');
                                observer.unobserve(img);
                            }
                        }
                    });
                });

                $('.pv-gallery-item img[data-src]').each(function() {
                    imageObserver.observe(this);
                });
            }
        },

        /**
         * Initialize frontend upload form
         */
        initUploadForm: function() {
            const self = this;
            
            // File preview
            $('#pv-frontend-files').on('change', function(e) {
                const files = Array.from(e.target.files);
                const $preview = $('.pv-upload-preview');
                $preview.empty();
                
                files.slice(0, 10).forEach(file => {
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
                self.handleFrontendUpload(this);
            });
        },

        handleFrontendUpload: function(form) {
            const files = $('#pv-frontend-files')[0].files;
            const albumId = $('#pv-frontend-album').val();
            const tags = $('input[name="tags"]', form).val();
            const visibility = $('select[name="visibility"]', form).val();
            
            if (files.length === 0) {
                alert('Please select files to upload');
                return;
            }

            $('.pv-upload-text').hide();
            $('.pv-upload-spinner').show();
            
            let uploaded = 0;
            const total = Math.min(files.length, 10);
            
            for (let i = 0; i < total; i++) {
                const formData = new FormData();
                formData.append('action', 'pv_upload_image');
                formData.append('nonce', photoVault.nonce);
                formData.append('file', files[i]);
                formData.append('title', files[i].name.replace(/\.[^/.]+$/, ""));
                formData.append('visibility', visibility);
                
                if (albumId) formData.append('album_id', albumId);
                if (tags) formData.append('tags', tags.split(','));
                
                $.ajax({
                    url: photoVault.ajaxUrl,
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
                        }
                    },
                    error: function() {
                        uploaded++;
                        if (uploaded === total) {
                            $('.pv-upload-text').show();
                            $('.pv-upload-spinner').hide();
                            $('.pv-upload-result').html('<div class="pv-error">Some uploads failed</div>');
                        }
                    }
                });
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PhotoVaultFrontend.init();
    });

})(jQuery);