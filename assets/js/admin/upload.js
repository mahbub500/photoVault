/**
 * PhotoVault - Upload Handler
 * File: assets/js/admin/upload.js
 */

(function($) {
    'use strict';

    window.PhotoVaultUploader = {
        filesToUpload: [],
        uploadQueue: [],
        isUploading: false,
        currentUploadIndex: 0,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // Upload button click
            $('#pv-select-files-btn').on('click', () => $('#pv-file-input').click());
            
            // File input change
            $('#pv-file-input').on('change', function(e) {
                self.handleFileSelect(e.target.files);
            });

            // Drag and drop
            const dropZone = document.getElementById('pv-drop-zone');
            if (dropZone) {
                dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropZone).addClass('pv-drag-over');
                });

                dropZone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropZone).removeClass('pv-drag-over');
                });

                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropZone).removeClass('pv-drag-over');
                    
                    const files = Array.from(e.dataTransfer.files).filter(file => 
                        file.type.startsWith('image/')
                    );
                    
                    self.handleFileSelect(files);
                });
            }

            // Start upload
            $('#pv-start-upload').on('click', () => this.startUpload());
            
            // Cancel upload
            $('#pv-cancel-upload').on('click', () => this.cancelUpload());
            
            // Remove preview
            $(document).on('click', '.pv-remove-preview', function() {
                const index = $(this).data('index');
                self.removeFile(index);
            });
        },

        handleFileSelect: function(files) {
            const fileArray = Array.isArray(files) ? files : Array.from(files);
            
            // Validate files
            const validFiles = fileArray.filter(file => this.validateFile(file));
            
            if (validFiles.length === 0) {
                alert(photoVault.i18n.uploadError || 'No valid files selected');
                return;
            }

            this.filesToUpload = this.filesToUpload.concat(validFiles);
            this.renderPreviews();
        },

        validateFile: function(file) {
            // Check file type
            if (!file.type.startsWith('image/')) {
                alert(`${file.name}: Not an image file`);
                return false;
            }

            // Check file size
            const maxSize = photoVault.maxFileSize || 10485760; // 10MB default
            if (file.size > maxSize) {
                alert(`${file.name}: File too large (max ${this.formatFileSize(maxSize)})`);
                return false;
            }

            return true;
        },

        renderPreviews: function() {
            const $previews = $('#pv-upload-previews');
            $previews.empty();

            this.filesToUpload.forEach((file, index) => {
                const reader = new FileReader();
                
                reader.onload = (e) => {
                    const $preview = $(`
                        <div class="pv-upload-preview" data-index="${index}">
                            <img src="${e.target.result}" alt="${file.name}">
                            <div class="pv-preview-info">
                                <span class="pv-preview-name">${file.name}</span>
                                <span class="pv-preview-size">${this.formatFileSize(file.size)}</span>
                            </div>
                            <button class="pv-remove-preview" data-index="${index}" type="button">×</button>
                        </div>
                    `);
                    $previews.append($preview);
                };
                
                reader.readAsDataURL(file);
            });
        },

        removeFile: function(index) {
            this.filesToUpload.splice(index, 1);
            this.renderPreviews();
        },

        startUpload: function() {
            if (this.filesToUpload.length === 0) {
                alert('Please select files to upload');
                return;
            }

            if (this.isUploading) {
                return;
            }

            this.isUploading = true;
            this.currentUploadIndex = 0;
            this.uploadQueue = [...this.filesToUpload];

            $('#pv-upload-progress').show();
            $('#pv-start-upload').prop('disabled', true).text(photoVault.i18n.uploading || 'Uploading...');

            this.uploadNext();
        },

        uploadNext: function() {
            if (this.currentUploadIndex >= this.uploadQueue.length) {
                this.uploadComplete();
                return;
            }

            const file = this.uploadQueue[this.currentUploadIndex];
            const chunkSize = photoVault.chunkSize || 1048576; // 1MB

            // Check if file needs chunking
            if (file.size > chunkSize * 5) {
                this.uploadChunked(file);
            } else {
                this.uploadSingle(file);
            }
        },

        uploadSingle: function(file) {
            const self = this;
            const formData = new FormData();
            
            formData.append('action', 'pv_upload_image');
            formData.append('nonce', photoVault.nonce);
            formData.append('file', file);
            formData.append('title', file.name.replace(/\.[^/.]+$/, ""));
            formData.append('visibility', $('#pv-upload-visibility').val());
            
            const albumId = $('#pv-upload-album').val();
            const tags = $('#pv-upload-tags').val();
            
            if (albumId) formData.append('album_id', albumId);
            if (tags) formData.append('tags', tags);

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            self.updateProgress(e.loaded, e.total);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        self.currentUploadIndex++;
                        self.uploadNext();
                    } else {
                        self.uploadError(response.data.message || 'Upload failed');
                    }
                },
                error: function(xhr) {
                    self.uploadError('Network error');
                }
            });
        },

        uploadChunked: function(file) {
            const self = this;
            const chunkSize = photoVault.chunkSize || 1048576; // 1MB
            const chunks = Math.ceil(file.size / chunkSize);
            const uniqueId = Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            let currentChunk = 0;

            function uploadChunk() {
                const start = currentChunk * chunkSize;
                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('action', 'pv_upload_image');
                formData.append('nonce', photoVault.nonce);
                formData.append('file', chunk, file.name);
                formData.append('chunk_index', currentChunk);
                formData.append('total_chunks', chunks);
                formData.append('unique_id', uniqueId);
                formData.append('title', file.name.replace(/\.[^/.]+$/, ""));
                formData.append('visibility', $('#pv-upload-visibility').val());

                $.ajax({
                    url: photoVault.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            currentChunk++;
                            const progress = (currentChunk / chunks) * 100;
                            self.updateProgressBar(progress);

                            if (currentChunk < chunks) {
                                uploadChunk();
                            } else {
                                // All chunks uploaded
                                self.currentUploadIndex++;
                                self.uploadNext();
                            }
                        } else {
                            self.uploadError(response.data.message || 'Chunk upload failed');
                        }
                    },
                    error: function() {
                        self.uploadError('Chunk upload error');
                    }
                });
            }

            uploadChunk();
        },

        updateProgress: function(loaded, total) {
            const fileProgress = (loaded / total) * 100;
            const totalProgress = ((this.currentUploadIndex + (fileProgress / 100)) / this.uploadQueue.length) * 100;
            this.updateProgressBar(totalProgress);
        },

        updateProgressBar: function(percentage) {
            $('.pv-progress-fill').css('width', percentage + '%');
            $('.pv-progress-text').text(Math.round(percentage) + '%');
        },

        uploadComplete: function() {
            this.isUploading = false;
            this.filesToUpload = [];
            this.uploadQueue = [];
            this.currentUploadIndex = 0;

            $('#pv-upload-progress').hide();
            $('#pv-start-upload').prop('disabled', false).text('Upload');
            $('.pv-progress-fill').css('width', '0%');
            $('.pv-progress-text').text('0%');
            $('#pv-upload-previews').empty();
            $('#pv-file-input').val('');

            alert(photoVault.i18n.uploadSuccess || 'Upload completed successfully!');

            // Reload images if PhotoVaultAdmin exists
            if (window.PhotoVaultAdmin) {
                window.PhotoVaultAdmin.loadImages();
            }

            $('#pv-upload-modal').fadeOut();
        },

        uploadError: function(message) {
            this.isUploading = false;
            $('#pv-start-upload').prop('disabled', false).text('Upload');
            alert(message || 'Upload failed');
        },

        cancelUpload: function() {
            if (this.isUploading) {
                if (!confirm('Cancel ongoing upload?')) {
                    return;
                }
            }

            this.isUploading = false;
            this.filesToUpload = [];
            this.uploadQueue = [];
            this.currentUploadIndex = 0;

            $('#pv-upload-progress').hide();
            $('#pv-upload-previews').empty();
            $('#pv-file-input').val('');
            $('.pv-progress-fill').css('width', '0%');
            $('.pv-progress-text').text('0%');
            $('#pv-upload-modal').fadeOut();
        },

        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        window.PhotoVaultUploader.init();
    });

})(jQuery);