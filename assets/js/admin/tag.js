jQuery(document).ready(function($) {
    let isEditMode = false;
    
    // Open modal for new tag
    $('#pv-add-new-tag, #pv-create-first-tag').on('click', function(e) {
        e.preventDefault();
        isEditMode = false;
        $('#pv-modal-title').text('Add New Tag');
        $('#pv-tag-id').val('');
        $('#pv-tag-name-input').val('');
        $('#pv-tag-color-input').val('#667eea');
        $('#pv-tag-modal').fadeIn(300);
    });
    
    // Open modal for edit
    $('.pv-edit-tag').on('click', function(e) {
        e.preventDefault();
        isEditMode = true;
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        $('#pv-modal-title').text('Edit Tag');
        $('#pv-tag-id').val(tagId);
        $('#pv-tag-name-input').val(tagName);
        $('#pv-tag-color-input').val(tagColor);
        $('#pv-tag-modal').fadeIn(300);
    });
    
    // Close modals
    $('.pv-modal-close, .pv-cancel-tag, .pv-cancel-assign').on('click', function() {
        $(this).closest('.pv-modal').fadeOut(300);
    });
    
    $('.pv-modal-overlay').on('click', function() {
        $(this).closest('.pv-modal').fadeOut(300);
    });
    
    // Save tag
    $('#pv-tag-form').on('submit', function(e) {
        e.preventDefault();
        
        const tagId = $('#pv-tag-id').val();
        const tagName = $('#pv-tag-name-input').val().trim();
        const tagColor = $('#pv-tag-color-input').val();
        
        if (!tagName) {
            alert('Please enter a tag name');
            return;
        }
        
        const action = isEditMode ? 'update_tag' : 'add_tag';
        const data = {
            action: action,
            nonce: 'photovault_nonce',
            name: tagName,
            color: tagColor
        };
        
        if (isEditMode) {
            data.tag_id = tagId;
        } else {
            data.tag_name = tagName;
            data.image_id = 0;
        }
        
        $('#pv-save-tag').prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error saving tag');
                    $('#pv-save-tag').prop('disabled', false).text('Save Tag');
                }
            },
            error: function() {
                alert('Error saving tag');
                $('#pv-save-tag').prop('disabled', false).text('Save Tag');
            }
        });
    });
    
    // Delete tag
    $('.pv-delete-tag').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this tag? This will remove it from all images.')) {
            return;
        }
        
        const tagId = $(this).data('tag-id');
        const $card = $(this).closest('.pv-tag-card');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_tag',
                nonce: 'photovault_nonce',
                tag_id: tagId
            },
            success: function(response) {
                if (response.success) {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.pv-tag-card').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || 'Error deleting tag');
                }
            },
            error: function() {
                alert('Error deleting tag');
            }
        });
    });
    
    // View images by tag
    $('.pv-view-tag-images').on('click', function(e) {
        e.preventDefault();
        
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        // Update modal title
        $('#pv-modal-tag-name').text(tagName);
        $('#pv-modal-tag-badge').css('background-color', tagColor).text(tagName);
        
        // Show modal and loading state
        $('#pv-images-modal').fadeIn(300);
        $('#pv-images-loading').show();
        $('#pv-images-grid').hide().empty();
        $('#pv-images-empty').hide();
        
        // Load images
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_images_by_tag',
                nonce: 'photovault_nonce',
                tag_id: tagId,
                limit: 50,
                offset: 0
            },
            success: function(response) {
                $('#pv-images-loading').hide();
                
                if (response.success && response.data.images && response.data.images.length > 0) {
                    const images = response.data.images;
                    const $grid = $('#pv-images-grid');
                    
                    images.forEach(function(image) {
                        const html = `
                            <div class="pv-image-item" data-image-id="${image.id}" data-full-url="${image.full_url || image.thumbnail_url}">
                                <div class="pv-image-wrapper">
                                    <img src="${image.thumbnail_url}" alt="${image.title}" loading="lazy">
                                    <button class="pv-remove-tag-from-image" 
                                            data-image-id="${image.id}" 
                                            data-tag-id="${tagId}"
                                            title="Remove tag from this image">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                                <div class="pv-image-info">
                                    <div class="pv-image-title">${image.title}</div>
                                    <div class="pv-image-date">${image.formatted_date}</div>
                                </div>
                            </div>
                        `;
                        $grid.append(html);
                    });
                    
                    $grid.show();
                } else {
                    $('#pv-images-empty').show();
                }
            },
            error: function(xhr, status, error) {
                $('#pv-images-loading').hide();
                $('#pv-images-empty').html(`
                    <p style="color: #dc2626;">
                        Error loading images. Please try again.
                    </p>
                `).show();
                console.error('AJAX Error:', error, xhr.responseText);
            }
        });
    });
    
    // View image in lightbox
    $(document).on('click', '.pv-image-item', function(e) {
        // Don't open lightbox if clicking remove button
        if ($(e.target).closest('.pv-remove-tag-from-image').length) {
            return;
        }
        
        const fullUrl = $(this).data('full-url');
        const title = $(this).find('.pv-image-title').text();
        const date = $(this).find('.pv-image-date').text();
        
        $('#pv-lightbox-image').attr('src', fullUrl);
        $('#pv-lightbox-title').text(title);
        $('#pv-lightbox-date').text(date);
        $('#pv-lightbox-modal').fadeIn(300);
    });
    
    // Remove tag from image (in tag images modal)
    $(document).on('click', '.pv-remove-tag-from-image', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!confirm('Remove this tag from the image?')) {
            return;
        }
        
        const $btn = $(this);
        const $item = $btn.closest('.pv-image-item');
        const imageId = $btn.data('image-id');
        const tagId = $btn.data('tag-id');
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_tag',
                nonce: 'photovault_nonce',
                image_id: imageId,
                tag_id: tagId
            },
            success: function(response) {
                if (response.success) {
                    // Remove image from grid with animation
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if grid is empty
                        if ($('#pv-images-grid .pv-image-item').length === 0) {
                            $('#pv-images-grid').hide();
                            $('#pv-images-empty').show();
                        }
                    });
                    
                    // Update tag count in main view
                    const $tagCard = $('.pv-tag-card[data-tag-id="' + tagId + '"]');
                    const $countSpan = $tagCard.find('.pv-tag-count');
                    const currentCount = parseInt($countSpan.text());
                    const newCount = currentCount - 1;
                    
                    if (newCount > 0) {
                        $countSpan.text(newCount + ' images');
                    } else {
                        $countSpan.text('0 images');
                    }
                } else {
                    alert(response.data.message || 'Failed to remove tag');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Error removing tag');
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Assign images to tag
    let selectedImages = [];
    let currentAssignTagId = null;
    
    $('.pv-assign-images').on('click', function(e) {
        e.preventDefault();
        
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        currentAssignTagId = tagId;
        selectedImages = [];
        
        // Update modal title
        $('#pv-assign-tag-name').text(tagName);
        $('#pv-assign-tag-badge').css('background-color', tagColor).text(tagName);
        $('#pv-selected-count').text('0');
        
        // Show modal and loading
        $('#pv-assign-images-modal').fadeIn(300);
        $('#pv-assign-loading').show();
        $('#pv-assign-images-grid').hide().empty();
        $('#pv-assign-empty').hide();
        
        // Load all user images
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pv_get_all_images',
                nonce: 'photovault_nonce'
            },
            success: function(response) {
                $('#pv-assign-loading').hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayAssignImages(response.data);
                    $('#pv-assign-images-grid').show();
                } else {
                    $('#pv-assign-empty').show();
                }
            },
            error: function() {
                $('#pv-assign-loading').hide();
                $('#pv-assign-empty').html('<p style="color: #dc2626;">Error loading images.</p>').show();
            }
        });
    });
    
    // Display images in assign modal
    function displayAssignImages(images) {
        const $grid = $('#pv-assign-images-grid');
        $grid.empty();
        
        images.forEach(function(image) {
            const html = `
                <div class="pv-assign-image-item" data-image-id="${image.id}">
                    <div class="pv-assign-image-wrapper">
                        <img src="${image.thumbnail_url}" alt="${image.title}" loading="lazy">
                        <div class="pv-assign-checkbox">
                            <span class="dashicons dashicons-yes"></span>
                        </div>
                    </div>
                    <div class="pv-assign-image-title">${image.title}</div>
                </div>
            `;
            $grid.append(html);
        });
    }
    
    // Toggle image selection
    $(document).on('click', '.pv-assign-image-item', function() {
        const imageId = $(this).data('image-id');
        
        $(this).toggleClass('selected');
        
        if ($(this).hasClass('selected')) {
            if (!selectedImages.includes(imageId)) {
                selectedImages.push(imageId);
            }
        } else {
            selectedImages = selectedImages.filter(id => id !== imageId);
        }
        
        $('#pv-selected-count').text(selectedImages.length);
    });
    
    // Select all
    $('#pv-assign-select-all').on('click', function() {
        $('.pv-assign-image-item').addClass('selected');
        selectedImages = [];
        $('.pv-assign-image-item').each(function() {
            selectedImages.push($(this).data('image-id'));
        });
        $('#pv-selected-count').text(selectedImages.length);
    });
    
    // Deselect all
    $('#pv-assign-deselect-all').on('click', function() {
        $('.pv-assign-image-item').removeClass('selected');
        selectedImages = [];
        $('#pv-selected-count').text('0');
    });
    
    // Search images
    $('#pv-assign-search').on('input', function() {
        const query = $(this).val().toLowerCase();
        
        $('.pv-assign-image-item').each(function() {
            const title = $(this).find('.pv-assign-image-title').text().toLowerCase();
            if (title.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Submit assignment
    $('#pv-assign-submit').on('click', function() {
        if (selectedImages.length === 0) {
            alert('Please select at least one image');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Assigning...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pv_bulk_assign_tag',
                nonce: 'photovault_nonce',
                tag_id: currentAssignTagId,
                image_ids: selectedImages
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    
                    // Update tag count in main view
                    const $tagCard = $('.pv-tag-card[data-tag-id="' + currentAssignTagId + '"]');
                    const $countSpan = $tagCard.find('.pv-tag-count');
                    const newCount = response.data.new_count;
                    $countSpan.text(newCount + ' images');
                    
                    // Close modal
                    $('#pv-assign-images-modal').fadeOut(300);
                    selectedImages = [];
                } else {
                    alert(response.data.message || 'Error assigning images');
                }
                
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span>Assign Selected Images');
            },
            error: function() {
                alert('Error assigning images');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span>Assign Selected Images');
            }
        });
    });
});