/**
 * SMTP Settings Toggle
 * Toggles SMTP fields visibility based on email transport method selection.
 */
jQuery(function($) {
    function toggleSMTPFields() {
        var method = $('#domilocus_manager_email_transport').val();
        $('.domilocus-smtp-field').toggle(method === 'smtp');
    }

    toggleSMTPFields();
    $('#domilocus_manager_email_transport').on('change', toggleSMTPFields);
});
