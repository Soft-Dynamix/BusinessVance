(function($) {
    'use strict';
    if (typeof bvDocReqs === 'undefined') return;

    var ajaxUrl = bvDocReqs.ajax_url;
    var nonce   = bvDocReqs.nonce;
    var strings = bvDocReqs.strings;

    // Load table data
    function loadDocReqs() {
        $.post(ajaxUrl, { action: 'bv_get_document_requirements', nonce: nonce }, function(res) {
            if (!res.success) return;
            var rows = res.data;
            var $tbody = $('#bv-doc-reqs-tbody').empty();

            if (!rows || rows.length === 0) {
                $tbody.html('<tr><td colspan="9" style="text-align:center; padding:40px; color:#999;">' + $('<span>').text(strings.no_items).html() + '</td></tr>');
                return;
            }

            rows.forEach(function(r) {
                var typesHtml = r.allowed_types.split(',').map(function(t) {
                    return '<code style="font-size:11px;background:#f0f0f1;padding:1px 5px;border-radius:3px;">' + $('<span>').text(t.trim()).html() + '</code>';
                }).join(' ');

                var reqLabel = r.is_required == 1
                    ? '<span style="color:#00a32a;font-weight:600;">✓ Yes</span>'
                    : '<span style="color:#999;">No</span>';

                var svcCount = parseInt(r.service_count) || 0;
                var svcHtml = svcCount > 0
                    ? '<a href="' + bvDocReqs.services_url + '" title="' + $('<span>').text(strings.view_services).html() + '">' + svcCount + '</a>'
                    : '<span style="color:#999;">0</span>';

                $tbody.append(
                    '<tr data-id="' + r.id + '">' +
                        '<td class="bv-sort-handle-col"><span class="bv-sort-handle" title="Drag to reorder">☰</span></td>' +
                        '<td><strong>' + $('<span>').text(r.name).html() + '</strong></td>' +
                        '<td><code style="font-size:12px;">' + $('<span>').text(r.slug).html() + '</code></td>' +
                        '<td>' + typesHtml + '</td>' +
                        '<td>' + reqLabel + '</td>' +
                        '<td>' + r.max_size_mb + '</td>' +
                        '<td>' + r.display_order + '</td>' +
                        '<td>' + svcHtml + '</td>' +
                        '<td>' +
                            '<button type="button" class="button button-small bv-edit-doc-req" data-id="' + r.id + '">' + $('<span>').text(strings.edit).html() + '</button> ' +
                            '<button type="button" class="button button-small bv-delete-doc-req" data-id="' + r.id + '">' + $('<span>').text(strings.delete).html() + '</button>' +
                        '</td>' +
                    '</tr>'
                );
            });

            // Init sortable
            initSortable();
        });
    }

    // Sortable
    function initSortable() {
        if ($.fn.sortable) {
            $('#bv-doc-reqs-table tbody').sortable({
                handle: '.bv-sort-handle',
                axis: 'y',
                update: function() {
                    var order = [];
                    $(this).find('tr').each(function() { order.push($(this).data('id')); });
                    $.post(ajaxUrl, { action: 'bv_reorder_document_requirements', nonce: nonce, order: order });
                }
            });
        }
    }

    // Open modal
    function openDocReqModal(title) {
        $('#bv-doc-req-modal-title').text(title);
        $('#bv-doc-req-modal').show();
        $('body').css('overflow', 'hidden');
    }

    // Close modal
    function closeDocReqModal() {
        $('#bv-doc-req-modal').hide();
        $('body').css('overflow', '');
    }

    // Add button
    $(document).on('click', '#bv-add-doc-req', function() {
        $('#bv-doc-req-form')[0].reset();
        $('#bv-doc-req-form input[name="id"]').val('');
        $('#bv-doc-req-form input[name="is_required"]').prop('checked', true);
        $('#bv-doc-req-form input[name="max_size_mb"]').val('10');
        $('#bv-doc-req-form input[name="allowed_types"]').val('pdf,doc,docx,jpg,jpeg,png');
        $('#bv-doc-req-form input[name="display_order"]').val('0');
        openDocReqModal(strings.add_title);
    });

    // Edit button
    $(document).on('click', '.bv-edit-doc-req', function() {
        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'bv_get_document_requirement', nonce: nonce, id: id }, function(res) {
            if (!res.success) { alert(res.data.message || strings.error); return; }
            var r = res.data;
            $('#bv-doc-req-form input[name="id"]').val(r.id);
            $('#bv-doc-req-form input[name="name"]').val(r.name);
            $('#bv-doc-req-form input[name="slug"]').val(r.slug);
            $('#bv-doc-req-form textarea[name="description"]').val(r.description);
            $('#bv-doc-req-form input[name="allowed_types"]').val(r.allowed_types);
            $('#bv-doc-req-form input[name="max_size_mb"]').val(r.max_size_mb);
            $('#bv-doc-req-form input[name="is_required"]').prop('checked', r.is_required == 1);
            $('#bv-doc-req-form input[name="display_order"]').val(r.display_order);
            openDocReqModal(strings.edit_title);
        });
    });

    // Delete button
    $(document).on('click', '.bv-delete-doc-req', function() {
        if (!confirm(strings.confirm_delete)) return;
        var id = $(this).data('id');
        $.post(ajaxUrl, { action: 'bv_delete_document_requirement', nonce: nonce, id: id }, function(res) {
            if (res.success) { location.reload(); } else { alert(res.data.message || strings.error); }
        });
    });

    // Save form
    $(document).on('submit', '#bv-doc-req-form', function(e) {
        e.preventDefault();
        var $btn = $(this).find('.bv-gold-btn');
        var origText = $btn.text();
        $btn.text(strings.saving).prop('disabled', true);

        var formData = $(this).serialize() + '&action=bv_save_document_requirement';
        $.post(ajaxUrl, formData, function(res) {
            if (res.success) { alert(strings.saved); location.reload(); }
            else { alert(res.data.message || strings.error); $btn.text(origText).prop('disabled', false); }
        }).fail(function() { alert(strings.error); $btn.text(origText).prop('disabled', false); });
    });

    // Close modal handlers
    $(document).on('click', '#bv-doc-req-modal .bv-modal-overlay, #bv-doc-req-modal .bv-modal-close, #bv-doc-req-modal .bv-cancel-btn', function() {
        closeDocReqModal();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') closeDocReqModal();
    });

    // Auto-generate slug from name
    $(document).on('input', '#dr-name', function() {
        if ($('#dr-slug').data('edited')) return;
        $('#dr-slug').val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
    });
    $(document).on('input', '#dr-slug', function() {
        $(this).data('edited', true);
    });

    // Initial load
    loadDocReqs();
})(jQuery);
