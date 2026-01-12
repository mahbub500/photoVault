/**
 * PhotoVault - Frontend JavaScript
 * File: assets/js/frontend.js
 */

(function($) {
    'use strict';

    const PhotoVaultFrontend = {
        
        init: function() {
            this.initLightbox();
            this.initLazyLoad();
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
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PhotoVaultFrontend.init();
    });

})(jQuery);