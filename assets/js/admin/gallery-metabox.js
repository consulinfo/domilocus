/**
 * Gallery Metabox Media Uploader
 * Handles image gallery management in apartment metabox.
 */
jQuery(document).ready(function($) {
    var mediaUploader;
    
    $('#domilocus-add-gallery-images').click(function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: domilocusGalleryL10n.selectImages,
            button: {
                text: domilocusGalleryL10n.addImages
            },
            multiple: true
        });
        
        mediaUploader.on('select', function() {
            var attachments = mediaUploader.state().get('selection').toJSON();
            var currentIds = $('#domilocus_gallery').val();
            var ids = currentIds ? currentIds.split(',') : [];
            
            attachments.forEach(function(attachment) {
                if (ids.indexOf(attachment.id.toString()) === -1) {
                    ids.push(attachment.id);
                    
                    var imageHtml = '<div class="domilocus-gallery-image" data-id="' + attachment.id + '">' +
                        '<img src="' + (attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) + '" />' +
                        '<button type="button" class="remove-image" title="' + domilocusGalleryL10n.removeImage + '">×</button>' +
                        '</div>';
                    
                    $('#domilocus-gallery-images').append(imageHtml);
                }
            });
            
            $('#domilocus_gallery').val(ids.join(','));
        });
        
        mediaUploader.open();
    });
    
    $(document).on('click', '.domilocus-gallery-image .remove-image', function(e) {
        e.preventDefault();
        var $image = $(this).closest('.domilocus-gallery-image');
        var imageId = $image.data('id');
        var currentIds = $('#domilocus_gallery').val().split(',');
        var index = currentIds.indexOf(imageId.toString());
        
        if (index > -1) {
            currentIds.splice(index, 1);
        }
        
        $('#domilocus_gallery').val(currentIds.join(','));
        $image.fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Make gallery sortable
    $('#domilocus-gallery-images').sortable({
        items: '.domilocus-gallery-image',
        cursor: 'move',
        update: function() {
            var ids = [];
            $('.domilocus-gallery-image').each(function() {
                ids.push($(this).data('id'));
            });
            $('#domilocus_gallery').val(ids.join(','));
        }
    });
});
