/**
 * Confirmation URL Clipboard Copy
 * Handles copying booking confirmation URLs to clipboard.
 */
jQuery(function($) {
    $(document).on('click', '.domilocus-copy-confirmation-url', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $input = $button.closest('.domilocus-confirmation-tools').find('.domilocus-confirmation-url');

        if (!$input.length) {
            return;
        }

        const url = $input.val();
        if (!url) {
            return;
        }

        const originalLabel = $button.data('originalLabel') || $button.text();
        const copiedLabel = $button.data('copiedLabel') || $button.attr('data-copied-label') || 'Copied!';

        const indicateSuccess = function() {
            $button.data('originalLabel', originalLabel);
            $button.text(copiedLabel);
            setTimeout(function() {
                $button.text(originalLabel);
            }, 2000);
        };

        const fallbackCopy = function() {
            $input.trigger('focus').trigger('select');
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    indicateSuccess();
                }
            } catch (err) {
                console.error('Domilocus copy fallback failed', err);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url)
                .then(function() {
                    indicateSuccess();
                })
                .catch(function() {
                    fallbackCopy();
                });
        } else {
            fallbackCopy();
        }
    });
});
