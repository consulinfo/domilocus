/**
 * Domilocus WP Admin JavaScript
 */

(function($) {
    'use strict';

    var DomilocusAdmin = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Add any general admin JavaScript functionality here
            console.log('Domilocus Admin initialized');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        DomilocusAdmin.init();
    });

})(jQuery);