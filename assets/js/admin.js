/**
 * BusinessVance Services Manager - Admin JavaScript
 * Handles AJAX CRUD, modals, drag-and-drop reordering
 */
(function($) {
    'use strict';

    if (typeof bvAdmin === 'undefined') return;

    var ajaxUrl = bvAdmin.ajax_url;
    var nonce   = bvAdmin.nonce;
    var strings = bvAdmin.strings;

    /* ============================================
       MODAL MANAGEMENT
       ============================================ */

    function openModal(modalId) {
        $('#' + modalId).show();
        $('body').css('overflow', 'hidden');
    }

    function closeModal(modalId) {
        $('#' + modalId).hide();
        $('body').css('overflow', '');
    }

    function resetForm(formId) {
        $('#' + formId)[0].reset();
        // Reset checkbox states
        $('#' + formId + ' input[type="checkbox"]').prop('checked', false);
        // Re-check defaults
        $('#' + formId + ' input[name="is_visible"]').prop('checked', true);
        // Reset hidden ID
        $('#' + formId + ' input[name="id"]').val('');
        // Reset features list
        $('#bv-features-list').empty();
        // Reset WooCommerce product selects
        $('.bv-woo-product-select').val(0);
        $('.bv-woo-search').val('');
    }

    // Close modal on overlay click or X button
    $(document).on('click', '.bv-modal-overlay, .bv-modal-close, .bv-cancel-btn', function(e) {
        var modal = $(this).closest('.bv-modal');
        if (modal.length) {
            modal.hide();
            $('body').css('overflow', '');
        }
    });

    // Close on Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.bv-modal:visible').hide();
            $('body').css('overflow', '');
        }
    });

    /* ============================================
       SERVICES
       ============================================ */

    // Open Add Service modal
    $(document).on('click', '#bv-add-service', function() {
        $('#bv-service-modal-title').text('Add New Service');
        resetForm('bv-service-form');
        $('#bv-service-form input[name="price"]').val('R0');
        $('#bv-service-form input[name="button_label"]').val('Get Started');
        openModal('bv-service-modal');
    });

    // Open Edit Service modal
    $(document).on('click', '#bv-services-table .bv-edit-btn', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');

        $.post(ajaxUrl, {
            action: 'bv_get_service',
            nonce: nonce,
            id: id
        }, function(response) {
            if (response.success) {
                var svc = response.data;
                $('#bv-service-modal-title').text('Edit Service');
                $('#bv-service-form input[name="id"]').val(svc.id);
                $('#bv-service-form input[name="name"]').val(svc.name);
                $('#bv-service-form textarea[name="description"]').val(svc.description);
                $('#bv-service-form input[name="price"]').val(svc.price);
                $('#bv-service-form input[name="price_display"]').val(svc.price_display);
                $('#bv-service-form select[name="icon"]').val(svc.icon);
                $('#bv-service-form input[name="button_label"]').val(svc.button_label);
                $('#bv-service-form select[name="service_type"]').val(svc.service_type);
                $('#bv-service-form select[name="woo_product_id"]').val(svc.woo_product_id || 0);
                $('#bv-service-form select[name="questionnaire_template_id"]').val(svc.questionnaire_template_id || 0);
                $('#bv-service-form select[name="category_id"]').val(svc.category_id);
                $('#bv-service-form input[name="is_visible"]').prop('checked', svc.is_visible == 1);
                $('#bv-service-form input[name="is_featured"]').prop('checked', svc.is_featured == 1);
                openModal('bv-service-modal');
            } else {
                alert(response.data.message || strings.error);
            }
        });
    });

    // Save Service
    $(document).on('submit', '#bv-service-form', function(e) {
        e.preventDefault();

        var $btn = $(this).find('.bv-gold-btn');
        var originalText = $btn.text();

        $btn.text(strings.saving).prop('disabled', true);

        var formData = $(this).serialize();
        formData += '&action=bv_save_service&nonce=' + nonce;

        $.post(ajaxUrl, formData, function(response) {
            if (response.success) {
                alert(strings.saved);
                location.reload();
            } else {
                alert(response.data.message || strings.error);
                $btn.text(originalText).prop('disabled', false);
            }
        }).fail(function() {
            alert(strings.error);
            $btn.text(originalText).prop('disabled', false);
        });
    });

    // Delete Service
    $(document).on('click', '#bv-services-table .bv-delete-btn', function() {
        if (!confirm(strings.confirm_delete)) return;

        var id = $(this).data('id');

        $.post(ajaxUrl, {
            action: 'bv_delete_service',
            nonce: nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || strings.error);
            }
        });
    });

    /* ============================================
       PLANS
       ============================================ */

    // Open Add Plan modal
    $(document).on('click', '#bv-add-plan', function() {
        $('#bv-plan-modal-title').text('Add New Plan');
        resetForm('bv-plan-form');
        $('#bv-plan-form input[name="price"]').val('R0/mo');
        $('#bv-plan-form input[name="button_label"]').val('Subscribe Now');
        $('#bv-plan-form input[name="is_visible"]').prop('checked', true);
        $('#bv-features-list').empty();
        addFeatureRow();
        addFeatureRow();
        openModal('bv-plan-modal');
    });

    // Open Edit Plan modal
    $(document).on('click', '#bv-plans-table .bv-edit-btn', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');

        // Get plan data via AJAX
        $.post(ajaxUrl, {
            action: 'bv_get_plan',
            nonce: nonce,
            id: id
        }, function(response) {
            if (response.success) {
                var plan = response.data;
                $('#bv-plan-modal-title').text('Edit Plan');
                $('#bv-plan-form input[name="id"]').val(plan.id);
                $('#bv-plan-form input[name="name"]').val(plan.name);
                $('#bv-plan-form input[name="subtitle"]').val(plan.subtitle);
                $('#bv-plan-form input[name="price"]').val(plan.price);
                $('#bv-plan-form input[name="color"]').val(plan.color);
                $('#bv-plan-form input[name="button_label"]').val(plan.button_label);
                $('#bv-plan-form select[name="woo_product_id"]').val(plan.woo_product_id || 0);
                $('#bv-plan-form select[name="category_id"]').val(plan.category_id);
                $('#bv-plan-form input[name="is_visible"]').prop('checked', plan.is_visible == 1);
                $('#bv-plan-form input[name="is_featured"]').prop('checked', plan.is_featured == 1);

                // Populate features
                $('#bv-features-list').empty();
                if (plan.features && plan.features.length > 0) {
                    plan.features.forEach(function(f) {
                        addFeatureRow(f.feature_text);
                    });
                } else {
                    addFeatureRow();
                }

                openModal('bv-plan-modal');
            }
        });
    });

    // Save Plan
    $(document).on('submit', '#bv-plan-form', function(e) {
        e.preventDefault();

        var $btn = $(this).find('.bv-gold-btn');
        var originalText = $btn.text();

        $btn.text(strings.saving).prop('disabled', true);

        // Collect features
        var features = [];
        $('#bv-features-list input[type="text"]').each(function() {
            features.push($(this).val());
        });

        var formData = $(this).serialize();
        formData += '&action=bv_save_plan&nonce=' + nonce;

        // Remove features from form data (we send as array)
        formData = formData.replace(/&features=[^&]*/g, '');

        // Append features array
        features.forEach(function(f, i) {
            formData += '&features[]=' + encodeURIComponent(f);
        });

        $.post(ajaxUrl, formData, function(response) {
            if (response.success) {
                alert(strings.saved);
                location.reload();
            } else {
                alert(response.data.message || strings.error);
                $btn.text(originalText).prop('disabled', false);
            }
        }).fail(function() {
            alert(strings.error);
            $btn.text(originalText).prop('disabled', false);
        });
    });

    // Delete Plan
    $(document).on('click', '#bv-plans-table .bv-delete-btn', function() {
        if (!confirm(strings.confirm_delete)) return;

        var id = $(this).data('id');

        $.post(ajaxUrl, {
            action: 'bv_delete_plan',
            nonce: nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || strings.error);
            }
        });
    });

    // Feature row management
    function addFeatureRow(value) {
        var row = $('<div class="bv-feature-row">' +
            '<input type="text" placeholder="Enter feature..." value="' + (value || '') + '">' +
            '<button type="button" class="bv-remove-feature">&times;</button>' +
            '</div>');
        $('#bv-features-list').append(row);
    }

    $(document).on('click', '#bv-add-feature', function() {
        addFeatureRow('');
    });

    $(document).on('click', '.bv-remove-feature', function() {
        $(this).closest('.bv-feature-row').remove();
    });

    /* ============================================
       CATEGORIES
       ============================================ */

    // Auto-generate slug from name
    $(document).on('input', '#cat-name', function() {
        var slug = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        $('#cat-slug').val(slug);
    });

    // Open Add Category modal
    $(document).on('click', '#bv-add-category', function() {
        $('#bv-category-modal-title').text('Add New Category');
        resetForm('bv-category-form');
        openModal('bv-category-modal');
    });

    // Open Edit Category modal
    $(document).on('click', 'table .bv-edit-btn[data-type="category"]', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');
        var name = row.find('strong').text().trim();
        var slug = row.find('code').text().trim();

        $('#bv-category-modal-title').text('Edit Category');
        $('#bv-category-form input[name="id"]').val(id);
        $('#bv-category-form input[name="name"]').val(name);
        $('#bv-category-form input[name="slug"]').val(slug);
        openModal('bv-category-modal');
    });

    // Save Category
    $(document).on('submit', '#bv-category-form', function(e) {
        e.preventDefault();

        var $btn = $(this).find('.bv-gold-btn');
        var originalText = $btn.text();

        $btn.text(strings.saving).prop('disabled', true);

        var formData = $(this).serialize();
        formData += '&action=bv_save_category&nonce=' + nonce;

        $.post(ajaxUrl, formData, function(response) {
            if (response.success) {
                alert(strings.saved);
                location.reload();
            } else {
                alert(response.data.message || strings.error);
                $btn.text(originalText).prop('disabled', false);
            }
        }).fail(function() {
            alert(strings.error);
            $btn.text(originalText).prop('disabled', false);
        });
    });

    // Delete Category
    $(document).on('click', 'table .bv-delete-btn[data-type="category"]', function() {
        if (!confirm(strings.confirm_delete)) return;

        var id = $(this).data('id');

        $.post(ajaxUrl, {
            action: 'bv_delete_category',
            nonce: nonce,
            id: id
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || strings.error);
            }
        });
    });

    /* ============================================
       TOGGLE VISIBILITY
       ============================================ */

    $(document).on('click', '.bv-toggle-btn', function() {
        var id = $(this).data('id');
        var type = $(this).data('type');
        var $btn = $(this);

        $.post(ajaxUrl, {
            action: 'bv_toggle_visibility',
            nonce: nonce,
            id: id,
            type: type
        }, function(response) {
            if (response.success) {
                if (response.data.is_visible) {
                    $btn.removeClass('bv-inactive').addClass('bv-active');
                    $btn.html('👁️');
                    $btn.attr('title', 'Click to hide');
                } else {
                    $btn.removeClass('bv-active').addClass('bv-inactive');
                    $btn.html('🚫');
                    $btn.attr('title', 'Click to show');
                }
            }
        });
    });

    /* ============================================
       DRAG & DROP REORDERING
       ============================================ */

    function initSortable(tableId, actionName) {
        var $table = $('#' + tableId);
        if ($table.length === 0) return;

        $table.find('tbody').sortable({
            handle: '.bv-sort-handle',
            placeholder: 'bv-sort-placeholder',
            axis: 'y',
            opacity: 0.7,
            tolerance: 'pointer',
            update: function() {
                var order = [];
                $table.find('tbody tr').each(function(i) {
                    order.push($(this).data('id'));
                });

                $.post(ajaxUrl, {
                    action: actionName,
                    nonce: nonce,
                    order: order
                }, function(response) {
                    if (response.success) {
                        // Brief visual feedback
                        $table.find('tbody tr').each(function(i) {
                            $(this).find('td').effect('highlight', { color: '#D4AF37' }, 300);
                        });
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        initSortable('bv-services-table', 'bv_reorder_services');
        initSortable('bv-plans-table', 'bv_reorder_plans');
    });

    /* ============================================
       AJAX: GET PLAN (for edit)
       ============================================ */

    // Register the AJAX handler in PHP if needed, or use inline data
    // For simplicity, plan edit fetches data from the table row and enriches with features
    // The full implementation adds a 'bv_get_plan' action to the admin class

    /* ============================================
       WOOCOMMERCE PRODUCT SEARCHABLE SELECT
       ============================================ */

    // Search filtering for WC product dropdowns
    $(document).on('input', '.bv-woo-search', function() {
        var $search = $(this);
        var $select = $search.next('.bv-woo-product-select');
        var term = $search.val().toLowerCase();
        
        $select.find('option').each(function() {
            var $option = $(this);
            var text = $option.text().toLowerCase();
            var value = $option.val();
            
            if (value === '0') {
                // Always show "None" option
                $option.show();
                return;
            }
            
            if (term === '') {
                $option.show();
            } else {
                $option.toggle(text.indexOf(term) !== -1);
            }
        });
        
        // Auto-select first visible option if only one match (excluding "None")
        var visibleOptions = $select.find('option:visible:not([value="0"])');
        if (visibleOptions.length === 1) {
            $select.val(visibleOptions.val());
        }
    });

    // When select changes, clear search
    $(document).on('change', '.bv-woo-product-select', function() {
        var $select = $(this);
        var $search = $select.prev('.bv-woo-search');
        var selectedOption = $select.find('option:selected');
        
        if (selectedOption.val() !== '0') {
            $search.val('');
        }
    });

})(jQuery);