/**
 * BusinessVance Services Manager — Admin Scripts
 *
 * @package BusinessVance_Services_Manager
 * @since   1.0.0
 */

(function ($) {
    'use strict';

    // ── Sortable Rows ─────────────────────────────────────────────────────
    function initSortable() {
        $('.bv-sortable-rows').sortable({
            handle: '.bv-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            update: function () {
                var $table = $(this).closest('.bv-sortable-table');
                var type = $(this).data('type');
                var nonce = $table.data('nonce');
                var order = [];

                $(this).children('tr[data-id]').each(function () {
                    order.push($(this).data('id'));
                });

                $.post(ajaxurl, {
                    action: 'bv_reorder_' + type,
                    nonce: nonce,
                    order: order,
                });
            },
        });
    }

    // ── Button Type Toggle (show/hide URL field) ──────────────────────────
    function initButtonTypeToggle() {
        $('select[name="button_type"], #svc-button-type, #plan-button-type').on('change', function () {
            var val = $(this).val();
            var $urlField = $(this).closest('.bv-form-card, .bv-form-sidebar').find('#svc-url-field, #plan-url-field');
            if (val === 'link') {
                $urlField.show();
            } else {
                $urlField.hide();
            }
        });
    }

    // ── Auto-generate slug from name ─────────────────────────────────────
    function initSlugGenerator() {
        $('#svc-name').on('input', function () {
            if (!$('#svc-slug').val()) {
                var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                $('#svc-slug').val(slug);
            }
        });
    }

    // ── Plan Feature Management ───────────────────────────────────────────
    function initPlanFeatures() {
        // Add feature.
        $('#bv-add-feature').on('click', function () {
            var html = '<div class="bv-feature-row">' +
                '<input type="text" name="features[]" value="" class="regular-text" placeholder="Feature description">' +
                '<button type="button" class="button bv-remove-feature" title="Remove feature">&minus;</button>' +
                '</div>';
            $('#bv-features-list').append(html);
        });

        // Remove feature.
        $(document).on('click', '.bv-remove-feature', function () {
            $(this).closest('.bv-feature-row').fadeOut(200, function () {
                $(this).remove();
            });
        });
    }

    // ── WooCommerce Product Browser Modal ─────────────────────────────────
    var $modal = $('#bv-wc-modal');
    var $targetInput = null;

    function initWCModal() {
        // Open modal.
        $(document).on('click', '.bv-browse-products', function () {
            $targetInput = $('#' + $(this).data('target'));
            $modal.show();
            $('#bv-wc-search').val('').focus().trigger('input');
        });

        // Close modal.
        $('.bv-wc-modal-close, .bv-wc-modal-backdrop').on('click', function () {
            $modal.hide();
        });

        // Search products with debounce.
        var searchTimer;
        $('#bv-wc-search').on('input', function () {
            clearTimeout(searchTimer);
            var query = $(this).val();

            searchTimer = setTimeout(function () {
                $.post(ajaxurl, {
                    action: 'bv_search_wc_products',
                    nonce: BVAdmin.nonce_wc_search,
                    search: query,
                }, function (res) {
                    if (!res.success) {
                        $('#bv-wc-results').html('<div class="bv-wc-no-results">WooCommerce is not active.</div>');
                        return;
                    }

                    var products = res.data;
                    if (products.length === 0) {
                        $('#bv-wc-results').html('<div class="bv-wc-no-results">No products found.</div>');
                        return;
                    }

                    var html = '';
                    products.forEach(function (p) {
                        html += '<div class="bv-wc-product-item" data-id="' + p.id + '">' +
                            '<div><div class="name">' + $('<span>').text(p.name).html() + '</div>' +
                            '<div class="meta">ID: ' + p.id + ' &middot; SKU: ' + $('<span>').text(p.sku).html() + '</div></div>' +
                            '<div class="price">' + p.price + '</div>' +
                            '</div>';
                    });

                    $('#bv-wc-results').html(html);
                });
            }, 300);
        });

        // Select product.
        $(document).on('click', '.bv-wc-product-item', function () {
            if ($targetInput) {
                $targetInput.val($(this).data('id'));
            }
            $modal.hide();
        });

        // Close on Escape.
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $modal.is(':visible')) {
                $modal.hide();
            }
        });
    }

    // ── Init on DOM Ready ─────────────────────────────────────────────────
    $(document).ready(function () {
        initSortable();
        initButtonTypeToggle();
        initSlugGenerator();
        initPlanFeatures();
        initWCModal();
    });

})(jQuery);