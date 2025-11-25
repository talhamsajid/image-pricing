/**
 * Art Print Calculator Admin JavaScript
 * Handles backend functionality and admin interface interactions
 */

jQuery(document).ready(function($) {
    'use strict';
    
    const ArtPrintAdmin = {
        init: function() {
            this.bindEvents();
            this.initFrameManager();
            this.initImageUploader();
        },
        
        bindEvents: function() {
            // Recalculate dimensions button
            $(document).on('click', '#recalculate-dimensions', this.recalculateDimensions);
            
            // Frame management
            $(document).on('click', '.edit-frame', this.openEditFrame);
            $(document).on('click', '.delete-frame', this.deleteFrame);
            $(document).on('submit', '#add-frame-form', this.addFrame);
            $(document).on('submit', '#edit-frame-form', this.updateFrame);
            
            // Modal controls
            $(document).on('click', '.frame-modal-close', this.closeModal);
            $(document).on('click', '.frame-modal', function(e) {
                if (e.target === this) {
                    ArtPrintAdmin.closeModal();
                }
            });
            
            // Image upload handlers
            $(document).on('click', '#upload-frame-image', this.uploadFrameImage);
            $(document).on('click', '#edit-upload-frame-image', this.uploadFrameImage);
            $(document).on('click', '#remove-frame-image, #edit-remove-frame-image', this.removeFrameImage);
            
            // Auto-process when featured image changes
            $(document).on('change', '#set-post-thumbnail', this.onFeaturedImageChange);
        },
        
        recalculateDimensions: function(e) {
            e.preventDefault();
            
            const postId = $('#post_ID').val();
            
            if (!postId) {
                alert('Invalid product ID');
                return;
            }
            
            const $button = $(this);
            const originalText = $button.text();
            
            $button.text('Calculating...').prop('disabled', true);
            
            $.ajax({
                url: app_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'calculate_from_image',
                    nonce: app_admin_ajax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Update the auto dimension fields
                        $('#_auto_width_cm').val(response.data.dimensions.width_cm);
                        $('#_auto_height_cm').val(response.data.dimensions.height_cm);
                        
                        // Refresh the calculator meta box
                        ArtPrintAdmin.refreshCalculatorMetaBox(postId);
                        
                        ArtPrintAdmin.showNotice('success', response.data.message);
                    } else {
                        ArtPrintAdmin.showNotice('error', response.data.message || 'Failed to calculate dimensions');
                    }
                },
                error: function() {
                    ArtPrintAdmin.showNotice('error', 'Error communicating with server');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },
        
        refreshCalculatorMetaBox: function(postId) {
            // Refresh the calculator meta box content
            $.ajax({
                url: app_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'refresh_calculator_metabox',
                    nonce: app_admin_ajax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $('#art-print-prices').html(response.data.html);
                    }
                }
            });
        },
        
        initFrameManager: function() {
            // Initialize frame management if on the frames page
            if ($('#frames-list').length) {
                this.loadFrames();
            }
        },
        
        loadFrames: function() {
            // Frames are already loaded via PHP, but we can add dynamic loading here if needed
        },
        
        addFrame: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = $form.serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=add_frame',
                beforeSend: function() {
                    $form.find('input[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('#frames-list').html(response.data.html);
                        $form[0].reset();
                        $('#frame-image-preview').hide();
                        $('#frame-image-url').val('');
                        ArtPrintAdmin.showNotice('success', response.data.message);
                    } else {
                        ArtPrintAdmin.showNotice('error', response.data.message || 'Failed to add frame');
                    }
                },
                error: function() {
                    ArtPrintAdmin.showNotice('error', 'Error communicating with server');
                },
                complete: function() {
                    $form.find('input[type="submit"]').prop('disabled', false);
                }
            });
        },
        
        openEditFrame: function(e) {
            e.preventDefault();
            
            const frameId = $(this).data('frame-id');
            const $row = $(this).closest('tr');
            
            // Populate edit form with current values
            const frameName = $row.find('td:nth-child(2) strong').text();
            const frameDescription = $row.find('td:nth-child(2) small').text();
            const framePrice = $row.find('td:nth-child(3)').text().replace(/[^0-9.]/g, '');
            const frameSortOrder = $row.find('td:nth-child(5)').text();
            const frameActive = $row.find('.status-active').length > 0;
            
            $('#edit-frame-id').val(frameId);
            $('#edit-frame-name').val(frameName);
            $('#edit-frame-description').val(frameDescription);
            $('#edit-frame-price').val(framePrice);
            $('#edit-frame-sort-order').val(frameSortOrder);
            $('#edit-frame-active').prop('checked', frameActive);
            
            // Show modal
            $('#edit-frame-modal').show();
        },
        
        updateFrame: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = $form.serialize();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=update_frame',
                beforeSend: function() {
                    $form.find('input[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $('#frames-list').html(response.data.html);
                        ArtPrintAdmin.closeModal();
                        ArtPrintAdmin.showNotice('success', response.data.message);
                    } else {
                        ArtPrintAdmin.showNotice('error', response.data.message || 'Failed to update frame');
                    }
                },
                error: function() {
                    ArtPrintAdmin.showNotice('error', 'Error communicating with server');
                },
                complete: function() {
                    $form.find('input[type="submit"]').prop('disabled', false);
                }
            });
        },
        
        deleteFrame: function(e) {
            e.preventDefault();
            
            const frameId = $(this).data('frame-id');
            
            if (!confirm('Are you sure you want to delete this frame? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_frame',
                    frame_id: frameId,
                    delete_frame_nonce: $('#delete_frame_nonce').val() || 'default_nonce'
                },
                success: function(response) {
                    if (response.success) {
                        $('#frames-list').html(response.data.html);
                        ArtPrintAdmin.showNotice('success', response.data.message);
                    } else {
                        ArtPrintAdmin.showNotice('error', response.data.message || 'Failed to delete frame');
                    }
                },
                error: function() {
                    ArtPrintAdmin.showNotice('error', 'Error communicating with server');
                }
            });
        },
        
        closeModal: function() {
            $('.frame-modal').hide();
        },
        
        initImageUploader: function() {
            // Initialize WordPress media uploader for frame images
            this.frameImageUploader = null;
        },
        
        uploadFrameImage: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const isEdit = $button.attr('id').includes('edit');
            const previewSelector = isEdit ? '#edit-frame-image-preview' : '#frame-image-preview';
            const urlInputSelector = isEdit ? '#edit-frame-image-url' : '#frame-image-url';
            const removeButtonSelector = isEdit ? '#edit-remove-frame-image' : '#remove-frame-image';
            
            // Create media uploader if it doesn't exist
            if (!ArtPrintAdmin.frameImageUploader) {
                ArtPrintAdmin.frameImageUploader = wp.media({
                    title: 'Select Frame Image',
                    button: {
                        text: 'Use this image'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                ArtPrintAdmin.frameImageUploader.on('select', function() {
                    const attachment = ArtPrintAdmin.frameImageUploader.state().get('selection').first().toJSON();
                    
                    $(urlInputSelector).val(attachment.url);
                    $(previewSelector).attr('src', attachment.url).show();
                    $(removeButtonSelector).show();
                });
            }
            
            ArtPrintAdmin.frameImageUploader.open();
        },
        
        removeFrameImage: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const isEdit = $button.attr('id').includes('edit');
            const previewSelector = isEdit ? '#edit-frame-image-preview' : '#frame-image-preview';
            const urlInputSelector = isEdit ? '#edit-frame-image-url' : '#frame-image-url';
            
            $(urlInputSelector).val('');
            $(previewSelector).hide();
            $button.hide();
        },
        
        onFeaturedImageChange: function() {
            // Auto-recalculate when featured image changes
            setTimeout(function() {
                if ($('#recalculate-dimensions').length) {
                    $('#recalculate-dimensions').trigger('click');
                }
            }, 1000);
        },
        
        showNotice: function(type, message) {
            // Remove existing notices
            $('.art-print-admin-notice').remove();
            
            // Add new notice
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible art-print-admin-notice"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(500, function() {
                    $notice.remove();
                });
            }, 5000);
            
            // Scroll to top to show notice
            $('html, body').animate({
                scrollTop: 0
            }, 500);
        },
        
        // Bulk operations
        bulkRecalculateAll: function() {
            if (!confirm('This will recalculate prices for all art print products. This may take a while. Continue?')) {
                return;
            }
            
            const $button = $('#bulk-recalculate');
            const originalText = $button.text();
            
            $button.text('Processing...').prop('disabled', true);
            
            // Process in batches to avoid timeout
            ArtPrintAdmin.processBatch('recalculate_all_prices', 0, function(completed, total) {
                $button.text('Processing... (' + completed + '/' + total + ')');
            }, function() {
                $button.text(originalText).prop('disabled', false);
                ArtPrintAdmin.showNotice('success', 'Bulk recalculation completed successfully');
            });
        },
        
        bulkExtractDimensions: function() {
            if (!confirm('This will extract dimensions from all unprocessed images. This may take a while. Continue?')) {
                return;
            }
            
            const $button = $('#bulk-extract-dimensions');
            const originalText = $button.text();
            
            $button.text('Processing...').prop('disabled', true);
            
            ArtPrintAdmin.processBatch('extract_all_dimensions', 0, function(completed, total) {
                $button.text('Processing... (' + completed + '/' + total + ')');
            }, function() {
                $button.text(originalText).prop('disabled', false);
                ArtPrintAdmin.showNotice('success', 'Bulk dimension extraction completed successfully');
            });
        },
        
        processBatch: function(action, offset, progressCallback, completeCallback) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    offset: offset,
                    batch_size: 30,
                    nonce: app_admin_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        if (progressCallback) {
                            progressCallback(data.completed, data.total);
                        }
                        
                        if (data.completed < data.total) {
                            // Continue processing
                            ArtPrintAdmin.processBatch(action, data.next_offset || data.completed, progressCallback, completeCallback);
                        } else {
                            // Complete
                            if (completeCallback) {
                                completeCallback();
                            }
                        }
                    } else {
                        ArtPrintAdmin.showNotice('error', response.data.message || 'Batch processing failed');
                        if (completeCallback) {
                            completeCallback();
                        }
                    }
                },
                error: function() {
                    ArtPrintAdmin.showNotice('error', 'Error during batch processing');
                    if (completeCallback) {
                        completeCallback();
                    }
                }
            });
        }
    };
    
    // Initialize
    ArtPrintAdmin.init();
    
    // Bind bulk operation buttons
    $(document).on('click', '#bulk-recalculate', ArtPrintAdmin.bulkRecalculateAll);
    $(document).on('click', '#bulk-extract-dimensions', ArtPrintAdmin.bulkExtractDimensions);
    
    // Export for external access
    window.ArtPrintAdmin = ArtPrintAdmin;
});