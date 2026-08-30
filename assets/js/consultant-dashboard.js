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

        // === TWO-STEP REPORT UPLOAD — CHUNKED BASE64 JSON ===
        // WAF blocks multipart/form-data. Server limits request body size (413).
        // Solution: read file as base64, split into small chunks (~250KB each),
        // send each as a separate JSON POST. Three phases: start → chunk... → finish.
        // Step 1: Select file → preview it (confirm it's the right file)
        // Step 2: Click "Complete Upload & Notify Client" → chunked upload with progress

        var bvSelectedFile = null;
        var BV_CHUNK_SIZE = 250000; // base64 chars per chunk (~188KB decoded, well under server limits)

        // Format bytes to human readable
        function bvFormatBytes(bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        // File icon based on extension
        function bvFileIcon(name) {
            var ext = (name || '').split('.').pop().toLowerCase();
            if (ext === 'pdf') return '📕';
            if (ext === 'doc' || ext === 'docx') return '📘';
            return '📄';
        }

        // Send a JSON POST and return a promise
        function bvJsonPost(url, nonce, data) {
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('X-WP-Nonce', nonce);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.addEventListener('load', function() {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (xhr.status >= 200 && xhr.status < 300 && resp.success) {
                            resolve(resp);
                        } else {
                            reject({ status: xhr.status, data: (resp && resp.data) ? resp.data : xhr.responseText.substring(0, 200) });
                        }
                    } catch(e) {
                        reject({ status: xhr.status, data: 'Unexpected response (HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 150) });
                    }
                });
                xhr.addEventListener('error', function() { reject({ status: 0, data: 'Network error' }); });
                xhr.send(JSON.stringify(data));
            });
        }

        // Step 1a: File selection — show preview
        $(document).on('change', '#bv-cd-report-file', function() {
            var fileInput = this;
            var file = fileInput.files && fileInput.files[0];
            if (!file) return;

            // Validate file type
            var ext = file.name.split('.').pop().toLowerCase();
            var allowed = ['pdf', 'doc', 'docx'];
            if (allowed.indexOf(ext) === -1) {
                alert('Only PDF, DOC, and DOCX files are allowed.');
                fileInput.value = '';
                return;
            }

            // Validate file size
            var maxSize = parseInt(bv_cd.max_upload_size) || 10485760;
            if (file.size > maxSize) {
                alert('File is too large (' + bvFormatBytes(file.size) + '). Maximum allowed: ' + bv_cd.max_upload_mb + ' MB.');
                fileInput.value = '';
                return;
            }

            bvSelectedFile = file;

            // Show preview card
            document.getElementById('bv-cd-preview-icon').textContent = bvFileIcon(file.name);
            document.getElementById('bv-cd-preview-name').textContent = file.name;
            document.getElementById('bv-cd-preview-details').textContent = bvFormatBytes(file.size) + ' — ' + (file.type || ext.toUpperCase());
            document.getElementById('bv-cd-file-preview').style.display = 'block';

            // Enable upload button
            document.getElementById('bv-cd-upload-report').disabled = false;
        });

        // Step 1b: Clear selected file
        $(document).on('click', '#bv-cd-clear-file', function(e) {
            e.preventDefault();
            bvSelectedFile = null;
            document.getElementById('bv-cd-report-file').value = '';
            document.getElementById('bv-cd-file-preview').style.display = 'none';
            document.getElementById('bv-cd-upload-report').disabled = true;
            document.getElementById('bv-cd-upload-status').textContent = '';
        });

        // Step 2: Chunked upload with progress
        $(document).on('click', '#bv-cd-upload-report', function(e) {
            e.preventDefault();

            var titleEl = document.getElementById('bv-cd-report-title');
            var statusEl = document.getElementById('bv-cd-upload-status');
            var step1 = document.getElementById('bv-cd-upload-step1');
            var step2 = document.getElementById('bv-cd-upload-step2');
            var progressBar = document.getElementById('bv-cd-progress-bar');
            var progressText = document.getElementById('bv-cd-progress-text');

            var title = titleEl ? titleEl.value.trim() : '';
            if (!title) {
                alert('Please enter a report title first.');
                if (titleEl) titleEl.focus();
                return;
            }
            if (!bvSelectedFile) {
                alert('Please select a file first.');
                return;
            }

            var projectId = this.getAttribute('data-project-id');
            var fileName = bvSelectedFile.name;
            var fileSize = bvSelectedFile.size;
            var fileMime = bvSelectedFile.type || 'application/octet-stream';

            // Switch to step 2 UI
            step1.style.display = 'none';
            step2.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.textContent = 'Reading file...';
            progressText.style.color = '#64748b';

            // Read file as base64
            var reader = new FileReader();
            reader.onprogress = function(evt) {
                if (evt.lengthComputable) {
                    var pct = Math.round((evt.loaded / evt.total) * 10); // reading is 0-10%
                    progressBar.style.width = pct + '%';
                    progressText.textContent = 'Reading file... ' + pct + '%';
                }
            };
            reader.onload = function() {
                var base64Full = reader.result.split(',')[1]; // strip data:mime;base64, prefix
                var totalChunks = Math.ceil(base64Full.length / BV_CHUNK_SIZE);
                progressBar.style.width = '10%';
                progressText.textContent = 'Sending chunk 1 of ' + totalChunks + '...';

                // Phase 1: START — send first chunk with metadata
                var firstChunk = base64Full.substring(0, BV_CHUNK_SIZE);
                bvJsonPost(bv_cd.rest_url, bv_cd.rest_nonce, {
                    upload_action: 'start',
                    chunk_base64: firstChunk,
                    file_name: fileName,
                    file_size: fileSize,
                    file_type: fileMime,
                    title: title,
                    project_id: projectId,
                    total_chunks: totalChunks
                }).then(function(startResp) {
                    var uploadId = startResp.upload_id;
                    var chunksSent = 1;

                    // Phase 2: Send remaining chunks sequentially
                    function sendNextChunk() {
                        if (chunksSent >= totalChunks) {
                            // Phase 3: FINISH
                            progressText.textContent = 'Finalizing upload...';
                            bvJsonPost(bv_cd.rest_url, bv_cd.rest_nonce, {
                                upload_action: 'finish',
                                upload_id: uploadId
                            }).then(function(finalResp) {
                                progressBar.style.width = '100%';
                                progressText.textContent = finalResp.data || 'Upload complete! Reloading...';
                                progressText.style.color = '#22c55e';
                                setTimeout(function() { location.reload(); }, 1200);
                            }).catch(function(err) {
                                step2.style.display = 'none';
                                step1.style.display = 'block';
                                statusEl.textContent = 'Error finalizing: ' + (err.data || 'Unknown error');
                                statusEl.style.color = '#dc2626';
                            });
                            return;
                        }

                        var chunk = base64Full.substring(chunksSent * BV_CHUNK_SIZE, (chunksSent + 1) * BV_CHUNK_SIZE);
                        var pct = 10 + Math.round((chunksSent / totalChunks) * 85); // 10% to 95%
                        progressBar.style.width = pct + '%';
                        progressText.textContent = 'Sending chunk ' + (chunksSent + 1) + ' of ' + totalChunks + '...';

                        bvJsonPost(bv_cd.rest_url, bv_cd.rest_nonce, {
                            upload_action: 'chunk',
                            upload_id: uploadId,
                            chunk_base64: chunk
                        }).then(function() {
                            chunksSent++;
                            sendNextChunk();
                        }).catch(function(err) {
                            step2.style.display = 'none';
                            step1.style.display = 'block';
                            statusEl.textContent = 'Error on chunk ' + (chunksSent + 1) + ': ' + (err.data || 'Unknown error');
                            statusEl.style.color = '#dc2626';
                        });
                    }

                    sendNextChunk();

                }).catch(function(err) {
                    step2.style.display = 'none';
                    step1.style.display = 'block';
                    statusEl.textContent = 'Error: ' + (err.data || 'Could not start upload.');
                    statusEl.style.color = '#dc2626';
                });
            };
            reader.onerror = function() {
                step2.style.display = 'none';
                step1.style.display = 'block';
                statusEl.textContent = 'Error: Could not read the file. Please try again.';
                statusEl.style.color = '#dc2626';
            };
            reader.readAsDataURL(bvSelectedFile);
        });

        // Deliver report
        $(document).on('click', '.bv-cd-deliver-report', function() {
            var rid = $(this).data('report-id');
            if (!confirm('Mark this report as delivered? The client will be able to download it.')) return;
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_deliver_report', nonce: bv_cd.nonce, report_id: rid }, success: function(r) {
                if (r.success) { location.reload(); } else { alert(r.data || 'Error'); }
            }, error: function() { alert('Request failed. Please try again.'); } });
        });

        // Deliver report AND notify client via email
        $(document).on('click', '.bv-cd-deliver-notify-report', function() {
            var rid = $(this).data('report-id');
            if (!confirm('Deliver this report AND send an email notification to the client?')) return;
            var $btn = $(this).prop('disabled', true).text('Sending...');
            $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_deliver_notify_report', nonce: bv_cd.nonce, report_id: rid }, success: function(r) {
                if (r.success) { location.reload(); } else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('✓ Deliver & Notify'); }
            }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('✓ Deliver & Notify'); } });
        });

        // Delete report
        $(document).on('click', '.bv-cd-delete-report', function() {
            var $btn = $(this);
            var rid = $btn.data('report-id');
            bvCdConfirm({
                icon: '🗑',
                title: 'Delete This Report?',
                danger: true,
                okText: 'Delete Permanently',
                body: '<p style="margin:8px 0;color:#666;">This will <strong>permanently delete</strong> the report file and its database record. This cannot be undone.</p>',
                onConfirm: function() {
                    $btn.prop('disabled', true).text('Deleting...');
                    $.ajax({ url: bv_cd.ajax_url, type: 'POST', dataType: 'json', data: { action: 'bv_cd_delete_report', nonce: bv_cd.nonce, report_id: rid }, success: function(r) {
                        if (r.success) { location.reload(); } else { alert(r.data || 'Error'); $btn.prop('disabled', false).text('🗑 Delete'); }
                    }, error: function() { alert('Request failed.'); $btn.prop('disabled', false).text('🗑 Delete'); } });
                }
            });
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
