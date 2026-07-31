/**
 * BusinessVance Questionnaire Admin — JavaScript
 */
(function($) {
    'use strict';

    var BVQT = window.bvQT || {};

    // ── Template List ──
    function loadTemplates() {
        $.post(ajaxurl, { action: 'bv_qt_get_templates', nonce: BVQT.nonce }, function(res) {
            if (!res.success) return;
            var rows = '';
            if (res.data.length === 0) {
                rows = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">No questionnaire templates yet. Click "Add New Template" to create one.</td></tr>';
            } else {
                $.each(res.data, function(i, t) {
                    var statusBadge = t.status === 'published'
                        ? '<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:12px;font-size:12px;">Published</span>'
                        : '<span style="background:#e2e3e5;color:#383d41;padding:2px 10px;border-radius:12px;font-size:12px;">Draft</span>';
                    rows += '<tr>'
                        + '<td><strong>' + escHtml(t.name) + '</strong><br><small style="color:#888;">' + escHtml(t.slug) + '</small></td>'
                        + '<td>' + statusBadge + '</td>'
                        + '<td>' + parseInt(t.section_count, 10) + '</td>'
                        + '<td>' + parseInt(t.question_count, 10) + '</td>'
                        + '<td>' + escHtml(t.created_at) + '</td>'
                        + '<td>'
                        +   '<button class="button button-small bv-qt-edit" data-id="' + t.id + '">Edit</button> '
                        +   '<button class="button button-small bv-qt-duplicate" data-id="' + t.id + '">Duplicate</button> '
                        +   '<button class="button button-small bv-qt-delete" data-id="' + t.id + '" style="color:#a00;">Delete</button>'
                        + '</td></tr>';
                });
            }
            $('#bv-qt-tbody').html(rows);
        });
    }

    // ── Edit Template ──
    function editTemplate(id) {
        if (id === 0) {
            $('#bv-qt-edit-id').val(0);
            $('#bv-qt-edit-name').val('');
            $('#bv-qt-edit-slug').val('');
            $('#bv-qt-edit-desc').val('');
            $('#bv-qt-edit-status').val('draft');
            $('#bv-qt-editor').show();
            $('#bv-qt-sections-panel').hide();
            $('#bv-qt-edit-title').text('Add New Template');
            return;
        }
        $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: id }, function(res) {
            if (!res.success) return;
            var t = res.data.template;
            $('#bv-qt-edit-id').val(t.id);
            $('#bv-qt-edit-name').val(t.name);
            $('#bv-qt-edit-slug').val(t.slug);
            $('#bv-qt-edit-desc').val(t.description);
            $('#bv-qt-edit-status').val(t.status);
            $('#bv-qt-edit-title').text('Edit Template: ' + t.name);
            $('#bv-qt-editor').show();
            renderSections(res.data.sections || []);
        });
    }

    // ── Sections ──
    function renderSections(sections) {
        var panel = $('#bv-qt-sections-panel').show();
        var list = $('#bv-qt-sections-list').empty();
        if (sections.length === 0) {
            list.html('<li style="text-align:center;padding:20px;color:#666;border:1px dashed #ccc;border-radius:4px;">No sections yet. Add one below.</li>');
            return;
        }
        $.each(sections, function(i, s) {
            var questions = s.questions || [];
            var li = $('<li class="bv-qt-section-item" data-section-id="' + s.id + '"></li>');
            var header = $('<div class="bv-qt-section-header"></div>');
            header.html('<strong>' + (i+1) + '. ' + escHtml(s.title) + '</strong>'
                + '<span class="bv-qt-meta">' + questions.length + ' question' + (questions.length !== 1 ? 's' : '') + '</span>'
                + '<span class="bv-qt-section-actions">'
                +   '<button class="button button-small bv-qt-toggle-section" data-sid="' + s.id + '">▼</button> '
                +   '<button class="button button-small bv-qt-move-up" data-sid="' + s.id + '" title="Move up">↑</button> '
                +   '<button class="button button-small bv-qt-move-down" data-sid="' + s.id + '" title="Move down">↓</button> '
                +   '<button class="button button-small bv-qt-edit-section" data-sid="' + s.id + '">Edit</button> '
                +   '<button class="button button-small bv-qt-del-section" data-sid="' + s.id + '" style="color:#a00;">Delete</button>'
                + '</span>');
            li.append(header);

            var qList = $('<ol class="bv-qt-questions-list" style="display:none;"></ol>');
            $.each(questions, function(j, q) {
                var optInfo = q.options_parsed ? ' <small style="color:#888;">(' + q.options_parsed + ' opts)</small>' : '';
                var reqBadge = q.is_required == 1 ? ' <span style="color:#c00;font-size:11px;">*required</span>' : '';
                var qli = $('<li data-qid="' + q.id + '"></li>');
                qli.html((j+1) + '. <strong>' + escHtml(q.label) + '</strong> <small style="color:#888;">[' + escHtml(q.type) + ']</small>' + optInfo + reqBadge
                    + ' <span class="bv-qt-q-actions">'
                    +   '<button class="button button-small bv-qt-edit-q" data-qid="' + q.id + '" data-sid="' + s.id + '">Edit</button> '
                    +   '<button class="button button-small bv-qt-del-q" data-qid="' + q.id + '" style="color:#a00;">Delete</button>'
                    + '</span>');
                qList.append(qli);
            });
            li.append(qList);
            list.append(li);
        });
    }

    // ── Auto-generate slug from name ──
    function slugify(text) {
        return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    // ── Escape HTML ──
    function escHtml(str) {
        if (!str) return '';
        return $('<span>').text(str).html();
    }

    // ── DOM Ready ──
    $(document).ready(function() {

        // Load templates on page load
        loadTemplates();

        // Add New button
        $('#bv-qt-add-new').on('click', function() { editTemplate(0); });

        // Back to list
        $('#bv-qt-back').on('click', function() {
            $('#bv-qt-editor').hide();
            $('#bv-qt-sections-panel').hide();
            $('#bv-qt-list').show();
        });

        // Edit template (list)
        $(document).on('click', '.bv-qt-edit', function() {
            $('#bv-qt-list').hide();
            editTemplate(parseInt($(this).data('id'), 10));
        });

        // Duplicate template
        $(document).on('click', '.bv-qt-duplicate', function() {
            if (!confirm('Duplicate this template and all its sections/questions?')) return;
            var id = $(this).data('id');
            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: id }, function(res) {
                if (!res.success) return;
                var t = res.data.template;
                $.post(ajaxurl, {
                    action: 'bv_qt_save_template', nonce: BVQT.nonce,
                    id: 0, name: t.name + ' (Copy)', slug: '', description: t.description, status: 'draft'
                }, function(res2) {
                    if (!res2.success) { alert('Duplicate failed'); return; }
                    var newId = res2.data.id;
                    // Copy sections + questions
                    var sections = res.data.sections || [];
                    var sectionMap = {};
                    var pending = sections.length;
                    if (pending === 0) { loadTemplates(); return; }
                    $.each(sections, function(i, s) {
                        $.post(ajaxurl, {
                            action: 'bv_qt_save_section', nonce: BVQT.nonce,
                            id: 0, template_id: newId, title: s.title, description: s.description, display_order: s.display_order
                        }, function(res3) {
                            sectionMap[s.id] = res3.data.id;
                            var questions = s.questions || [];
                            var qPending = questions.length;
                            if (qPending === 0) { pending--; if(pending===0) loadTemplates(); return; }
                            $.each(questions, function(j, q) {
                                $.post(ajaxurl, {
                                    action: 'bv_qt_save_question', nonce: BVQT.nonce,
                                    id: 0, section_id: res3.data.id, type: q.type, label: q.label,
                                    placeholder: q.placeholder, is_required: q.is_required,
                                    help_text: q.help_text, options_text: q.options_raw || '', display_order: q.display_order
                                }, function() { qPending--; if(qPending===0){ pending--; if(pending===0) loadTemplates(); } });
                            });
                        });
                    });
                });
            });
        });

        // Delete template (list)
        $(document).on('click', '.bv-qt-delete', function() {
            if (!confirm('Delete this template and ALL its sections and questions?')) return;
            $.post(ajaxurl, { action: 'bv_qt_delete_template', nonce: BVQT.nonce, id: $(this).data('id') }, function(res) {
                alert(res.success ? 'Deleted.' : res.data.message);
                loadTemplates();
            });
        });

        // Auto slug from name
        $('#bv-qt-edit-name').on('input', function() {
            $('#bv-qt-edit-slug').val(slugify($(this).val()));
        });

        // Save template
        $('#bv-qt-save-template').on('click', function() {
            $.post(ajaxurl, {
                action: 'bv_qt_save_template', nonce: BVQT.nonce,
                id: $('#bv-qt-edit-id').val(),
                name: $('#bv-qt-edit-name').val(),
                slug: $('#bv-qt-edit-slug').val(),
                description: $('#bv-qt-edit-desc').val(),
                status: $('#bv-qt-edit-status').val()
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                var id = res.data.id;
                if ($('#bv-qt-edit-id').val() == 0) {
                    $('#bv-qt-edit-id').val(id);
                    $('#bv-qt-edit-title').text('Edit Template: ' + $('#bv-qt-edit-name').val());
                }
                editTemplate(id);
            });
        });

        // ── Sections ──

        // Toggle section expand
        $(document).on('click', '.bv-qt-toggle-section', function() {
            var qList = $(this).closest('.bv-qt-section-item').find('.bv-qt-questions-list');
            qList.toggle();
            $(this).text(qList.is(':visible') ? '▲' : '▼');
        });

        // Add section
        $('#bv-qt-add-section').on('click', function() {
            var title = prompt('Section title:');
            if (!title) return;
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: 0, template_id: $('#bv-qt-edit-id').val(),
                title: title, description: ''
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
            });
        });

        // Edit section
        $(document).on('click', '.bv-qt-edit-section', function() {
            var sid = $(this).data('sid');
            var item = $(this).closest('.bv-qt-section-item');
            var title = item.find('strong').first().text().replace(/^\d+\.\s*/, '');
            var newTitle = prompt('Edit section title:', title);
            if (!newTitle || newTitle === title) return;
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: sid, template_id: $('#bv-qt-edit-id').val(),
                title: newTitle, description: '', display_order: item.index()
            }, function(res) {
                if (res.success) editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
            });
        });

        // Delete section
        $(document).on('click', '.bv-qt-del-section', function() {
            if (!confirm('Delete this section and all its questions?')) return;
            $.post(ajaxurl, {
                action: 'bv_qt_delete_section', nonce: BVQT.nonce,
                id: $(this).data('sid')
            }, function(res) {
                if (res.success) editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
            });
        });

        // Move section up/down
        $(document).on('click', '.bv-qt-move-up, .bv-qt-move-down', function() {
            var list = $('#bv-qt-sections-list');
            var item = $(this).closest('.bv-qt-section-item');
            var dir = $(this).hasClass('bv-qt-move-up') ? 'up' : 'down';
            if (dir === 'up' && item.index() === 0) return;
            if (dir === 'down' && item.index() === list.children().length - 1) return;
            if (dir === 'up') item.prev().before(item);
            else item.next().after(item);
            // Save new order
            var ids = [];
            list.children('.bv-qt-section-item').each(function() { ids.push($(this).data('section-id')); });
            $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'section', ids: ids.join(',') });
        });

        // ── Questions ──

        // Add question to section
        $(document).on('click', '.bv-qt-add-q', function() {
            var sid = $(this).data('sid');
            var label = prompt('Question label:');
            if (!label) return;
            $.post(ajaxurl, {
                action: 'bv_qt_save_question', nonce: BVQT.nonce,
                id: 0, section_id: sid, type: 'text', label: label,
                placeholder: '', is_required: 0, help_text: '', options_text: ''
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
            });
        });

        // Edit question → opens inline form
        $(document).on('click', '.bv-qt-edit-q', function() {
            var qid = $(this).data('qid');
            var sid = $(this).data('sid');
            // Load question details
            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: parseInt($('#bv-qt-edit-id').val(), 10) }, function(res) {
                if (!res.success) return;
                var q = null;
                $.each(res.data.sections, function(i, s) {
                    $.each(s.questions, function(j, qq) {
                        if (qq.id == qid) q = qq;
                    });
                });
                if (!q) return;

                // Build options text from JSON
                var optsText = '';
                if (q.options && Array.isArray(q.options)) {
                    $.each(q.options, function(i, o) {
                        var v = typeof o === 'object' ? (o.value || '') : o;
                        var l = typeof o === 'object' ? (o.label || '') : o;
                        optsText += v + '|' + l + '\n';
                    });
                }

                var formHtml = '<div id="bv-qt-q-form-wrap" style="background:#fff;padding:16px;margin:8px 0;border:1px solid #ccd0d4;border-radius:4px;">'
                    + '<h4 style="margin:0 0 12px;">Edit Question</h4>'
                    + '<p><label>Type:</label><br><select id="bvq-type">'
                    +   '<option value="text"' + (q.type==='text'?' selected':'') + '>Text</option>'
                    +   '<option value="textarea"' + (q.type==='textarea'?' selected':'') + '>Textarea</option>'
                    +   '<option value="number"' + (q.type==='number'?' selected':'') + '>Number</option>'
                    +   '<option value="email"' + (q.type==='email'?' selected':'') + '>Email</option>'
                    +   '<option value="phone"' + (q.type==='phone'?' selected':'') + '>Phone</option>'
                    +   '<option value="date"' + (q.type==='date'?' selected':'') + '>Date</option>'
                    +   '<option value="select"' + (q.type==='select'?' selected':'') + '>Select (dropdown)</option>'
                    +   '<option value="radio"' + (q.type==='radio'?' selected':'') + '>Radio</option>'
                    +   '<option value="checkbox"' + (q.type==='checkbox'?' selected':'') + '>Checkbox</option>'
                    +   '<option value="heading"' + (q.type==='heading'?' selected':'') + '>Heading</option>'
                    +   '<option value="paragraph"' + (q.type==='paragraph'?' selected':'') + '>Paragraph</option>'
                    +   '</select></p>'
                    + '<p><label>Label:</label><br><input type="text" id="bvq-label" class="regular-text" value="' + escAttr(q.label) + '"></p>'
                    + '<p class="bvq-placeholder-row"><label>Placeholder:</label><br><input type="text" id="bvq-placeholder" class="regular-text" value="' + escAttr(q.placeholder || '') + '"></p>'
                    + '<p><label><input type="checkbox" id="bvq-required"' + (q.is_required==1?' checked':'') + '> Required</label></p>'
                    + '<p><label>Help Text:</label><br><input type="text" id="bvq-help" class="regular-text" value="' + escAttr(q.help_text || '') + '"></p>'
                    + '<p class="bvq-options-row"><label>Options (one per line: value|Label):</label><br><textarea id="bvq-options" rows="4" class="large-text">' + escHtml(optsText) + '</textarea></p>'
                    + '<p><button class="button button-primary" id="bvq-save">Save Question</button> <button class="button" id="bvq-cancel">Cancel</button></p>'
                    + '</div>';
                $(this).closest('li').after(formHtml);
                toggleOptionVisibility();
            });
        });

        // Toggle options field based on type
        $(document).on('change', '#bvq-type', toggleOptionVisibility);
        function toggleOptionVisibility() {
            var t = $('#bvq-type').val();
            var showOpts = (t === 'select' || t === 'radio' || t === 'checkbox');
            var showPH = (t !== 'heading' && t !== 'paragraph' && t !== 'checkbox');
            $('.bvq-options-row').toggle(showOpts);
            $('.bvq-placeholder-row').toggle(showPH);
        }

        // Save question edit
        $(document).on('click', '#bvq-save', function() {
            var qid = $(this).closest('#bv-qt-q-form-wrap').prev('li').find('.bv-qt-edit-q').data('qid');
            var sid = $(this).closest('#bv-qt-q-form-wrap').prev('li').find('.bv-qt-edit-q').data('sid');
            $.post(ajaxurl, {
                action: 'bv_qt_save_question', nonce: BVQT.nonce,
                id: qid, section_id: sid,
                type: $('#bvq-type').val(),
                label: $('#bvq-label').val(),
                placeholder: $('#bvq-placeholder').val(),
                is_required: $('#bvq-required').is(':checked') ? 1 : 0,
                help_text: $('#bvq-help').val(),
                options_text: $('#bvq-options').val()
            }, function(res) {
                if (res.success) {
                    editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
                } else {
                    alert(res.data.message);
                }
            });
        });

        // Cancel question edit
        $(document).on('click', '#bvq-cancel', function() {
            $(this).closest('#bv-qt-q-form-wrap').remove();
        });

        // Delete question
        $(document).on('click', '.bv-qt-del-q', function() {
            if (!confirm('Delete this question?')) return;
            $.post(ajaxurl, {
                action: 'bv_qt_delete_question', nonce: BVQT.nonce,
                id: $(this).data('qid')
            }, function(res) {
                if (res.success) editTemplate(parseInt($('#bv-qt-edit-id').val(), 10));
            });
        });

        function escAttr(str) {
            return $('<span>').text(str || '').html().replace(/"/g, '&quot;');
        }
    });

})(jQuery);
