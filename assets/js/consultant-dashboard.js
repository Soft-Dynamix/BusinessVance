/**
 * BusinessVance Consultant Dashboard - Frontend JavaScript
 * @since 2.5.0 Added questionnaire CSV download
 * @since 2.7.21 Added HTML report download with service grouping
 * @since 2.7.22 HTML report opens in new window for PDF save; file downloads via AJAX
 * @since 2.7.43 Added confirmation modals, send reminder, detail quick note
 */
(function($) {
    'use strict';

    if (typeof bv_cd === 'undefined') return;

    function bvEscapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    window.bv_cd_download_questionnaire = function(projectId) {
        var url = bv_cd.ajax_url
            + '?action=bv_cd_download_questionnaire'
            + '&nonce=' + bv_cd.nonce
            + '&project_id=' + encodeURIComponent(projectId);
        window.location.href = url;
    };

    window.bv_cd_download_questionnaire_html = function(projectId) {
        var url = bv_cd.ajax_url
            + '?action=bv_cd_download_questionnaire_html'
            + '&nonce=' + bv_cd.nonce
            + '&project_id=' + encodeURIComponent(projectId);
        window.open(url, '_blank', 'width=1000,height=800,scrollbars=yes');
    };

    // Multi-status filter: auto-submit on checkbox toggle
    $(document).on('change', '#bv-cd-status-filters input[type="checkbox"]', function() {
        $(this).closest('form').submit();
    });

    // Feature 4: Quick status change from table row
    $(document).on('change', '.bv-cd-quick-status', function() {
        var $sel = $(this);
        var pid = $sel.data('project-id');
        var status = $sel.val();
        $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_update_project_status', nonce: bv_cd.nonce, project_id: pid, status: status }, success: function(r) {
            if (r.success) { location.href = location.pathname + location.search + '&_t=' + Date.now(); } else { alert(r.data || 'Error'); $sel.val($sel.data('prev')); }
        }, error: function() { alert('Request failed.'); $sel.val($sel.data('prev')); } });
    });
    $(document).on('focus', '.bv-cd-quick-status', function() { $(this).data('prev', $(this).val()); });

    // Feature 9: Bulk select all
    $(document).on('change', '#bv-cd-select-all', function() {
        $('.bv-cd-bulk-cb').prop('checked', this.checked).trigger('change');
    });
    $(document).on('change', '.bv-cd-bulk-cb', function() {
        var total = $('.bv-cd-bulk-cb').length;
        var checked = $('.bv-cd-bulk-cb:checked').length;
        $('#bv-cd-select-all').prop('checked', total === checked);
        if (checked > 0) {
            $('#bv-cd-bulk-bar').show().find('.bv-cd-bulk-count').text(checked + ' selected');
        } else {
            $('#bv-cd-bulk-bar').hide();
        }
    });

    // Feature 9: Bulk apply
    $(document).on('click', '#bv-cd-bulk-apply', function() {
        var pids = $('.bv-cd-bulk-cb:checked').map(function() { return $(this).val(); }).get();
        var status = $('#bv-cd-bulk-status').val();
        if (!pids.length) return;
        if (!confirm('Change ' + pids.length + ' project(s) to "' + status + '"?')) return;
        var $btn = $(this).prop('disabled', true).text('Applying...');
        $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_bulk_update_status', nonce: bv_cd.nonce, project_ids: pids, status: status }, success: function(r) {
            if (r.success) { location.href = location.pathname + location.search + '&_t=' + Date.now(); } else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('Apply'); }
        }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('Apply'); } });
    });

    // Feature 10: Quick note modal
    var quickNotePid = 0;
    $(document).on('click', '.bv-cd-quick-note-btn', function() {
        quickNotePid = $(this).data('project-id');
        $('#bv-cd-note-modal-pnum').text($(this).data('project-number'));
        $('#bv-cd-quick-note-text').val('');
        $('#bv-cd-note-modal').show();
        $('#bv-cd-quick-note-text').focus();
    });
    $(document).on('click', '#bv-cd-note-modal .bv-cd-modal-backdrop', function() { $(this).closest('.bv-cd-modal').hide(); });
    $(document).on('click', '#bv-cd-save-quick-note', function() {
        var content = $('#bv-cd-quick-note-text').val();
        if (!content) return alert('Note cannot be empty');
        var $btn = $(this).prop('disabled', true).text('Saving...');
        $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_quick_note', nonce: bv_cd.nonce, project_id: quickNotePid, content: content }, success: function(r) {
            if (r.success) { $('#bv-cd-note-modal').hide(); } else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('Save Note'); }
        }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('Save Note'); } });
    });

    // v2.7.43 Feature 11: Confirmation modal system (replaces browser confirm for Reset/Remove)
    function bvCdConfirm(options) {
        var $modal = $('#bv-cd-confirm-modal');
        if (!$modal.length) { if (options.onConfirm) options.onConfirm(); return; }
        $('#bv-cd-confirm-icon').text(options.icon || '\u26A0\uFE0F');
        $('#bv-cd-confirm-title').text(options.title || 'Confirm Action');
        $('#bv-cd-confirm-body').html(options.body || '');
        var $ok = $('#bv-cd-confirm-ok').text(options.okText || 'Confirm').css({background:'',color:'',borderColor:''});
        if (options.danger) { $ok.css({background:'#dc2626',color:'#fff',borderColor:'#dc2626'}); }
        $modal.data('callback', options.onConfirm).show();
    }
    $(document).on('click', '#bv-cd-confirm-modal .bv-cd-confirm-cancel', function() {
        $('#bv-cd-confirm-modal').hide().removeData('callback');
    });
    $(document).on('click', '#bv-cd-confirm-ok', function() {
        var cb = $('#bv-cd-confirm-modal').data('callback');
        $('#bv-cd-confirm-modal').hide().removeData('callback');
        if (typeof cb === 'function') cb();
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') $('#bv-cd-confirm-modal').hide().removeData('callback');
    });

    // v2.7.43 Feature 14: Send reminder email
    $(document).on('click', '#bv-cd-send-reminder, #bv-cd-send-reminder-docs', function() {
        var $btn = $(this);
        var pid = $btn.data('project-id');
        $btn.prop('disabled', true).text('Sending\u2026');
        $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_send_reminder', nonce: bv_cd.nonce, project_id: pid }, success: function(r) {
            if (r.success) { $btn.text('Sent \u2713'); setTimeout(function() { $btn.prop('disabled', false).text('\uD83D\uDCE7 Send Reminder'); }, 3000); }
            else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('\uD83D\uDCE7 Send Reminder'); }
        }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('\uD83D\uDCE7 Send Reminder'); } });
    });

    // v2.7.43 Feature 6: Quick note from detail overview
    $(document).on('click', '#bv-cd-save-detail-quick-note', function() {
        var pid = $(this).data('project-id');
        var content = $('#bv-cd-detail-quick-note').val();
        if (!content) return alert('Note cannot be empty');
        var $btn = $(this).prop('disabled', true).text('Saving...');
        $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_add_note', nonce: bv_cd.nonce, project_id: pid, content: content }, success: function(r) {
            if (r.success) { $('#bv-cd-detail-quick-note').val(''); $btn.text('Saved \u2713'); setTimeout(function() { $btn.prop('disabled', false).text('Add'); }, 2000); }
            else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('Add'); }
        }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('Add'); } });
    });

    $(function() {
        // Tab switching in project detail
        $('.bv-cd-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            $('.bv-cd-tab').removeClass('active');
            $(this).addClass('active');
            $('.bv-cd-panel').hide();
            $('#bv-cd-panel-' + tab).show();
        });

        // Status update
        $('.bv-cd-status-update').on('change', function() {
            var pid = $(this).data('project-id');
            var status = $(this).val();
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_update_project_status', nonce: bv_cd.nonce, project_id: pid, status: status }, success: function(r) {
                if (r.success) { location.href = location.pathname + location.search + '&_t=' + Date.now(); } else { alert(r.data || 'Error updating status'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Progress update
        $('.bv-cd-progress-input').on('change', function() {
            var pid = $(this).data('project-id');
            var val = $(this).val();
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_update_progress', nonce: bv_cd.nonce, project_id: pid, progress: val }, success: function(r) {
                if (r.success) {
                    $('#bv-cd-project-' + pid + ' .bv-cd-progress-display').text(val + '%');
                    $('#bv-cd-project-' + pid + ' .bv-cd-progress-fill').css('width', val + '%');
                } else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Internal notes save
        $('#bv-cd-save-notes').on('click', function() {
            var pid = $(this).data('project-id');
            var notes = $('#bv-cd-internal-notes').val();
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_update_internal_notes', nonce: bv_cd.nonce, project_id: pid, notes: notes }, success: function(r) {
                if (r.success) { alert('Notes saved'); } else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Add note
        $('#bv-cd-add-note').on('click', function() {
            var pid = $(this).data('project-id');
            var content = $('#bv-cd-note-content').val();
            if (!content) return alert('Note cannot be empty');
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_add_note', nonce: bv_cd.nonce, project_id: pid, content: content }, success: function(r) {
                if (r.success) {
                    var user = bv_cd.current_user;
                    var time = bv_cd.current_time;
                    $('#bv-cd-notes-list').prepend('<div class="bv-cd-note"><strong>' + bvEscapeHtml(user) + '</strong><span class="bv-cd-note-time">' + bvEscapeHtml(time) + '</span><p>' + $('<div>').text(content).html() + '</p></div>');
                    $('#bv-cd-note-content').val('');
                } else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Send message
        $('#bv-cd-send-msg').on('click', function() {
            var pid = $(this).data('project-id');
            var msg = $('#bv-cd-msg-text').val();
            if (!msg) return alert('Message cannot be empty');
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_send_message', nonce: bv_cd.nonce, project_id: pid, message: msg }, success: function(r) {
                if (r.success) {
                    var user = bv_cd.current_user;
                    var time = bv_cd.current_time;
                    $('#bv-cd-msg-thread').append('<div class="bv-cd-msg bv-cd-msg-admin"><strong>' + bvEscapeHtml(user) + '</strong><span>' + bvEscapeHtml(time) + '</span><p>' + $('<div>').text(msg).html() + '</p></div>');
                    $('#bv-cd-msg-text').val('');
                    $('#bv-cd-msg-thread').scrollTop($('#bv-cd-msg-thread')[0].scrollHeight);
                }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Report upload
        $('#bv-cd-upload-report').on('click', function() {
            var pid = $(this).data('project-id');
            var fileInput = $('#bv-cd-report-file')[0];
            var title = $('#bv-cd-report-title').val();
            if (!fileInput.files.length || !title) return alert('Please enter title and select file');

            // Client-side file size check
            var maxBytes = parseInt(bv_cd.max_upload_size) || 0;
            var maxMb = parseFloat(bv_cd.max_upload_mb) || 0;
            if (maxBytes > 0 && fileInput.files[0].size > maxBytes) {
                return alert('File is too large (' + (fileInput.files[0].size / 1048576).toFixed(1) + ' MB). Maximum upload size is ' + maxMb + ' MB. Contact your hosting provider to increase the upload limit if needed.');
            }

            var fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('action', 'bv_cd_upload_report');
            fd.append('nonce', bv_cd.nonce);
            fd.append('project_id', pid);
            fd.append('title', title);
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json', success: function(r) {
                if (r.success) { alert('Report uploaded'); location.reload(); } else { alert(r.data || 'Error uploading'); }
            }, error: function(xhr, status, err) {
                var msg = 'Request failed.';
                if (xhr.status === 413) {
                    msg = 'File too large. Your server limits uploads to ' + maxMb + ' MB. Contact your hosting provider to increase upload_max_filesize and post_max_size in PHP.';
                } else if (xhr.status === 500) {
                    msg = 'Server error (500). This usually means the file exceeds PHP\'s upload size limit (' + maxMb + ' MB). Contact your hosting provider to increase upload_max_filesize and post_max_size.';
                } else if (xhr.status > 0) {
                    msg = 'Request failed (HTTP ' + xhr.status + '). ' + (err || status) + '.';
                } else if (status === 'parsererror') {
                    msg = 'Server returned an invalid response. This may be caused by a PHP error or the file exceeding the server\'s upload limit (' + maxMb + ' MB). Check the browser console (F12) for details.';
                }
                alert(msg);
            } });
        });

        // Deliver report
        $('.bv-cd-deliver-report').on('click', function() {
            var rid = $(this).data('report-id');
            if (!confirm('Mark this report as delivered? The client will be able to download it.')) return;
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_deliver_report', nonce: bv_cd.nonce, report_id: rid }, success: function(r) {
                if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Reset project (v2.7.43: styled modal #11)
        $(document).on('click', '#bv-cd-reset-project', function() {
            var $btn = $(this);
            var pid = $btn.data('project-id');
            var pnum = $btn.data('project-number') || pid;
            bvCdConfirm({
                icon: '\u21BB',
                title: 'Reset Project ' + pnum + '?',
                danger: true,
                okText: 'Reset Project',
                body: '<p style="margin:8px 0;">This will <strong>permanently delete</strong>:</p>' +
                      '<ul style="margin:0 0 0 20px;color:#666;"><li>All questionnaire responses &amp; uploaded files</li><li>Signed agreement(s)</li><li>Required documents</li><li>Delivered reports</li></ul>' +
                      '<p style="color:#dc2626;margin-top:8px;">The client will need to redo everything. This cannot be undone.</p>',
                onConfirm: function() {
                    $btn.prop('disabled', true).text('Resetting\u2026');
                    $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_reset_project', nonce: bv_cd.nonce, project_id: pid }, success: function(r) {
                        if (r.success) { location.reload(); } else { alert(r.data || 'Error resetting project'); $btn.prop('disabled', false).text('\u21bb Reset Project'); }
                    }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('\u21bb Reset Project'); } });
                }
            });
        });

        // Remove project (v2.7.43: styled modal #11)
        $(document).on('click', '#bv-cd-remove-project', function() {
            var $btn = $(this);
            var pid = $btn.data('project-id');
            var pnum = $btn.data('project-number') || pid;
            bvCdConfirm({
                icon: '\uD83D\uDDD1',
                title: 'DELETE Project ' + pnum + '?',
                danger: true,
                okText: 'Permanently Delete',
                body: '<p style="margin:8px 0;">This will <strong>PERMANENTLY</strong> remove:</p>' +
                      '<ul style="margin:0 0 0 20px;color:#666;"><li>The project record</li><li>All questionnaire responses &amp; uploaded files</li><li>Signed agreement(s)</li><li>Required documents</li><li>Delivered reports</li><li>All messages &amp; notes</li><li>Activity log</li></ul>' +
                      '<p style="color:#dc2626;margin-top:8px;font-weight:600;">This CANNOT be undone.</p>',
                onConfirm: function() {
                    $btn.prop('disabled', true).text('Removing\u2026');
                    $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_remove_project', nonce: bv_cd.nonce, project_id: pid }, success: function(r) {
                        if (r.success) { location.href = '?page=bv-consultant-dashboard'; } else { alert(r.data || 'Error removing project'); $btn.prop('disabled', false).text('\uD83D\uDDD1 Remove Project'); }
                    }, error: function(xhr, status, err) { alert('Request failed: ' + (err || status)); $btn.prop('disabled', false).text('\uD83D\uDDD1 Remove Project'); } });
                }
            });
        });

        // Create project
        $('#bv-cd-create-project').on('click', function() {
            var data = { action: 'bv_cd_create_project', nonce: bv_cd.nonce };
            data.client_name = $('#bv-cd-new-name').val();
            data.client_email = $('#bv-cd-new-email').val();
            data.client_phone = $('#bv-cd-new-phone').val();
            data.client_company = $('#bv-cd-new-company').val();
            data.notes = $('#bv-cd-new-notes').val();
            if (!data.client_name || !data.client_email) return alert('Name and email required');
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: data, success: function(r) {
                if (r.success) { location.href = '?page=bv-consultant-dashboard&project_id=' + encodeURIComponent(r.data.project_id); }
                else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });
    });

})(jQuery);
