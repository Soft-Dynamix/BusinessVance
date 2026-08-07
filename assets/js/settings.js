/**
 * BusinessVance Settings Page JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Color picker initialization (backup, also loaded inline)
        if ($.fn.wpColorPicker) {
            $('.bv-color-picker').wpColorPicker();
        }
    });

})(jQuery);
