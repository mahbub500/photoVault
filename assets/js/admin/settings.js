/**
 * PhotoVault Settings Page Scripts
 *
 * @package PhotoVault
 */

(function($) {
    'use strict';

    /**
     * Settings Page Handler
     */
    const PhotoVaultSettings = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initRangeInputs();
            this.initColorPickers();
            this.initFormValidation();
            this.initConditionalFields();
            this.initUnsavedChanges();
        },
        
        /**
         * Initialize range input handlers
         */
        initRangeInputs: function() {
            $('.photovault-range-input').on('input', function() {
                const outputId = $(this).data('output');
                const value = $(this).val();
                $('#' + outputId).text(value + '%');
            });
        },
        
        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker({
                    change: function(event, ui) {
                        $(this).trigger('input');
                    }
                });
            }
        },
        
        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            $('.photovault-settings-form').on('submit', function(e) {
                let isValid = true;
                
                // Validate number fields
                $(this).find('input[type="number"]').each(function() {
                    const $input = $(this);
                    const min = parseInt($input.attr('min'));
                    const max = parseInt($input.attr('max'));
                    const value = parseInt($input.val());
                    
                    if (min && value < min) {
                        isValid = false;
                        $input.addClass('error');
                        PhotoVaultSettings.showError($input, 'Value must be at least ' + min);
                    } else if (max && value > max) {
                        isValid = false;
                        $input.addClass('error');
                        PhotoVaultSettings.showError($input, 'Value must be at most ' + max);
                    } else {
                        $input.removeClass('error');
                        PhotoVaultSettings.hideError($input);
                    }
                });
                
                // Validate required fields
                $(this).find('[required]').each(function() {
                    const $input = $(this);
                    if (!$input.val()) {
                        isValid = false;
                        $input.addClass('error');
                        PhotoVaultSettings.showError($input, 'This field is required');
                    } else {
                        $input.removeClass('error');
                        PhotoVaultSettings.hideError($input);
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    PhotoVaultSettings.scrollToFirstError();
                }
            });
            
            // Clear errors on input
            $('.photovault-settings-form input, .photovault-settings-form select').on('input change', function() {
                $(this).removeClass('error');
                PhotoVaultSettings.hideError($(this));
            });
        },
        
        /**
         * Initialize conditional field visibility
         */
        initConditionalFields: function() {
            // Show/hide watermark fields based on enable checkbox
            const $enableWatermark = $('#photovault_enable_watermark');
            const $watermarkFields = $enableWatermark.closest('tr').nextAll('tr');
            
            const toggleWatermarkFields = function() {
                if ($enableWatermark.is(':checked')) {
                    $watermarkFields.show();
                } else {
                    $watermarkFields.hide();
                }
            };
            
            $enableWatermark.on('change', toggleWatermarkFields);
            toggleWatermarkFields(); // Initial state
        },
        
        /**
         * Initialize unsaved changes warning
         */
        initUnsavedChanges: function() {
            let formChanged = false;
            
            $('.photovault-settings-form input, .photovault-settings-form select').on('change', function() {
                formChanged = true;
            });
            
            $('.photovault-settings-form').on('submit', function() {
                formChanged = false;
            });
            
            $(window).on('beforeunload', function(e) {
                if (formChanged) {
                    const message = 'You have unsaved changes. Are you sure you want to leave?';
                    e.returnValue = message;
                    return message;
                }
            });
        },
        
        /**
         * Show error message
         */
        showError: function($field, message) {
            const $error = $('<span class="photovault-error-message">' + message + '</span>');
            $field.after($error);
        },
        
        /**
         * Hide error message
         */
        hideError: function($field) {
            $field.next('.photovault-error-message').remove();
        },
        
        /**
         * Scroll to first error
         */
        scrollToFirstError: function() {
            const $firstError = $('.error').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }
    };
    
    /**
     * Document ready
     */
    $(document).ready(function() {
        PhotoVaultSettings.init();
    });
    
})(jQuery);