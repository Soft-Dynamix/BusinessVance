/**
 * BusinessVance Services Manager — Frontend Scripts
 *
 * Handles category filtering on the frontend.
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // ── Category Filter ───────────────────────────────────────────────
        $('.bv-cat-btn').on('click', function () {
            var category = $(this).data('category');

            // Toggle active class.
            $('.bv-cat-btn').removeClass('bv-cat-btn-active');
            $(this).addClass('bv-cat-btn-active');

            if (category === 'all') {
                // Show all services and plans.
                $('.bv-services-table tbody tr').show();
                $('.bv-plan-card').show();
                return;
            }

            // Get the category ID from the button's data-cat-id attribute.
            var catId = $(this).data('cat-id');

            if (catId) {
                // Hide all, then show only matching category.
                $('.bv-services-table tbody tr').each(function () {
                    var rowCatId = $(this).data('category-id');
                    $(this).toggle(rowCatId && String(rowCatId) === String(catId));
                });
                $('.bv-plan-card').each(function () {
                    var cardCatId = $(this).data('category-id');
                    $(this).toggle(cardCatId && String(cardCatId) === String(catId));
                });
            }
        });

    });

})(jQuery);