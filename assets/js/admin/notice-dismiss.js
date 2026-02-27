/**
 * Setup Notice Dismissal
 * Handles AJAX dismissal of the setup notice.
 */
jQuery(document).ready(function($) {
    $('.notice[data-dismiss="domilocus-setup"]').on('click', '.notice-dismiss', function() {
        $.post(ajaxurl, {
            action: 'domilocus_dismiss_notice',
            notice: 'setup',
            nonce: domilocusAdminL10n.nonce
        });
    });
});
