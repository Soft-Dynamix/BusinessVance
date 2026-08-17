/**
 * BusinessVance Consultant Dashboard - Frontend JavaScript
 * @since 2.5.0 Added questionnaire CSV download
 * @since 2.7.21 Added HTML report download with service grouping
 * @since 2.7.22 HTML report opens in new window for PDF save; file downloads via AJAX
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

    /**
     * Download questionnaire responses as CSV for a project.
     */
    window.bv_cd_download_questionnaire = function(projectId) {
        var url = bv_cd.ajax_url
            + '?action=bv_cd_download_questionnaire'
            + '&nonce=' + bv_cd.nonce
            + '&project_id=' + encodeURIComponent(projectId);
        window.location.href = url;
    };

    /**
     * Open questionnaire responses as professional HTML report in a new window.
     * The report includes a "Save as PDF" button that triggers the browser print dialog.
     */
    window.bv_cd_download_questionnaire_html = function(projectId) {
        var url = bv_cd.ajax_url
            + '?action=bv_cd_download_questionnaire_html'
            + '&nonce=' + bv_cd.nonce
            + '&project_id=' + encodeURIComponent(projectId);
        window.open(url, '_blank', 'width=1000,height=800,scrollbars=yes');
    };

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
            $.post(bv_cd.ajax_url, { action: 'bv_cd_update_project_status', nonce: bv_cd.nonce, project_id: pid, status: status }, function(r) {
                if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });

        // Progress update
        $('.bv-cd-progress-input').on('change', function() {
            var pid = $(this).data('project-id');
            var val = $(this).val();
            $.post(bv_cd.ajax_url, { action: 'bv_cd_update_progress', nonce: bv_cd.nonce, project_id: pid, progress: val }, function(r) {
                if (r.success) {
                    $('#bv-cd-project-' + pid + ' .bv-cd-progress-display').text(val + '%');
                    $('#bv-cd-project-' + pid + ' .bv-cd-progress-fill').css('width', val + '%');
                } else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });

        // Internal notes save
        $('#bv-cd-save-notes').on('click', function() {
            var pid = $(this).data('project-id');
            var notes = $('#bv-cd-internal-notes').val();
            $.post(bv_cd.ajax_url, { action: 'bv_cd_update_internal_notes', nonce: bv_cd.nonce, project_id: pid, notes: notes }, function(r) {
                if (r.success) { alert('Notes saved'); } else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });

        // Add note
        $('#bv-cd-add-note').on('click', function() {
            var pid = $(this).data('project-id');
            var content = $('#bv-cd-note-content').val();
            if (!content) return alert('Note cannot be empty');
            $.post(bv_cd.ajax_url, { action: 'bv_cd_add_note', nonce: bv_cd.nonce, project_id: pid, content: content }, function(r) {
                if (r.success) {
                    var user = bv_cd.current_user;
                    var time = bv_cd.current_time;
                    $('#bv-cd-notes-list').prepend('<div class="bv-cd-note"><strong>' + bvEscapeHtml(user) + '</strong><span class="bv-cd-note-time">' + bvEscapeHtml(time) + '</span><p>' + $('<div>').text(content).html() + '</p></div>');
                    $('#bv-cd-note-content').val('');
                } else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });

        // Send message
        $('#bv-cd-send-msg').on('click', function() {
            var pid = $(this).data('project-id');
            var msg = $('#bv-cd-msg-text').val();
            if (!msg) return alert('Message cannot be empty');
            $.post(bv_cd.ajax_url, { action: 'bv_cd_send_message', nonce: bv_cd.nonce, project_id: pid, message: msg }, function(r) {
                if (r.success) {
                    var user = bv_cd.current_user;
                    var time = bv_cd.current_time;
                    $('#bv-cd-msg-thread').append('<div class="bv-cd-msg bv-cd-msg-admin"><strong>' + bvEscapeHtml(user) + '</strong><span>' + bvEscapeHtml(time) + '</span><p>' + $('<div>').text(msg).html() + '</p></div>');
                    $('#bv-cd-msg-text').val('');
                    $('#bv-cd-msg-thread').scrollTop($('#bv-cd-msg-thread')[0].scrollHeight);
                }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });

        // Report upload
        $('#bv-cd-upload-report').on('click', function() {
            var pid = $(this).data('project-id');
            var fileInput = $('#bv-cd-report-file')[0];
            var title = $('#bv-cd-report-title').val();
            if (!fileInput.files.length || !title) return alert('Please enter title and select file');
            var fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('action', 'bv_cd_upload_report');
            fd.append('nonce', bv_cd.nonce);
            fd.append('project_id', pid);
            fd.append('title', title);
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', data: fd, processData: false, contentType: false, success: function(r) {
                if (r.success) { alert('Report uploaded'); location.reload(); } else { alert(r.data || 'Error uploading'); }
            }, error: function() { alert('Request failed. Please try again.'); }});
        });

        // Deliver report
        $('.bv-cd-deliver-report').on('click', function() {
            var rid = $(this).data('report-id');
            if (!confirm('Mark this report as delivered? The client will be able to download it.')) return;
            $.post(bv_cd.ajax_url, { action: 'bv_cd_deliver_report', nonce: bv_cd.nonce, report_id: rid }, function(r) {
                if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
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
            $.post(bv_cd.ajax_url, data, function(r) {
                if (r.success) { location.href = '?page=bv-consultant-dashboard&project_id=' + encodeURIComponent(r.data.project_id); }
                else { alert(r.data || 'Error'); }
            }).fail(function() { alert('Request failed. Please try again.'); });
        });
    });

})(jQuery);
