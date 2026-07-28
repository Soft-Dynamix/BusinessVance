/**
 * BusinessVance Client Portal - Frontend JavaScript
 */
(function($) {
    'use strict';

    window.bv_sign_agreement = function(projectId) {
        var name = $('#bv-sign-name').val();
        if (!name) { alert('Please enter your full legal name.'); return; }
        $('#bv-agreement-status').html('<em>Signing...</em>');
        $.post(bv_portal.ajax_url, {
            action: 'bv_portal_sign_agreement',
            nonce: bv_portal.nonce,
            project_id: projectId,
            full_name: name
        }, function(r) {
            if (r.success) {
                $('#bv-agreement-status').html('<span style="color:#27AE60;">✓ Agreement signed successfully! The page will reload...</span>');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $('#bv-agreement-status').html('<span style="color:#DC3545;">' + (r.data || 'Error signing agreement') + '</span>');
            }
        });
    };

    window.bv_upload_document = function(projectId) {
        var fileInput = $('#bv-doc-file')[0];
        var category = $('#bv-doc-category').val();
        if (!fileInput.files.length) { alert('Please select a file.'); return; }
        $('#bv-doc-status').html('<em>Uploading...</em>');
        var fd = new FormData();
        fd.append('file', fileInput.files[0]);
        fd.append('action', 'bv_portal_upload_document');
        fd.append('nonce', bv_portal.nonce);
        fd.append('project_id', projectId);
        fd.append('category', category);
        fd.append('name', fileInput.files[0].name);
        $.ajax({
            url: bv_portal.ajax_url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r) {
                if (r.success) {
                    $('#bv-doc-status').html('<span style="color:#27AE60;">✓ ' + r.data + '</span>');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $('#bv-doc-status').html('<span style="color:#DC3545;">' + (r.data || 'Error uploading') + '</span>');
                }
            },
            error: function() {
                $('#bv-doc-status').html('<span style="color:#DC3545;">Upload failed. Please try again.</span>');
            }
        });
    };

    window.bv_download_report = function(reportId) {
        window.location.href = bv_portal.ajax_url + '?action=bv_portal_download_report&nonce=' + bv_portal.nonce + '&report_id=' + reportId;
    };

    window.bv_send_message = function(projectId) {
        var text = $('#bv-message-text').val();
        if (!text) { alert('Please type a message.'); return; }
        $('#bv-msg-status').html('<em>Sending...</em>');
        $.post(bv_portal.ajax_url, {
            action: 'bv_portal_send_message',
            nonce: bv_portal.nonce,
            project_id: projectId,
            message: text
        }, function(r) {
            if (r.success) {
                var html = '<div class="bv-message bv-message-client">' +
                    '<div class="bv-message-header"><strong>' + r.data.sender_name + '</strong>' +
                    '<span class="bv-message-time">' + r.data.created_at + '</span></div>' +
                    '<div class="bv-message-body">' + r.data.message + '</div></div>';
                $('#bv-messages-thread').append(html);
                $('#bv-messages-thread').scrollTop($('#bv-messages-thread')[0].scrollHeight);
                $('#bv-message-text').val('');
                $('#bv-msg-status').html('');
            } else {
                $('#bv-msg-status').html('<span style="color:#DC3545;">Error sending message</span>');
            }
        });
    };

    // Questionnaire form submission
    $(document).on('submit', '#bv-questionnaire-form', function(e) {
        e.preventDefault();
        var projectId = $(this).data('project-id');
        var responses = {};
        $(this).find('input, select, textarea').each(function() {
            var name = $(this).attr('name');
            if (name && name.indexOf('q_') === 0) {
                var qid = name.replace('q_', '');
                if ($(this).attr('type') === 'checkbox') {
                    if (!responses[qid]) responses[qid] = [];
                    if ($(this).is(':checked')) responses[qid].push($(this).val());
                } else {
                    responses[qid] = $(this).val();
                }
            }
        });
        $('#bv-q-status').html('<em>Saving...</em>');
        $.post(bv_portal.ajax_url, {
            action: 'bv_portal_submit_questionnaire',
            nonce: bv_portal.nonce,
            project_id: projectId,
            responses: responses
        }, function(r) {
            if (r.success) {
                $('#bv-q-status').html('<span style="color:#27AE60;">✓ ' + r.data + '</span>');
            } else {
                $('#bv-q-status').html('<span style="color:#DC3545;">' + (r.data || 'Error saving') + '</span>');
            }
        });
    });

})(jQuery);
