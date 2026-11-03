/**
 * BusinessVance Client Portal - Frontend JavaScript
 * @since 2.0.0  Updated 2.5.0 for document requirements, questionnaire fixes
 * @since 2.7.20 Fixed repeatable/checkbox/address serialization, added multifile/signature/static fields
 * @since 2.7.20 Moved inline handlers here for reliability, added rating hover, fixed all field types
 * @since 2.7.22 Rewrote signature pad: one-time init, mobile fix, clear & re-sign from saved image
 * @since 2.7.55 Fixed single file upload, added Save Draft & Continue Later, file change feedback
 * @since 2.7.71 Fixed multifile upload: files now upload immediately on select/drop instead of on form submit
 */
(function($) {
    'use strict';

    if (typeof bv_portal === 'undefined') return;

    function bvEscapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ============================================
    // Agreement
    // ============================================
    window.bv_sign_agreement = function(projectId) {
        var name = $('#bv-sign-name').val();
        if (!name) { alert(bv_portal.i18n && bv_portal.i18n.enter_name ? bv_portal.i18n.enter_name : 'Please enter your full legal name.'); return; }
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
                $('#bv-agreement-status').html('<span style="color:#DC3545;">' + bvEscapeHtml(r.data || 'Error signing agreement') + '</span>');
            }
        }).fail(function(xhr, status, error) {
            $('#bv-agreement-status').html('<span style="color:#DC3545;">Network error: ' + bvEscapeHtml(error) + '</span>');
        });
    };

    // ============================================
    // Document Uploads
    // ============================================
    /**
     * Upload a document for a specific document requirement.
     * @since 2.5.0
     */
    window.bv_upload_document_for_requirement = function(projectId, requirementId) {
        var fileInput = $('#bv-doc-file-' + requirementId)[0];
        if (!fileInput || !fileInput.files.length) { alert('Please select a file.'); return; }
        var statusEl = $('#bv-doc-status-' + requirementId);
        if (statusEl) statusEl.html('<em>Uploading...</em>');

        var fd = new FormData();
        fd.append('file', fileInput.files[0]);
        fd.append('action', 'bv_portal_upload_document');
        fd.append('nonce', bv_portal.nonce);
        fd.append('project_id', projectId);
        fd.append('document_requirement_id', requirementId);
        fd.append('name', fileInput.files[0].name);
        fd.append('category', 'requirement');

        $.ajax({
            url: bv_portal.ajax_url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(r) {
                if (r.success) {
                    if (statusEl) statusEl.html('<span style="color:#27AE60;">✓ ' + bvEscapeHtml(r.data || 'Uploaded') + '</span>');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (statusEl) statusEl.html('<span style="color:#DC3545;">' + bvEscapeHtml(r.data || 'Error uploading') + '</span>');
                }
            },
            error: function() {
                if (statusEl) statusEl.html('<span style="color:#DC3545;">Upload failed. Please try again.</span>');
            }
        });
    };

    /**
     * Legacy upload function (backward compatibility for old category-based uploads).
     */
    window.bv_upload_document = function(projectId) {
        var fileInput = $('#bv-doc-file')[0];
        var category = $('#bv-doc-category').val();
        if (!fileInput || !fileInput.files.length) { alert('Please select a file.'); return; }
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
                    $('#bv-doc-status').html('<span style="color:#27AE60;">✓ ' + bvEscapeHtml(r.data) + '</span>');
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    $('#bv-doc-status').html('<span style="color:#DC3545;">' + bvEscapeHtml(r.data || 'Error uploading') + '</span>');
                }
            },
            error: function() {
                $('#bv-doc-status').html('<span style="color:#DC3545;">Upload failed. Please try again.</span>');
            }
        });
    };

    // ============================================
    // Reports & Messages
    // ============================================
    window.bv_download_report = function(reportId) {
        var $btn = $('[onclick="bv_download_report(' + reportId + ')"]');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="bv-spinner-inline" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:bv-spin 0.6s linear infinite;vertical-align:middle;margin-right:6px;"></span>Preparing download...');
        // Use a hidden iframe to trigger the download so we can show feedback
        var iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = bv_portal.ajax_url + '?action=bv_portal_download_report&nonce=' + bv_portal.nonce + '&report_id=' + encodeURIComponent(reportId);
        document.body.appendChild(iframe);
        // Re-enable button after a delay (file should have started downloading by then)
        setTimeout(function() {
            $btn.prop('disabled', false).html(originalText);
            document.body.removeChild(iframe);
        }, 5000);
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
                    '<div class="bv-message-header"><strong>' + bvEscapeHtml(r.data.sender_name) + '</strong>' +
                    '<span class="bv-message-time">' + bvEscapeHtml(r.data.created_at) + '</span></div>' +
                    '<div class="bv-message-body">' + bvEscapeHtml(r.data.message) + '</div></div>';
                $('#bv-messages-thread').append(html);
                $('#bv-messages-thread').scrollTop($('#bv-messages-thread')[0].scrollHeight);
                $('#bv-message-text').val('');
                $('#bv-msg-status').html('');
            } else {
                $('#bv-msg-status').html('<span style="color:#DC3545;">Error sending message</span>');
            }
        }).fail(function(xhr, status, error) {
            $('#bv-msg-status').html('<span style="color:#DC3545;">Network error: ' + bvEscapeHtml(error) + '</span>');
        });
    };

    // ============================================
    // Star Rating — Click + Hover
    // ============================================
    $(document).on('click', '.bv-q-star', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var val = parseInt($(this).data('val'), 10);
        if (isNaN(val)) return;
        var $wrap = $(this).closest('.bv-q-rating-wrap');
        $wrap.find('.bv-q-star').each(function() {
            var sv = parseInt($(this).data('val'), 10);
            if (sv <= val) {
                $(this).removeClass('bv-q-star-empty').addClass('bv-q-star-filled');
                $(this).css('color', '#f59e0b');
            } else {
                $(this).removeClass('bv-q-star-filled').addClass('bv-q-star-empty');
                $(this).css('color', '#d1d5db');
            }
        });
        var $hidden = $wrap.find('input[type=hidden]');
        if ($hidden.length) $hidden.val(val);
    });

    // Hover preview for rating stars
    $(document).on('mouseenter', '.bv-q-star', function() {
        var val = parseInt($(this).data('val'), 10);
        if (isNaN(val)) return;
        var $wrap = $(this).closest('.bv-q-rating-wrap');
        $wrap.find('.bv-q-star').each(function() {
            var sv = parseInt($(this).data('val'), 10);
            if (sv <= val) {
                $(this).css('color', '#fbbf24'); // lighter gold on hover
                $(this).css('transform', 'scale(1.15)');
            } else {
                $(this).css('color', '#d1d5db');
                $(this).css('transform', 'scale(1)');
            }
        });
    });
    $(document).on('mouseleave', '.bv-q-rating-wrap', function() {
        var $wrap = $(this);
        var currentVal = parseInt($wrap.find('input[type=hidden]').val(), 10) || 0;
        $wrap.find('.bv-q-star').each(function() {
            var sv = parseInt($(this).data('val'), 10);
            $(this).css('transform', 'scale(1)');
            if (sv <= currentVal) {
                $(this).css('color', '#f59e0b');
            } else {
                $(this).css('color', '#d1d5db');
            }
        });
    });

    // ============================================
    // Repeatable Table — Add Row
    // ============================================
    $(document).on('click', '.bv-q-rep-add-row', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $wrap = $(this).closest('.bv-q-repeatable-wrap');
        var $tbody = $wrap.find('tbody');
        var qid = $wrap.data('qid');
        if (!qid) return;
        var colCount = $wrap.find('thead th').length - 1; // last th is actions
        if (colCount < 1) colCount = 1;
        var newIdx = $tbody.find('tr').length;
        var newRow = $('<tr></tr>');
        for (var c = 0; c < colCount; c++) {
            newRow.append($('<td></td>').html(
                '<input type="text" name="q_' + qid + '[' + newIdx + '][' + c + ']" class="bv-q-rep-cell" />'
            ));
        }
        newRow.append($('<td></td>').html(
            '<button type="button" class="bv-q-rep-remove" title="Remove row">&times;</button>'
        ));
        $tbody.append(newRow);
        // Focus the first cell of the new row
        newRow.find('.bv-q-rep-cell').first().focus();
    });

    // ============================================
    // Repeatable Table — Remove Row
    // ============================================
    $(document).on('click', '.bv-q-rep-remove', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $tbody = $(this).closest('tbody');
        if ($tbody.find('tr').length > 1) {
            $(this).closest('tr').fadeOut(150, function() { $(this).remove(); });
        }
    });

    // ============================================
    // Other Option Toggle (radio/checkbox)
    // ============================================
    $(document).on('change', '.bv-q-other-option input[type=radio], .bv-q-other-option input[type=checkbox]', function() {
        var $otherInput = $(this).closest('.bv-q-other-option').find('.bv-q-other-input');
        if ($(this).is(':checked')) {
            $otherInput.show().focus();
        } else {
            $otherInput.hide().val('');
        }
    });

    // ============================================
    // Helper: Extract question ID from name attribute
    // Handles: q_123, q_123[], q_123[0][1], q_123[street]
    // ============================================
    function bvExtractQid(name) {
        var m = name.match(/^q_(\d+)/);
        return m ? m[1] : null;
    }

    // ============================================
    // Helper: Collect structured data for a question
    // from all inputs/selects/textareas with that qid prefix
    // ============================================
    function bvCollectField(form, qid) {
        var prefix = 'q_' + qid;
        var $fields = form.find('input[name^="' + prefix + '"], select[name^="' + prefix + '"], textarea[name^="' + prefix + '"]');

        if ($fields.length === 0) return undefined;

        // Check if any are checkboxes
        var hasCheckbox = false;
        var hasBracket = false;
        $fields.each(function() {
            var n = $(this).attr('name');
            if ($(this).attr('type') === 'checkbox') hasCheckbox = true;
            if (n && n.indexOf('[') > -1) hasBracket = true;
        });

        // Checkbox group (name ends with [])
        if (hasCheckbox) {
            var vals = [];
            $fields.each(function() {
                if ($(this).is(':checked')) vals.push($(this).val());
            });
            return vals;
        }

        // Nested fields (repeatable or address)
        if (hasBracket) {
            var first = $fields.first().attr('name');
            var afterPrefix = first.substring(prefix.length);
            var parts = afterPrefix.match(/\[([^\]]*)\]/g);

            if (parts && parts.length >= 2) {
                // Repeatable table: q_123[0][1], q_123[1][0], etc.
                // Collect into 2D array [row][col]
                var data = {};
                $fields.each(function() {
                    var n = $(this).attr('name');
                    var brackets = n.substring(prefix.length).match(/\[([^\]]*)\]/g);
                    if (brackets && brackets.length >= 2) {
                        var rowIdx = brackets[0].replace(/[\[\]]/g, '');
                        var colIdx = brackets[1].replace(/[\[\]]/g, '');
                        if (!data[rowIdx]) data[rowIdx] = [];
                        data[rowIdx][colIdx] = $(this).val();
                    }
                });
                // Convert object to sorted array
                var rows = [];
                var keys = Object.keys(data).sort(function(a,b){ return parseInt(a) - parseInt(b); });
                for (var i = 0; i < keys.length; i++) {
                    var row = data[keys[i]];
                    // Ensure all columns exist
                    var maxCol = 0;
                    for (var c in row) { if (parseInt(c) > maxCol) maxCol = parseInt(c); }
                    var fullRow = [];
                    for (var j = 0; j <= maxCol; j++) {
                        fullRow.push(row[j] || '');
                    }
                    rows.push(fullRow);
                }
                return rows;
            } else if (parts && parts.length === 1) {
                // Address or keyed object: q_123[street], q_123[city]
                var obj = {};
                $fields.each(function() {
                    var n = $(this).attr('name');
                    var brackets = n.substring(prefix.length).match(/\[([^\]]*)\]/g);
                    if (brackets && brackets.length === 1) {
                        var key = brackets[0].replace(/[\[\]]/g, '');
                        obj[key] = $(this).val();
                    }
                });
                return obj;
            }
        }

        // Radio group — return the checked value
        var firstType = $fields.first().attr('type');
        if (firstType === 'radio') {
            var checkedVal = $fields.filter(':checked').val();
            return checkedVal !== undefined ? checkedVal : '';
        }

        // Simple field
        return $fields.first().val();
    }

    // ============================================
    // Questionnaire form submission (final + draft)
    // ============================================
    function bvPrepareAndSubmit($form, isDraft) {
        var projectId = $form.data('project-id');
        var responses = {};

        // Trigger tinyMCE save so textarea values are up-to-date
        if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
            window.tinyMCE.triggerSave();
        }

        // Collect all unique question IDs (exclude actual file inputs — use hidden data fields)
        var qids = {};
        $form.find('input[name^="q_"]:not([type="file"]), select[name^="q_"], textarea[name^="q_"]').each(function() {
            var qid = bvExtractQid($(this).attr('name'));
            if (qid) qids[qid] = true;
        });

        // Collect data for each question
        for (var qid in qids) {
            var val = bvCollectField($form, qid);
            if (val !== undefined) {
                responses[qid] = val;
            }
        }

        // Collect single-file hidden data (previously uploaded or to be uploaded)
        $form.find('input.bv-q-file-data').each(function() {
            var $h = $(this);
            var qid = $h.data('qid');
            if (qid && $h.val()) {
                responses[qid] = $h.val();
            }
        });

        // Collect multifile hidden data
        $form.find('input.bv-q-multifile-data').each(function() {
            var $h = $(this);
            var qid = $h.data('qid');
            if (qid && $h.val()) {
                responses[qid] = $h.val();
            }
        });

        // Collect signature data
        $form.find('.bv-q-signature-canvas').each(function() {
            var canvas = this;
            var qid = $(canvas).data('qid');
            if (qid && canvas.toDataURL) {
                var blank = document.createElement('canvas');
                blank.width = canvas.width;
                blank.height = canvas.height;
                if (canvas.toDataURL() !== blank.toDataURL()) {
                    responses[qid] = canvas.toDataURL('image/png');
                }
            }
        });

        // Handle single-file uploads via AJAX first (same endpoint as multifile)
        var singleFilePromises = [];
        $form.find('.bv-q-file').each(function() {
            var $input = $(this);
            var qid = $input.data('qid');
            if (!qid || !$input[0].files.length) return;

            var fd = new FormData();
            fd.append('files[]', $input[0].files[0]);
            fd.append('action', 'bv_portal_upload_multifile');
            fd.append('nonce', bv_portal.nonce);
            fd.append('project_id', projectId);
            fd.append('question_id', qid);

            var promise = new Promise(function(resolve, reject) {
                $.ajax({
                    url: bv_portal.ajax_url,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(r) {
                        if (r.success) {
                            resolve({ qid: qid, data: r.data });
                        } else {
                            reject(r.data || 'File upload failed');
                        }
                    },
                    error: function() { reject('Network error during file upload.'); }
                });
            });
            singleFilePromises.push(promise);
        });

        // v2.7.71: Multifile uploads now happen immediately on file select/drop.
        // No need to upload on submit — hidden data input already has server file metadata.
        var allPromises = singleFilePromises;
        var $status = $('#bv-q-status');
        var $btns = $form.find('.bv-btn');
        var origTexts = $btns.map(function() { return $(this).data('bv-orig-text') || $(this).text(); }).get();
        $btns.each(function(i) { $(this).data('bv-orig-text', origTexts[i]); }).text('Saving...').prop('disabled', true);
        $status.html('<span class="bv-q-saving">Saving...</span>');

        if (allPromises.length > 0) {
            Promise.all(allPromises).then(function(results) {
                for (var i = 0; i < results.length; i++) {
                    var qid = results[i].qid;
                    var existingFiles = [];
                    var existing = responses[qid];
                    if (typeof existing === 'string') {
                        try { existingFiles = JSON.parse(existing); } catch(e) {}
                    } else if (Array.isArray(existing)) {
                        existingFiles = existing;
                    }
                    responses[qid] = existingFiles.concat(results[i].data);
                }
                bvSubmitResponses(projectId, responses, $btns, origTexts, isDraft);
            }).catch(function(err) {
                $btns.each(function(i) { $(this).text(origTexts[i]).prop('disabled', false); });
                $status.html('<span class="bv-q-error">' + bvEscapeHtml(String(err)) + '</span>');
            });
        } else {
            bvSubmitResponses(projectId, responses, $btns, origTexts, isDraft);
        }
    }

    // Final submit (Save Questionnaire)
    $(document).on('submit', '#bv-questionnaire-form', function(e) {
        e.preventDefault();
        bvPrepareAndSubmit($(this), false);
    });

    // Draft submit (Save Draft & Continue Later)
    $(document).on('click', '.bv-save-draft', function(e) {
        e.preventDefault();
        e.stopPropagation();
        bvPrepareAndSubmit($(this).closest('#bv-questionnaire-form'), true);
    });

    function bvSubmitResponses(projectId, responses, $btns, origTexts, isDraft) {
        var postData = {
            action: 'bv_portal_submit_questionnaire',
            nonce: bv_portal.nonce,
            project_id: projectId,
            responses: responses
        };
        if (isDraft) postData.is_draft = '1';

        $.post(bv_portal.ajax_url, postData, function(r) {
            $btns.each(function(i) { $(this).text(origTexts[i]).prop('disabled', false); });
            if (r.success) {
                if (isDraft) {
                    $('#bv-q-status').html('<span class="bv-q-saved" style="color:#f59e0b;">&#128190; ' + bvEscapeHtml(r.data || 'Draft saved') + '</span>');
                } else {
                    $('#bv-q-status').html('<span class="bv-q-saved">&#10003; ' + bvEscapeHtml(r.data || 'Saved') + '</span>');
                }
            } else {
                $('#bv-q-status').html('<span class="bv-q-error">' + bvEscapeHtml(r.data || 'Error saving') + '</span>');
            }
        }).fail(function() {
            $btns.each(function(i) { $(this).text(origTexts[i]).prop('disabled', false); });
            $('#bv-q-status').html('<span class="bv-q-error">Network error. Please try again.</span>');
        });
    }

    // ============================================
    // Signature Pad — v2.7.22 rewrite
    // Fixes: mobile scrolling, duplicate listeners, clear & re-sign
    // ============================================
    var bvSigInitialized = {};  // track canvases by data-qid to prevent duplicate listeners

    function bvInitSignatureCanvas(canvas) {
        var $canvas = $(canvas);
        var qid = $canvas.data('qid');
        if (!qid || bvSigInitialized[qid]) return;
        bvSigInitialized[qid] = true;

        var ctx = canvas.getContext('2d');
        var isDrawing = false;
        var lastX = 0, lastY = 0;

        // Prevent scrolling on the wrapper when drawing (but allow button taps)
        var $wrap = $canvas.closest('.bv-q-signature-wrap');
        $wrap[0].addEventListener('touchmove', function(e) {
            // Only prevent if the touch is on or started on the canvas
            if (isDrawing || e.target === canvas) { e.preventDefault(); }
        }, { passive: false });

        function getPos(e) {
            // Always get fresh rect to handle page scroll/resize
            var rect = canvas.getBoundingClientRect();
            var touch = e.touches ? e.touches[0] : e;
            return {
                x: (touch.clientX - rect.left) * (canvas.width / rect.width),
                y: (touch.clientY - rect.top) * (canvas.height / rect.height)
            };
        }

        function startDraw(e) {
            if (canvas.getAttribute('data-signed') === '1') return;
            e.preventDefault();
            e.stopPropagation();
            isDrawing = true;
            var pos = getPos(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function draw(e) {
            if (!isDrawing) return;
            if (canvas.getAttribute('data-signed') === '1') { isDrawing = false; return; }
            e.preventDefault();
            e.stopPropagation();
            var pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = '#1a1a2e';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            // Show confirm button on first stroke
            $canvas.closest('.bv-q-signature-wrap').find('.bv-q-sig-confirm').show();
        }

        function stopDraw(e) {
            isDrawing = false;
        }

        // Mouse events
        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseleave', stopDraw);
        // Touch events — passive:false to allow preventDefault (stops page scrolling)
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw);
    }

    // Initialize any existing canvases on page load
    $(document).ready(function() {
        $('.bv-q-signature-canvas').each(function() {
            bvInitSignatureCanvas(this);
        });
    });

    // Also initialize if new canvases appear (e.g. after AJAX)
    $(document).on('DOMNodeInserted', '.bv-q-signature-wrap', function(e) {
        var canvas = $(e.target).closest('.bv-q-signature-wrap').find('.bv-q-signature-canvas')[0];
        if (canvas) bvInitSignatureCanvas(canvas);
    });

    // Confirm signature
    $(document).on('click', '.bv-q-sig-confirm', function() {
        var $wrap = $(this).closest('.bv-q-signature-wrap');
        var canvas = $wrap.find('.bv-q-signature-canvas')[0];
        if (canvas) {
            canvas.setAttribute('data-signed', '1');
            canvas.style.pointerEvents = 'none';
            $(this).hide();
            $wrap.find('.bv-q-sig-clear').show();
            $wrap.addClass('bv-q-sig-confirmed');
        }
    });

    // Clear signature — handles both canvas and already-saved image states
    $(document).on('click', '.bv-q-sig-clear', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $wrap = $(this).closest('.bv-q-signature-wrap');
        var canvas = $wrap.find('.bv-q-signature-canvas')[0];
        var qid = $wrap.find('.bv-q-signature-canvas').data('qid') || $wrap.find('img').data('qid');

        if (canvas) {
            // Canvas exists — just clear it
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.removeAttribute('data-signed');
            canvas.style.pointerEvents = 'auto';
            $(this).hide();
            $wrap.find('.bv-q-sig-confirm').hide();
            $wrap.removeClass('bv-q-sig-confirmed');
        } else {
            // Already-signed state: img is shown, replace with fresh canvas
            var $img = $wrap.find('img');
            if ($img.length && qid) {
                // Remove initialized flag FIRST so new canvas gets fresh listeners
                delete bvSigInitialized[qid];
                var $canvas = $('<canvas class="bv-q-signature-canvas" data-qid="' + qid + '" width="600" height="160"><\/canvas>');
                var $actions = $('<div class="bv-q-sig-actions"><button type="button" class="bv-q-sig-confirm">&#10003; Confirm Signature<\/button><button type="button" class="bv-q-sig-clear">&#10005; Clear<\/button><\/div>');
                var $hint = $('<p class="bv-q-sig-hint">Draw your signature above using your mouse or finger<\/p>');
                // Remove existing content and add canvas
                $wrap.empty().removeClass('bv-q-sig-confirmed').append($canvas).append($actions).append($hint);
                // Initialize the new canvas for drawing (only once!)
                bvInitSignatureCanvas($canvas[0]);
            }
        }
    });

    // ============================================
    // Single file: show selected filename, allow re-selection
    // ============================================
    $(document).on('change', '.bv-q-file', function() {
        var $input = $(this);
        var $wrap = $input.closest('.bv-q-file-area');
        var qid = $input.data('qid');
        var $status = $wrap.find('.bv-q-file-status');
        if (this.files.length > 0) {
            var sizeStr = (this.files[0].size / 1024).toFixed(1);
            sizeStr = sizeStr > 1024 ? (sizeStr / 1024).toFixed(1) + ' MB' : sizeStr + ' KB';
            if ($status.length) {
                $status.html('<span class="bv-q-file-selected">&#128196; ' + bvEscapeHtml(this.files[0].name) + ' <small>(' + sizeStr + ')</small></span>');
            } else {
                $wrap.append('<div class="bv-q-file-status"><span class="bv-q-file-selected">&#128196; ' + bvEscapeHtml(this.files[0].name) + ' <small>(' + sizeStr + ')</small></span></div>');
            }
        }
    });

    // ============================================
    // Multifile: Dropzone click-to-browse + drag-and-drop
    // v2.7.71: Files upload IMMEDIATELY on select/drop (not on form submit).
    // ============================================
    $(document).on('click', '.bv-q-multifile-dropzone', function() {
        var qid = $(this).data('qid');
        $(this).siblings('.bv-q-multifile-input').data('qid', qid).trigger('click');
    });

    // v2.7.71: Upload multifile immediately on file selection
    function bvUploadMultifileNow($input, files) {
        var $wrap = $input.closest('.bv-q-multifile-wrap');
        var qid = $input.data('qid');
        var $list = $wrap.find('.bv-q-multifile-list');
        var projectId = $input.closest('#bv-questionnaire-form').data('project-id');

        for (var i = 0; i < files.length; i++) {
            (function(file) {
                var sizeStr = (file.size / 1024).toFixed(1);
                sizeStr = sizeStr > 1024 ? (sizeStr / 1024).toFixed(1) + ' MB' : sizeStr + ' KB';

                // Add file item with uploading status
                var $item = $('<div class="bv-q-mf-file" data-filename="' + bvEscapeHtml(file.name) + '">'
                    + '<span class="bv-q-mf-file-icon">&#128196;</span>'
                    + '<span class="bv-q-mf-file-name">' + bvEscapeHtml(file.name) + '</span>'
                    + '<span class="bv-q-mf-file-size bv-mf-uploading">' + sizeStr + ' &mdash; uploading...</span>'
                    + '</div>');
                $list.append($item);

                // Upload immediately via AJAX
                var fd = new FormData();
                fd.append('files[]', file);
                fd.append('action', 'bv_portal_upload_multifile');
                fd.append('nonce', bv_portal.nonce);
                fd.append('project_id', projectId);
                fd.append('question_id', qid);

                $.ajax({
                    url: bv_portal.ajax_url,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(r) {
                        if (r.success && r.data) {
                            // Merge uploaded file metadata into hidden data input
                            var $hidden = $wrap.find('input.bv-q-multifile-data');
                            var existing = [];
                            try { existing = JSON.parse($hidden.val() || '[]'); } catch(e) {}
                            // r.data is an array of uploaded file objects
                            var newData = Array.isArray(r.data) ? r.data : [r.data];
                            var merged = existing.concat(newData);
                            $hidden.val(JSON.stringify(merged));

                            // Update UI: show success status + remove button
                            $item.find('.bv-q-mf-file-size').removeClass('bv-mf-uploading')
                                .html(sizeStr + ' <span style="color:#27AE60;">&#10003;</span>');
                            $item.append('<button type="button" class="bv-q-mf-remove" title="Remove">&times;</button>');
                        } else {
                            // Show error
                            var errMsg = (r.data && typeof r.data === 'string') ? r.data : 'Upload failed';
                            $item.find('.bv-q-mf-file-size').removeClass('bv-mf-uploading')
                                .html('<span style="color:#e74c3c;">' + bvEscapeHtml(errMsg) + '</span>');
                            $item.append('<button type="button" class="bv-q-mf-remove" title="Remove">&times;</button>');
                        }
                    },
                    error: function() {
                        $item.find('.bv-q-mf-file-size').removeClass('bv-mf-uploading')
                            .html('<span style="color:#e74c3c;">Network error</span>');
                        $item.append('<button type="button" class="bv-q-mf-remove" title="Remove">&times;</button>');
                    }
                });
            })(files[i]);
        }
    }

    $(document).on('change', '.bv-q-multifile-input', function() {
        if (this.files.length > 0) {
            bvUploadMultifileNow($(this), this.files);
            // Reset so same file can be re-selected
            $(this).val('');
        }
    });

    // Drag and drop for multifile
    $(document).on('dragover dragenter', '.bv-q-multifile-dropzone', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    $(document).on('dragleave drop', '.bv-q-multifile-dropzone', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    $(document).on('drop', '.bv-q-multifile-dropzone', function(e) {
        var $input = $(this).siblings('.bv-q-multifile-input');
        if (e.originalEvent && e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
            bvUploadMultifileNow($input, e.originalEvent.dataTransfer.files);
        }
    });

    // ============================================
    // Multifile: Remove uploaded file
    // ============================================
    $(document).on('click', '.bv-q-mf-remove', function(e) {
        e.preventDefault();
        var $fileItem = $(this).closest('.bv-q-mf-file');
        var $hidden = $(this).closest('.bv-q-multifile-wrap').find('input.bv-q-multifile-data');
        var fileName = $fileItem.data('filename');
        try {
            var files = JSON.parse($hidden.val() || '[]');
            files = files.filter(function(f) { return f.name !== fileName; });
            $hidden.val(JSON.stringify(files));
        } catch(ex) {}
        $fileItem.fadeOut(200, function() { $(this).remove(); });
    });

    // ============================================
    // Reset Questionnaire — v2.7.58
    // ============================================
    $(document).on('click', '.bv-reset-questionnaire', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#bv-q-reset-modal').fadeIn(150);
    });
    $(document).on('click', '.bv-reset-modal-cancel', function(e) {
        e.preventDefault();
        $('#bv-q-reset-modal').fadeOut(150);
    });
    $(document).on('click', '.bv-reset-modal-overlay', function(e) {
        if (e.target === this) $(this).fadeOut(150);
    });
    $(document).on('click', '.bv-reset-modal-confirm', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var projectId = $('#bv-questionnaire-form').data('project-id');
        $btn.prop('disabled', true).text('Resetting...');
        $('.bv-reset-modal-cancel').prop('disabled', true);
        $.post(bv_portal.ajax_url, {
            action: 'bv_portal_reset_questionnaire',
            nonce: bv_portal.nonce,
            project_id: projectId
        }, function(r) {
            if (r.success) {
                // v2.7.89: also clear the local backup so reset is truly clean
                try { localStorage.removeItem('bv_q_backup_' + projectId); } catch(e) {}
                try { sessionStorage.removeItem('bv_q_autosave_' + projectId); } catch(e) {}
                $('#bv-q-status').html('<span class="bv-q-saved">&#10003; ' + bvEscapeHtml(r.data || 'Reset complete') + '</span>');
                setTimeout(function() { window.location.reload(); }, 800);
            } else {
                $('#bv-q-status').html('<span class="bv-q-error">' + bvEscapeHtml(r.data || 'Error resetting') + '</span>');
                $btn.prop('disabled', false).text('Yes, Reset Everything');
                $('.bv-reset-modal-cancel').prop('disabled', false);
            }
        }).fail(function() {
            $('#bv-q-status').html('<span class="bv-q-error">Network error. Please try again.</span>');
            $btn.prop('disabled', false).text('Yes, Reset Everything');
            $('.bv-reset-modal-cancel').prop('disabled', false);
        });
    });

    // ============================================
    // v2.7.89: Questionnaire safety net
    //   - localStorage backup on every input (survives crashes / refreshes)
    //   - silent autosave to server every 60s
    //   - restore from backup on page load (with prompt)
    //   - nonce keepalive every 5 min
    //   - beforeunload warning when there are unsaved changes
    //   - keep form data + add Retry button on save error
    // ============================================
    var bvQForm = $('#bv-questionnaire-form');
    if (bvQForm.length) {
        var bvProjectId = bvQForm.data('project-id');
        var bvBackupKey = 'bv_q_backup_' + bvProjectId;
        var bvAutosaveKey = 'bv_q_autosave_' + bvProjectId;
        var bvLastSavedSig = null;     // signature of the last successfully-saved state
        var bvDirty = false;            // true if there are unsaved changes since the last backup
        var bvAutosaveInFlight = false; // prevent overlapping autosave requests
        var bvKeepaliveTimer = null;
        var bvAutosaveTimer = null;

        // Build a compact snapshot of the current form state for localStorage.
        // Reuses the existing bvCollectField() helper so all field types
        // (text, textarea, radio, checkbox, select, signature, file-metadata,
        // repeatable, address, etc.) are captured exactly the same way the
        // server would receive them.
        function bvSnapshotForm() {
            var responses = {};
            if (typeof window.tinyMCE !== 'undefined' && window.tinyMCE.activeEditor) {
                window.tinyMCE.triggerSave();
            }
            var qids = {};
            bvQForm.find('input[name^="q_"]:not([type="file"]), select[name^="q_"], textarea[name^="q_"]').each(function() {
                var qid = bvExtractQid($(this).attr('name'));
                if (qid) qids[qid] = true;
            });
            for (var qid in qids) {
                var val = bvCollectField(bvQForm, qid);
                if (val !== undefined && val !== '') responses[qid] = val;
            }
            // Hidden single-file + multifile metadata (server file refs)
            bvQForm.find('input.bv-q-file-data, input.bv-q-multifile-data').each(function() {
                var $h = $(this);
                var qid = $h.data('qid');
                if (qid && $h.val()) responses[qid] = $h.val();
            });
            // Signature canvases → data URL
            bvQForm.find('.bv-q-signature-canvas').each(function() {
                var canvas = this;
                var qid = $(canvas).data('qid');
                if (qid && canvas.toDataURL) {
                    var blank = document.createElement('canvas');
                    blank.width = canvas.width;
                    blank.height = canvas.height;
                    if (canvas.toDataURL() !== blank.toDataURL()) {
                        responses[qid] = canvas.toDataURL('image/png');
                    }
                }
            });
            return responses;
        }

        // Compute a cheap hash of the snapshot so we can detect "no change"
        // and skip redundant autosaves.
        function bvSnapshotSig(responses) {
            try { return JSON.stringify(responses); } catch(e) { return ''; }
        }

        // Persist the current form state to localStorage (instant, offline-safe).
        function bvBackupLocal() {
            var snap = bvSnapshotForm();
            var sig = bvSnapshotSig(snap);
            if (sig === bvLastSavedSig) {
                bvDirty = false;
                return;
            }
            try {
                localStorage.setItem(bvBackupKey, JSON.stringify({
                    ts: Date.now(),
                    data: snap
                }));
                bvDirty = false;
            } catch(e) {
                // Quota exceeded or storage disabled — non-fatal; the server
                // autosave is still the primary safety net.
            }
        }

        // Apply a previously-saved snapshot back into the form fields.
        function bvRestoreFromSnapshot(responses) {
            if (!responses || typeof responses !== 'object') return;
            for (var qid in responses) {
                var val = responses[qid];
                // text / textarea / select-one
                bvQForm.find('[name="q_' + qid + '"]').each(function() {
                    var $el = $(this);
                    var type = $el.attr('type');
                    if (type === 'radio') {
                        $el.prop('checked', $el.val() === val);
                    } else if (type === 'checkbox') {
                        // val may be an array (checkbox group) or a JSON string
                        var arr = Array.isArray(val) ? val : (function(){ try { return JSON.parse(val); } catch(e) { return [val]; } })();
                        if (!Array.isArray(arr)) arr = [arr];
                        $el.prop('checked', arr.indexOf($el.val()) !== -1);
                    } else {
                        $el.val(val);
                    }
                });
                // Hidden file metadata
                bvQForm.find('input.bv-q-file-data[data-qid="' + qid + '"], input.bv-q-multifile-data[data-qid="' + qid + '"]').val(val);
                // Other-option text field
                bvQForm.find('#q_' + qid + '_other_text').each(function() {
                    // val for "other" text is stored separately in post_meta; we
                    // only restore when the parent value is '__other__'
                    if (val === '__other__') {
                        $(this).show();
                    }
                });
                // Signature canvas — restore drawing
                bvQForm.find('.bv-q-signature-canvas[data-qid="' + qid + '"]').each(function() {
                    if (typeof val === 'string' && val.indexOf('data:image/png;base64,') === 0) {
                        var img = new Image();
                        var canvas = this;
                        img.onload = function() {
                            var ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        };
                        img.src = val;
                    }
                });
            }
        }

        // ---- Status helpers (declared early so the restore-on-load IIFE below can use them) ----
        var $bvStatusEl = $('#bv-q-status');
        function bvShowStatus(type, msg, timeoutMs) {
            var cls = type === 'error' ? 'bv-q-error' : (type === 'info' ? 'bv-q-info' : 'bv-q-saved');
            $bvStatusEl.html('<span class="' + cls + '">' + bvEscapeHtml(msg) + '</span>');
            if (timeoutMs) {
                setTimeout(function() {
                    if ($bvStatusEl.find('.' + cls).length) $bvStatusEl.empty();
                }, timeoutMs);
            }
        }

        // On page load: check for a previous backup and offer to restore.
        // This fires after the server-rendered form (with any saved draft) is
        // in the DOM, so the user can choose between the server draft and
        // their more recent local backup.
        (function bvMaybeRestore() {
            var raw = null;
            try { raw = localStorage.getItem(bvBackupKey); } catch(e) { return; }
            if (!raw) return;
            var parsed;
            try { parsed = JSON.parse(raw); } catch(e) { return; }
            if (!parsed || !parsed.data) return;
            // Compare local backup to what's currently in the form. If they
            // match, no restore needed. If they differ, prompt the user.
            var currentSig = bvSnapshotSig(bvSnapshotForm());
            var backupSig = bvSnapshotSig(parsed.data);
            if (currentSig === backupSig) {
                // Local backup matches the server-rendered state — clear it.
                try { localStorage.removeItem(bvBackupKey); } catch(e) {}
                return;
            }
            // There IS a meaningful difference. Prompt the user.
            var msg = (bv_portal.i18n && bv_portal.i18n.restore_prompt) || 'We found unsaved answers from your previous session. Restore them?';
            if (window.confirm(msg)) {
                bvRestoreFromSnapshot(parsed.data);
                bvShowStatus('info', 'Previous session restored.');
            } else {
                // User chose to discard — clear the local backup so it stops prompting.
                try { localStorage.removeItem(bvBackupKey); } catch(e) {}
            }
        })();

        // ---- Autosave indicator pill (top of the form) ----
        var $bvAutosavePill = $('<span class="bv-q-autosave-pill" />').text('');
        bvQForm.prepend($bvAutosavePill);

        function bvUpdateAutosavePill(state, extra) {
            // state: 'idle' | 'saving' | 'saved' | 'error'
            $bvAutosavePill.removeClass('bv-q-as-saving bv-q-as-saved bv-q-as-error');
            if (state === 'saving') {
                $bvAutosavePill.addClass('bv-q-as-saving').html('&#8987; ' + (extra || 'Saving…'));
            } else if (state === 'saved') {
                $bvAutosavePill.addClass('bv-q-as-saved').html('&#10003; ' + (extra || 'Saved'));
            } else if (state === 'error') {
                $bvAutosavePill.addClass('bv-q-as-error').html('&#9888; ' + (extra || 'Autosave failed'));
            } else {
                $bvAutosavePill.html('');
            }
        }

        // ---- Silent server autosave (uses the existing draft endpoint) ----
        function bvAutosaveToServer() {
            if (bvAutosaveInFlight) return;
            var snap = bvSnapshotForm();
            var sig = bvSnapshotSig(snap);
            // Nothing to save / nothing changed since the last save
            if (sig === bvLastSavedSig) return;
            if ($.isEmptyObject(snap)) return; // empty form — nothing to save
            bvAutosaveInFlight = true;
            bvUpdateAutosavePill('saving');
            $.post(bv_portal.ajax_url, {
                action: 'bv_portal_submit_questionnaire',
                nonce: bv_portal.nonce,
                project_id: bvProjectId,
                is_draft: '1',
                responses: snap
            }, function(r) {
                bvAutosaveInFlight = false;
                if (r.success) {
                    bvLastSavedSig = sig;
                    // Also stash a tiny marker in sessionStorage so we know
                    // the server has accepted at least one autosave this session.
                    try { sessionStorage.setItem(bvAutosaveKey, String(Date.now())); } catch(e) {}
                    var ts = new Date();
                    var hh = String(ts.getHours()).padStart(2, '0');
                    var mm = String(ts.getMinutes()).padStart(2, '0');
                    bvUpdateAutosavePill('saved', 'Saved ' + hh + ':' + mm);
                    // Auto-clear the pill after 6 seconds so it doesn't linger
                    setTimeout(function() {
                        if ($bvAutosavePill.hasClass('bv-q-as-saved')) bvUpdateAutosavePill('idle');
                    }, 6000);
                } else {
                    bvUpdateAutosavePill('error', bv_portal.i18n && bv_portal.i18n.autosave_failed ? 'Save failed — keeping local backup' : 'Save failed — local backup active');
                    // Local backup is still being taken on every input, so
                    // the user's work is safe even if the server autosave fails.
                }
            }).fail(function() {
                bvAutosaveInFlight = false;
                bvUpdateAutosavePill('error', 'Network error — local backup active');
            });
        }

        // ---- Keepalive: refresh the nonce periodically ----
        function bvKeepalive() {
            $.post(bv_portal.ajax_url, {
                action: 'bv_portal_keepalive',
                nonce: bv_portal.nonce
            }, function(r) {
                if (r.success && r.data && r.data.nonce) {
                    bv_portal.nonce = r.data.nonce; // refresh the stored nonce
                }
            }).fail(function() {
                // Non-fatal — the next autosave attempt will surface a real
                // network error if the server is truly unreachable.
            });
        }

        // ---- Wire up input → local backup (debounced 800ms) ----
        var bvBackupTimer = null;
        bvQForm.on('input change', 'input, select, textarea', function() {
            bvDirty = true;
            if (bvBackupTimer) clearTimeout(bvBackupTimer);
            bvBackupTimer = setTimeout(bvBackupLocal, 800);
        });
        // Signatures emit custom events when the user finishes a stroke —
        // we listen on the wrapper and run the backup.
        bvQForm.on('pointerup mouseup touchend', '.bv-q-signature-canvas', function() {
            bvDirty = true;
            if (bvBackupTimer) clearTimeout(bvBackupTimer);
            bvBackupTimer = setTimeout(bvBackupLocal, 800);
        });

        // ---- Start the autosave + keepalive timers ----
        var autosaveIntervalMs = (parseInt(bv_portal.autosave_interval, 10) || 60) * 1000;
        var keepaliveIntervalMs = (parseInt(bv_portal.keepalive_interval, 10) || 300) * 1000;
        // First autosave after 30s (so we capture early changes), then every 60s.
        setTimeout(bvAutosaveToServer, 30000);
        bvAutosaveTimer = setInterval(bvAutosaveToServer, autosaveIntervalMs);
        bvKeepaliveTimer = setInterval(bvKeepalive, keepaliveIntervalMs);

        // Take a backup right now (in case the user leaves immediately).
        bvLastSavedSig = bvSnapshotSig(bvSnapshotForm());

        // ---- beforeunload: warn if there are unsaved changes ----
        $(window).on('beforeunload', function(e) {
            if (bvDirty) {
                var msg = (bv_portal.i18n && bv_portal.i18n.unsaved_warning) || 'You have unsaved answers. Are you sure you want to leave?';
                e.originalEvent.returnValue = msg;
                return msg;
            }
        });

        // Clear the beforeunload warning after a successful manual save,
        // so a normal "Save Questionnaire" doesn't trip the prompt.
        $(document).on('bv-questionnaire-saved', function() {
            bvDirty = false;
            try { localStorage.removeItem(bvBackupKey); } catch(e) {}
            bvLastSavedSig = bvSnapshotSig(bvSnapshotForm());
        });

        // ---- Enhanced error handling on manual save ----
        // Wrap the existing bvSubmitResponses() so a network failure keeps the
        // form data intact (the original code already keeps the fields, but
        // the user might panic and refresh) and adds a Retry button.
        var bvOriginalSubmit = bvSubmitResponses;
        bvSubmitResponses = function(projectId, responses, $btns, origTexts, isDraft) {
            $btns.each(function(i) { $(this).prop('disabled', true); });
            // Take a local backup BEFORE the save attempt — if the server is
            // unreachable, the user's work is still safe in localStorage.
            try {
                localStorage.setItem(bvBackupKey, JSON.stringify({
                    ts: Date.now(),
                    data: responses
                }));
            } catch(e) {}
            $.post(bv_portal.ajax_url, {
                action: 'bv_portal_submit_questionnaire',
                nonce: bv_portal.nonce,
                project_id: projectId,
                responses: responses,
                is_draft: isDraft ? '1' : undefined
            }, function(r) {
                $btns.each(function(i) { $(this).text(origTexts[i]).prop('disabled', false); });
                if (r.success) {
                    if (isDraft) {
                        $('#bv-q-status').html('<span class="bv-q-saved" style="color:#f59e0b;">&#128190; ' + bvEscapeHtml(r.data || 'Draft saved') + '</span>');
                    } else {
                        $('#bv-q-status').html('<span class="bv-q-saved">&#10003; ' + bvEscapeHtml(r.data || 'Saved') + '</span>');
                    }
                    bvDirty = false;
                    try { localStorage.removeItem(bvBackupKey); } catch(e) {}
                    bvLastSavedSig = bvSnapshotSig(bvSnapshotForm());
                    $(document).triggerHandler('bv-questionnaire-saved');
                } else {
                    $('#bv-q-status').html('<span class="bv-q-error">' + bvEscapeHtml(r.data || 'Error saving') + '</span>');
                }
            }).fail(function(xhr) {
                $btns.each(function(i) { $(this).text(origTexts[i]).prop('disabled', false); });
                // The form data is STILL in the fields — DO NOT clear anything.
                // Show a clear, reassuring message with a retry button.
                var msg = 'Network error. Your answers are safe on this device.';
                var retry = ' <a href="#" class="bv-q-retry" style="color:#fff;background:#dc2626;padding:6px 14px;border-radius:6px;margin-left:8px;text-decoration:none;font-weight:600;">Retry save</a>';
                $('#bv-q-status').html('<span class="bv-q-error">' + bvEscapeHtml(msg) + '</span>' + retry);
                bvUpdateAutosavePill('error', 'Last save failed');
            });
        };

        // Delegated Retry button — re-triggers the standard submit flow so
        // all the file-upload + collection logic runs again.
        $(document).on('click', '.bv-q-retry', function(e) {
            e.preventDefault();
            $('#bv-q-status').empty();
            // Re-use whichever button the user originally clicked. If we
            // can't tell, default to "Save Questionnaire" (final submit).
            var $form = $('#bv-questionnaire-form');
            bvPrepareAndSubmit($form, false);
        });
    }

})(jQuery);
