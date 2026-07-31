/**
 * BusinessVance Settings Page JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Tab switching — JS fallback when URL params are not used
        $('.bv-nav-tab-wrapper .nav-tab').on('click', function(e) {
            // If it's an anchor link with href, let it navigate naturally
            if ($(this).attr('href') && $(this).attr('href').indexOf('#') === -1) {
                return; // let the browser follow the link
            }
        });

        // Color picker initialization (backup, also loaded inline)
        if ($.fn.wpColorPicker) {
            $('.bv-color-picker').wpColorPicker();
        }
    });

})(jQuery);
