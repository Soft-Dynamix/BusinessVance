/**
 * BusinessVance Questionnaire Admin — JavaScript
 */
(function($) {
    'use strict';

    if (typeof bvQT === 'undefined') { console.error('BV: bvQT not localized'); return; }
    var BVQT = window.bvQT;
    // ajaxurl is a WordPress-admin global, always available on admin pages.

    // ── Helpers ──

    function escHtml(str) {
        if (!str) return '';
        return $('<span>').text(str).html();
    }

    function escAttr(str) {
        return $('<span>').text(str || '').html().replace(/"/g, '&quot;');
    }

    function slugify(text) {
        return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function getTemplateId() {
        return parseInt($('#bv-qt-edit-id').val() || 0, 10);
    }

    // Show a green success notice that auto-dismisses
    function bvQTShowNotice(msg) {
        $('#bv-qt-notices').html('<div class="notice notice-success is-dismissible" style="margin:5px 0"><p>' + msg + '</p></div>');
        setTimeout(function(){ $('#bv-qt-notices').empty(); }, 3000);
    }

    // Build options preview string from question options array
    function bvQTOptsPreview(q) {
        if (!q.options || !Array.isArray(q.options) || q.options.length === 0) return '';
        var labels = [];
        $.each(q.options, function(i, o) {
            var l = typeof o === 'object' ? (o.label || o.value || '') : o;
            if (l) labels.push(l);
        });
        if (labels.length === 0) return '';
        return ' <span class="bv-qt-opts-preview">(' + escHtml(labels.join(', ')) + ')</span>';
    }

    // Build a color-coded type badge
    function bvQTTypeBadge(type) {
        return '<span class="bv-qt-type-badge" data-type="' + escHtml(type) + '">' + escHtml(type) + '</span>';
    }

    // ── Template List ──
    function loadTemplates() {
        $.post(ajaxurl, { action: 'bv_qt_get_templates', nonce: BVQT.nonce }, function(res) {
            if (!res.success) {
                $('#bv-qt-templates-body').html(
                    '<tr><td colspan="7" style="text-align:center;padding:40px;color:#a00;">'
                    + (res.data && res.data.message ? escHtml(res.data.message) : 'Error loading templates.')
                    + '</td></tr>'
                );
                return;
            }
            var templates = res.data.templates || [];
            var rows = '';
            if (templates.length === 0) {
                rows = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#666;">No questionnaire templates yet. Click "Add New Template" to create one.</td></tr>';
            } else {
                $.each(templates, function(i, t) {
                    var statusBadge = t.status === 'published'
                        ? '<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:12px;font-size:12px;">Published</span>'
                        : '<span style="background:#e2e3e5;color:#383d41;padding:2px 10px;border-radius:12px;font-size:12px;">Draft</span>';
                    rows += '<tr>'
                        + '<td><strong>' + escHtml(t.name) + '</strong></td>'
                        + '<td><small style="color:#888;">' + escHtml(t.slug) + '</small></td>'
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
            $('#bv-qt-templates-body').html(rows);
        }).fail(function(xhr, status, error) {
            $('#bv-qt-templates-body').html(
                '<tr><td colspan="7" style="text-align:center;padding:40px;color:#a00;">'
                + 'Request failed: ' + escHtml(status + (error ? ' - ' + error : ''))
                + '. Please try refreshing the page.'
                + '</td></tr>'
            );
        });
    }

    // ── Edit Template ──
    function editTemplate(id) {
        // Show edit view, hide list
        $('#bv-qt-list-view').hide();
        $('#bv-qt-edit-view').show();

        if (id === 0) {
            $('#bv-qt-edit-id').val(0);
            $('#bv-qt-tpl-name').val('');
            $('#bv-qt-tpl-slug').val('');
            $('#bv-qt-tpl-description').val('');
            $('#bv-qt-tpl-status').val('draft');
            $('#bv-qt-sections-panel').hide();
            $('#bv-qt-edit-title').text('Add New Template');
            return;
        }
        $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: id }, function(res) {
            if (!res.success) return;
            var t = res.data.template;
            $('#bv-qt-edit-id').val(t.id);
            $('#bv-qt-tpl-name').val(t.name);
            $('#bv-qt-tpl-slug').val(t.slug);
            $('#bv-qt-tpl-description').val(t.description);
            $('#bv-qt-tpl-status').val(t.status);
            $('#bv-qt-edit-title').text('Edit Template: ' + t.name);
            renderSections(t.sections || []);
        }).fail(function() { alert('Failed to load template.'); });
    }

    // ── Sections ──
    function renderSections(sections) {
        $('#bv-qt-sections-panel').show();
        var list = $('#bv-qt-sections-list').empty();
        if (sections.length === 0) {
            list.html('<li style="text-align:center;padding:20px;color:#666;border:1px dashed #ccc;border-radius:4px;">No sections yet. Add one below.</li>');
            return;
        }
        $.each(sections, function(i, s) {
            var questions = s.questions || [];
            var sectionDesc = s.description || '';

            var li = $('<li class="bv-qt-section-item" data-section-id="' + s.id + '"></li>');
            if (sectionDesc) {
                li.attr('data-section-description', sectionDesc);
            }

            // Section header with number badge
            var header = $('<div class="bv-qt-section-header"></div>');
            var headerInner = '<span class="bv-qt-section-num-badge">' + (i + 1) + '</span>'
                + '<div style="flex:1;min-width:0;">'
                +   '<strong>' + escHtml(s.title) + '</strong>'
                +   (sectionDesc ? '<div class="bv-qt-section-desc">' + escHtml(sectionDesc) + '</div>' : '')
                + '</div>'
                + '<span class="bv-qt-meta">' + questions.length + ' question' + (questions.length !== 1 ? 's' : '') + '</span>'
                + '<span class="bv-qt-section-actions">'
                +   '<button class="button button-small bv-qt-toggle-section" data-sid="' + s.id + '">Collapse</button> '
                +   '<button class="button button-small bv-qt-move-up" data-sid="' + s.id + '" title="Move up">↑</button> '
                +   '<button class="button button-small bv-qt-move-down" data-sid="' + s.id + '" title="Move down">↓</button> '
                +   '<button class="button button-small bv-qt-edit-section" data-sid="' + s.id + '">Edit</button> '
                +   '<button class="button button-small bv-qt-del-section" data-sid="' + s.id + '" style="color:#a00;">Delete</button>'
                + '</span>';
            header.html(headerInner);
            li.append(header);

            // Questions list — EXPANDED by default (no display:none)
            var qList = $('<ol class="bv-qt-questions-list"></ol>');
            $.each(questions, function(j, q) {
                var reqBadge = q.is_required == 1 ? ' <span class="bv-qt-req-badge">*required</span>' : '';
                var optsPreview = (q.type === 'radio' || q.type === 'select' || q.type === 'checkbox') ? bvQTOptsPreview(q) : '';
                var subInfo = '';
                var subParts = [];
                if (q.placeholder) subParts.push('placeholder: ' + escHtml(q.placeholder));
                if (q.help_text) subParts.push(escHtml(q.help_text));
                if (subParts.length > 0) {
                    subInfo = '<div class="bv-qt-q-sub-info">' + subParts.join(' &middot; ') + '</div>';
                }

                var qli = $('<li data-qid="' + q.id + '"></li>');
                qli.html('<div class="bv-qt-q-main-info">'
                    +   (j + 1) + '. <strong>' + escHtml(q.label) + '</strong> '
                    +   bvQTTypeBadge(q.type)
                    +   reqBadge
                    +   optsPreview
                    + '</div>'
                    + subInfo
                    + ' <span class="bv-qt-q-actions">'
                    +   '<button class="button button-small bv-qt-edit-q" data-qid="' + q.id + '" data-sid="' + s.id + '">Edit</button> '
                    +   '<button class="button button-small bv-qt-del-q" data-qid="' + q.id + '" style="color:#a00;">Delete</button>'
                    + '</span>');
                qList.append(qli);
            });
            li.append(qList);

            // Add Question button below the questions list
            var addQWrap = $('<div class="bv-qt-add-q-wrap" style="padding:4px 16px 12px 52px;"></div>');
            addQWrap.html('<button class="button button-small bv-qt-add-q" data-sid="' + s.id + '">'
                + '<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;margin-right:3px;font-size:14px;"></span>'
                + 'Add Question</button>');
            li.append(addQWrap);

            list.append(li);
        });
    }

    // ── DOM Ready ──
    $(document).ready(function() {

        // Load templates on page load
        loadTemplates();

        // Add New Template button
        $('#bv-qt-add-template-btn').on('click', function() { editTemplate(0); });

        // Back to list
        $('#bv-qt-back-to-list').on('click', function() {
            $('#bv-qt-edit-view').hide();
            $('#bv-qt-sections-panel').hide();
            $('#bv-qt-list-view').show();
            loadTemplates();
        });

        // Edit template (from list)
        $(document).on('click', '.bv-qt-edit', function() {
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
                    var newId = res2.data.template_id;
                    var sections = t.sections || [];
                    var pending = sections.length;
                    if (pending === 0) { loadTemplates(); return; }
                    $.each(sections, function(i, s) {
                        $.post(ajaxurl, {
                            action: 'bv_qt_save_section', nonce: BVQT.nonce,
                            id: 0, template_id: newId, title: s.title, description: s.description, display_order: s.display_order
                        }, function(res3) {
                            var questions = s.questions || [];
                            var qPending = questions.length;
                            if (qPending === 0) { pending--; if(pending===0) loadTemplates(); return; }
                            $.each(questions, function(j, q) {
                                var optsStr = '';
                                if (q.options && Array.isArray(q.options)) {
                                    $.each(q.options, function(k, o) {
                                        var v = typeof o === 'object' ? (o.value || '') : o;
                                        var l = typeof o === 'object' ? (o.label || '') : o;
                                        optsStr += v + '|' + l + '\n';
                                    });
                                }
                                $.post(ajaxurl, {
                                    action: 'bv_qt_save_question', nonce: BVQT.nonce,
                                    id: 0, section_id: res3.data.section.id, type: q.type, label: q.label,
                                    placeholder: q.placeholder, is_required: q.is_required,
                                    help_text: q.help_text, options_text: optsStr, display_order: q.display_order
                                }, function() { qPending--; if(qPending===0){ pending--; if(pending===0) loadTemplates(); } })
                                .fail(function() { qPending--; if(qPending===0){ pending--; if(pending===0) loadTemplates(); } alert('Failed to duplicate a question.'); });
                            });
                        }).fail(function() { pending--; if(pending===0) loadTemplates(); alert('Failed to duplicate a section.'); });
                    });
                }).fail(function() { alert('Failed to create duplicate template.'); });
            }).fail(function() { alert('Failed to load template for duplication.'); });
        });

        // Delete template
        $(document).on('click', '.bv-qt-delete', function() {
            if (!confirm('Delete this template and ALL its sections and questions?')) return;
            $.post(ajaxurl, { action: 'bv_qt_delete_template', nonce: BVQT.nonce, id: $(this).data('id') }, function(res) {
                if (res.success) {
                    bvQTShowNotice('Template deleted successfully.');
                    loadTemplates();
                } else {
                    alert(res.data.message);
                    loadTemplates();
                }
            }).fail(function() { alert('Delete failed.'); });
        });

        // Auto slug from name
        $('#bv-qt-tpl-name').on('input', function() {
            $('#bv-qt-tpl-slug').val(slugify($(this).val()));
        });

        // Save template
        $('#bv-qt-save-tpl-btn').on('click', function() {
            var name = $('#bv-qt-tpl-name').val().trim();
            if (!name) { alert('Template name is required.'); return; }
            $.post(ajaxurl, {
                action: 'bv_qt_save_template', nonce: BVQT.nonce,
                id: getTemplateId(),
                name: name,
                slug: $('#bv-qt-tpl-slug').val(),
                description: $('#bv-qt-tpl-description').val(),
                status: $('#bv-qt-tpl-status').val()
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Template saved successfully.');
                var newId = res.data.template_id;
                if (getTemplateId() === 0) {
                    $('#bv-qt-edit-view').data('template-id', newId);
                    $('#bv-qt-edit-title').text('Edit Template: ' + $('#bv-qt-tpl-name').val());
                }
                editTemplate(newId);
            }).fail(function() { alert('Failed to save template.'); });
        });

        // ── Sections ──

        // Toggle section expand/collapse — text-based
        $(document).on('click', '.bv-qt-toggle-section', function() {
            var item = $(this).closest('.bv-qt-section-item');
            var qList = item.find('.bv-qt-questions-list');
            var addBtn = item.find('.bv-qt-add-q-wrap');
            qList.slideToggle(150);
            addBtn.slideToggle(150);
            $(this).text(qList.is(':visible') ? 'Collapse' : 'Expand');
        });

        // Add section button (top of sections panel)
        $('#bv-qt-add-section-btn').on('click', function() {
            $('#bv-qt-section-form-wrap').show();
            $('#bv-qt-section-form-title').text('New Section');
            $('#bv-qt-sec-title').val('');
            $('#bv-qt-sec-description').val('');
            $('#bv-qt-sec-edit-id').val(0);
        });

        // Save section (top form)
        $('#bv-qt-save-sec-btn').on('click', function() {
            var title = $('#bv-qt-sec-title').val().trim();
            if (!title) { alert('Section title is required.'); return; }
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: $('#bv-qt-sec-edit-id').val(),
                template_id: getTemplateId(),
                title: title,
                description: $('#bv-qt-sec-description').val(),
                display_order: 0
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Section saved successfully.');
                $('#bv-qt-section-form-wrap').hide();
                editTemplate(getTemplateId());
            }).fail(function() { alert('Failed to save section.'); });
        });

        // Cancel section (top form)
        $('#bv-qt-cancel-sec-btn').on('click', function() {
            $('#bv-qt-section-form-wrap').hide();
        });

        // Edit section — INLINE form below header
        $(document).on('click', '.bv-qt-edit-section', function() {
            var $btn = $(this);
            var sid = $btn.data('sid');
            var item = $btn.closest('.bv-qt-section-item');

            // Remove any existing inline section forms
            $('.bv-qt-inline-section-form').slideUp(150, function() { $(this).remove(); });

            // Extract current title and description
            var currentTitle = item.find('.bv-qt-section-header strong').text();
            var currentDesc = item.data('sectionDescription') || '';

            var formHtml = '<div class="bv-qt-inline-section-form">'
                + '<table class="form-table">'
                + '<tr><th scope="row"><label>Title <span class="description">(required)</span></label></th>'
                + '<td><input type="text" class="bv-qt-inline-sec-title large-text" value="' + escAttr(currentTitle) + '" /></td></tr>'
                + '<tr><th scope="row"><label>Description</label></th>'
                + '<td><textarea class="bv-qt-inline-sec-desc large-text" rows="2" placeholder="Optional description...">' + escHtml(currentDesc) + '</textarea></td></tr>'
                + '</table>'
                + '<p style="margin-top:10px;">'
                + '<button type="button" class="button button-primary bv-qt-save-inline-sec" data-sid="' + sid + '">Save Section</button> '
                + '<button type="button" class="button bv-qt-cancel-inline-sec">Cancel</button>'
                + '</p>'
                + '</div>';

            // Insert after the section header, before the questions list
            item.find('.bv-qt-section-header').after(formHtml);
        });

        // Save inline section edit
        $(document).on('click', '.bv-qt-save-inline-sec', function() {
            var sid = $(this).data('sid');
            var item = $(this).closest('.bv-qt-section-item');
            var newTitle = item.find('.bv-qt-inline-sec-title').val().trim();
            var newDesc = item.find('.bv-qt-inline-sec-desc').val();
            if (!newTitle) { alert('Section title is required.'); return; }
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: sid, template_id: getTemplateId(),
                title: newTitle, description: newDesc, display_order: item.index()
            }, function(res) {
                if (res.success) {
                    bvQTShowNotice('Section updated successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { alert('Failed to update section.'); });
        });

        // Cancel inline section edit
        $(document).on('click', '.bv-qt-cancel-inline-sec', function() {
            $(this).closest('.bv-qt-inline-section-form').slideUp(150, function() { $(this).remove(); });
        });

        // Delete section
        $(document).on('click', '.bv-qt-del-section', function() {
            if (!confirm('Delete this section and all its questions?')) return;
            $.post(ajaxurl, {
                action: 'bv_qt_delete_section', nonce: BVQT.nonce,
                id: $(this).data('sid')
            }, function(res) {
                if (res.success) {
                    bvQTShowNotice('Section deleted successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { alert('Failed to delete section.'); });
        });

        // Move section up/down
        $(document).on('click', '.bv-qt-move-up, .bv-qt-move-down', function() {
            var list = $('#bv-qt-sections-list');
            var item = $(this).closest('.bv-qt-section-item');
            var dir = $(this).hasClass('bv-qt-move-up') ? 'up' : 'down';
            if (dir === 'up' && item.index() === 0) return;
            if (dir === 'down' && item.index() === list.children().length - 1) return;
            var $prev = item.prev();
            var $next = item.next();
            if (dir === 'up') $prev.before(item);
            else $next.after(item);
            var ids = [];
            list.children('.bv-qt-section-item').each(function() { ids.push($(this).data('section-id')); });
            $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'section', ids: ids.join(',') })
            .fail(function() {
                alert('Reorder failed, reverting.');
                if (dir === 'up') item.insertBefore($prev);
                else item.insertAfter($next);
            });
        });

        // ── Questions ──

        // Add question — INLINE form (reuses hidden template)
        $(document).on('click', '.bv-qt-add-q', function() {
            var $btn = $(this);
            var sid = $btn.data('sid');
            var sectionItem = $btn.closest('.bv-qt-section-item');

            // Remove any existing inline question forms
            $('.bv-qt-inline-q-form').slideUp(150, function() { $(this).remove(); });

            // Clone the hidden form template HTML and adapt IDs
            var formHtml = $('#bv-qt-question-form-template').html()
                .replace(/id="bv-qt-question-form-title"/g, 'id="bv-qt-new-q-form-title"')
                .replace(/id="bv-qt-q-type"/g, 'id="bvq-new-type" class="bv-qt-new-q-type-select"')
                .replace(/id="bv-qt-q-label"/g, 'id="bvq-new-label"')
                .replace(/id="bv-qt-q-placeholder-row"/g, 'id="bvq-new-placeholder-row" class="bv-qt-new-q-placeholder-row"')
                .replace(/id="bv-qt-q-placeholder"/g, 'id="bvq-new-placeholder"')
                .replace(/id="bv-qt-q-required"/g, 'id="bvq-new-required"')
                .replace(/id="bv-qt-q-help-text"/g, 'id="bvq-new-help"')
                .replace(/id="bv-qt-q-options-row"/g, 'id="bvq-new-options-row" class="bv-qt-new-q-options-row"')
                .replace(/id="bv-qt-q-options"/g, 'id="bvq-new-options"')
                .replace(/bv-qt-save-q-btn/g, 'bv-qt-save-new-q')
                .replace(/bv-qt-cancel-q-btn/g, 'bv-qt-cancel-new-q');

            var wrapper = $('<div class="bv-qt-inline-q-form" data-section-id="' + sid + '"></div>');
            wrapper.html(formHtml);

            // Set title and section ID
            wrapper.find('#bv-qt-new-q-form-title').text('Add Question');
            wrapper.find('.bv-qt-q-section-id').val(sid);
            wrapper.find('.bv-qt-q-edit-id').val(0);

            // Insert after the Add Question button wrapper
            $btn.closest('.bv-qt-add-q-wrap').after(wrapper);

            // Initial toggle for options/placeholder visibility
            var initType = wrapper.find('#bvq-new-type').val();
            var showOpts = (initType === 'select' || initType === 'radio' || initType === 'checkbox');
            var showPH = (initType !== 'heading' && initType !== 'paragraph' && initType !== 'checkbox');
            wrapper.find('.bv-qt-new-q-options-row').toggle(showOpts);
            wrapper.find('.bv-qt-new-q-placeholder-row').toggle(showPH);

            // Scroll to form
            wrapper[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        // Toggle options/placeholder visibility in new question form
        $(document).on('change', '.bv-qt-new-q-type-select', function() {
            var t = $(this).val();
            var $form = $(this).closest('.bv-qt-inline-q-form');
            var showOpts = (t === 'select' || t === 'radio' || t === 'checkbox');
            var showPH = (t !== 'heading' && t !== 'paragraph' && t !== 'checkbox');
            $form.find('.bv-qt-new-q-options-row').toggle(showOpts);
            $form.find('.bv-qt-new-q-placeholder-row').toggle(showPH);
        });

        // Save new question (from inline add form)
        $(document).on('click', '.bv-qt-save-new-q', function() {
            var $form = $(this).closest('.bv-qt-inline-q-form');
            var sid = $form.data('section-id');
            var label = $form.find('#bvq-new-label').val().trim();
            if (!label) { alert('Question label is required.'); return; }
            $.post(ajaxurl, {
                action: 'bv_qt_save_question', nonce: BVQT.nonce,
                id: 0, section_id: sid,
                type: $form.find('#bvq-new-type').val(),
                label: label,
                placeholder: $form.find('#bvq-new-placeholder').val(),
                is_required: $form.find('#bvq-new-required').is(':checked') ? 1 : 0,
                help_text: $form.find('#bvq-new-help').val(),
                options_text: $form.find('#bvq-new-options').val()
            }, function(res) {
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Question added successfully.');
                editTemplate(getTemplateId());
            }).fail(function() { alert('Failed to add question.'); });
        });

        // Cancel new question form
        $(document).on('click', '.bv-qt-cancel-new-q', function() {
            $(this).closest('.bv-qt-inline-q-form').slideUp(150, function() { $(this).remove(); });
        });

        // Edit question → opens inline form (DO NOT CHANGE — existing working handler)
        $(document).on('click', '.bv-qt-edit-q', function() {
            var $btn = $(this);
            var qid = $btn.data('qid');
            var sid = $btn.data('sid');
            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: getTemplateId() }, function(res) {
                if (!res.success) return;
                var q = null;
                $.each(res.data.template.sections, function(i, s) {
                    $.each(s.questions, function(j, qq) {
                        if (qq.id == qid) q = qq;
                    });
                });
                if (!q) return;

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
                    + '</select></p>'
                    + '<p><label>Label:</label><br><input type="text" id="bvq-label" class="regular-text" value="' + escAttr(q.label) + '"></p>'
                    + '<p class="bvq-placeholder-row"><label>Placeholder:</label><br><input type="text" id="bvq-placeholder" class="regular-text" value="' + escAttr(q.placeholder || '') + '"></p>'
                    + '<p><label><input type="checkbox" id="bvq-required"' + (q.is_required==1?' checked':'') + '> Required</label></p>'
                    + '<p><label>Help Text:</label><br><input type="text" id="bvq-help" class="regular-text" value="' + escAttr(q.help_text || '') + '"></p>'
                    + '<p class="bvq-options-row"><label>Options (one per line: value|Label):</label><br><textarea id="bvq-options" rows="4" class="large-text">' + escHtml(optsText) + '</textarea></p>'
                    + '<p><button class="button button-primary" id="bvq-save">Save Question</button> <button class="button" id="bvq-cancel">Cancel</button></p>'
                    + '</div>';
                $btn.closest('li').after(formHtml);
                toggleOptionVisibility();
            }).fail(function() { alert('Failed to load template data.'); });
        });

        // Toggle options field based on type (for edit question form)
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
                    bvQTShowNotice('Question updated successfully.');
                    editTemplate(getTemplateId());
                } else {
                    alert(res.data.message);
                }
            }).fail(function() { alert('Failed to save question.'); });
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
                if (res.success) {
                    bvQTShowNotice('Question deleted successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { alert('Failed to delete question.'); });
        });

        // ── Global import function (called from inline button) ──
        window.bvQTImportQuestionnaires = function() {
            if (!confirm('Import Market Research and Business Plan questionnaire templates? Existing templates with the same slug will be skipped.')) return;
            var $btn = $('#bv-qt-import-btn').prop('disabled', true).text('Importing...');
            $.post(ajaxurl, { action: 'bv_qt_import_questionnaires', nonce: BVQT.nonce }, function(res) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Pre-built Questionnaires');
                if (res.success) {
                    var msg = res.data.message;
                    $.each(res.data.results, function(key, r) {
                        msg += '\n• ' + key + ': ' + r.message + ' (' + r.sections + ' sections, ' + r.questions + ' questions)';
                    });
                    alert(msg);
                    loadTemplates();
                } else {
                    var errMsg = res.data && res.data.message ? res.data.message : 'Unknown error';
                    if (errMsg.indexOf('Security check failed') !== -1) {
                        errMsg += ' — Your session may have expired. Please refresh the page and try again.';
                    } else if (errMsg.indexOf('Permission denied') !== -1) {
                        errMsg += ' — You do not have permission to perform this action.';
                    }
                    alert('Import failed: ' + errMsg);
                }
            }).fail(function(xhr, status, error) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Pre-built Questionnaires');
                alert('Request failed: ' + (error || status || 'Network error') + '. The import may have timed out — try refreshing the page.');
            });
        };

        // Helper: download JSON as file
        function bvQTDownloadJson(jsonStr, filename) {
            var blob = new Blob([jsonStr], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // ════════════════════════════════════════════════════════════════════
        // DOCUMENT IMPORT (PDF/Word) — Two-step: Upload → Preview → Confirm
        // ════════════════════════════════════════════════════════════════════

        // Question type labels for preview
        var bvDocTypeLabels = {
            'text': 'Text', 'textarea': 'Textarea', 'number': 'Number',
            'email': 'Email', 'phone': 'Phone', 'date': 'Date',
            'select': 'Dropdown', 'radio': 'Radio', 'checkbox': 'Checkbox',
            'heading': 'Heading', 'paragraph': 'Paragraph', 'file': 'File Upload'
        };

        // Question type colors for preview badges
        var bvDocTypeColors = {
            'text': '#2271b1', 'textarea': '#2271b1', 'number': '#7e5bf0',
            'email': '#2271b1', 'phone': '#2271b1', 'date': '#7e5bf0',
            'select': '#0e7732', 'radio': '#0e7732', 'checkbox': '#0e7732',
            'heading': '#646970', 'paragraph': '#646970', 'file': '#b32d2e'
        };

        // Store parsed data for import confirmation
        var bvQTDocParsedData = null;

        // Open document import modal
        $('#bv-qt-import-doc-btn').on('click', function() {
            bvQTDocShowStep('upload');
            $('#bv-qt-doc-modal').fadeIn(200);
        });

        // Close modal
        window.bvQTDocCloseModal = function() {
            $('#bv-qt-doc-modal').fadeOut(150);
            bvQTDocParsedData = null;
        };

        // Close on overlay click
        $('#bv-qt-doc-modal').on('click', function(e) {
            if ($(e.target).hasClass('bv-qt-modal-overlay')) {
                bvQTDocCloseModal();
            }
        });

        // Close on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#bv-qt-doc-modal').is(':visible')) {
                bvQTDocCloseModal();
            }
        });

        // Show a specific modal step
        function bvQTDocShowStep(step) {
            $('#bv-qt-doc-step-upload, #bv-qt-doc-step-parsing, #bv-qt-doc-step-review').hide();
            $('#bv-qt-doc-step-' + step).css('display', 'flex');
        }

        // Drop zone click opens file input
        $('#bv-qt-doc-drop-zone').on('click', function() {
            $('#bv-qt-doc-upload-input').val('').trigger('click');
        });

        // Drop zone drag events
        var $dropZone = $('#bv-qt-doc-drop-zone');
        $dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({ 'border-color': '#D4AF37', 'background': '#fef8e0' });
        });
        $dropZone.on('dragleave drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css({ 'border-color': '#c3c4c7', 'background': '#fafafa' });
        });
        $dropZone.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                bvQTDocUploadFile(files[0]);
            }
        });

        // File input change
        $('#bv-qt-doc-upload-input').on('change', function() {
            if (this.files && this.files[0]) {
                bvQTDocUploadFile(this.files[0]);
                this.value = '';
            }
        });

        // Upload file and parse
        function bvQTDocUploadFile(file) {
            var ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'pdf' && ext !== 'docx') {
                alert('Please upload a PDF or .docx file.\n\nGot: .' + ext);
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                alert('File is too large. Maximum size is 10MB.\n\nYour file: ' + (file.size / 1024 / 1024).toFixed(1) + 'MB');
                return;
            }

            bvQTDocShowStep('parsing');

            var fd = new FormData();
            fd.append('file', file);
            fd.append('action', 'bv_qt_parse_document');
            fd.append('nonce', BVQT.nonce);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 120000,
                success: function(res) {
                    if (res.success) {
                        bvQTDocParsedData = res.data.data;
                        bvQTDocRenderPreview(res.data.data);
                        bvQTDocShowStep('review');
                    } else {
                        bvQTDocShowStep('upload');
                        var errMsg = res.data && res.data.message ? res.data.message : 'Unknown error parsing document.';
                        var tips = '\n\nTips:\n• PDF must contain selectable text (not scanned images)\n• .docx files must be saved in Word format (not .doc)\n• This plugin requires PHP 7.4 or higher\n• Try re-saving the file and uploading again.';
                        alert('Document Import Error:\n\n' + errMsg + tips);
                    }
                },
                error: function(xhr, status, error) {
                    bvQTDocShowStep('upload');
                    var detail = '';
                    try {
                        if (xhr.responseText) {
                            var resp = xhr.responseText;
                            var phpErr = resp.match(/<b>(?:Fatal error|Warning|Parse error|Error)<\/b>:\s*(.+?)(?:<br|<br\/|<\/b>)/);
                            if (phpErr) {
                                detail = '\n\nServer error: ' + phpErr[1].trim();
                            } else if (resp.length < 500) {
                                detail = '\n\nServer response: ' + resp.substring(0, 300);
                            } else {
                                detail = '\n\nHTTP Status: ' + xhr.status + ' ' + (error || status || 'error');
                            }
                        } else {
                            detail = '\n\nHTTP Status: ' + xhr.status + ' ' + (error || status || 'error');
                        }
                    } catch(e) {
                        detail = '\n\nError: ' + (error || status || 'unknown');
                    }
                    alert('Upload failed: ' + (error || status || 'Network error') + detail);
                }
            });
        }

        // Render the preview step with editable sections/questions
        function bvQTDocRenderPreview(data) {
            $('#bv-qt-doc-name').val(data.name || '');
            $('#bv-qt-doc-desc').val(data.description || '');

            var formatLabel = (data.format || 'unknown').toUpperCase();
            var statsHtml = ''
                + '<div class="bv-qt-stat" style="min-width:100px;"><div class="bv-qt-stat-num">' + formatLabel + '</div><div class="bv-qt-stat-label">Format</div></div>'
                + '<div class="bv-qt-stat" style="min-width:100px;"><div class="bv-qt-stat-num">' + (data.total_sections || 0) + '</div><div class="bv-qt-stat-label">Sections</div></div>'
                + '<div class="bv-qt-stat" style="min-width:100px;"><div class="bv-qt-stat-num">' + (data.total_questions || 0) + '</div><div class="bv-qt-stat-label">Questions</div></div>';
            $('#bv-qt-doc-stats').html(statsHtml);

            var html = '';
            $.each(data.sections || [], function(si, section) {
                html += '<div class="bv-qt-doc-section" data-section-idx="' + si + '" style="border-bottom:1px solid #e0e0e0;">';
                html += '<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#f6f7f7;cursor:pointer;" onclick="bvQTDocToggleSection(' + si + ')">';
                html += '<span class="dashicons dashicons-arrow-down-alt2 bv-qt-doc-section-arrow" id="bv-qt-doc-arrow-' + si + '" style="font-size:18px;transition:transform 0.2s;"></span>';
                html += '<strong style="flex:1;">' + escHtml(section.title || 'Untitled Section') + '</strong>';
                html += '<span class="bv-qt-meta">' + (section.questions ? section.questions.length : 0) + ' questions</span>';
                html += '</div>';
                html += '<div class="bv-qt-doc-section-body" id="bv-qt-doc-section-' + si + '" style="padding:0 16px 12px;">';
                html += '<div style="margin:8px 0;"><input type="text" class="large-text bv-qt-doc-sec-title" data-section-idx="' + si + '" value="' + escAttr(section.title || '') + '" placeholder="Section title" style="font-size:13px;" /></div>';

                $.each(section.questions || [], function(qi, q) {
                    var typeColor = bvDocTypeColors[q.type] || '#646970';
                    var typeLabel = bvDocTypeLabels[q.type] || q.type;
                    html += '<div class="bv-qt-doc-question" style="display:flex;gap:8px;align-items:flex-start;padding:8px 0;border-top:1px dashed #e0e0e0;flex-wrap:wrap;" data-section-idx="' + si + '" data-question-idx="' + qi + '">';
                    html += '<span style="color:#646970;font-size:12px;min-width:24px;text-align:right;margin-top:2px;">' + (qi + 1) + '.</span>';
                    html += '<div style="flex:1;min-width:200px;">';
                    html += '<input type="text" class="large-text bv-qt-doc-q-label" data-s="' + si + '" data-q="' + qi + '" value="' + escAttr(q.label || '') + '" placeholder="Question label" style="font-size:13px;margin-bottom:4px;" />';
                    if (q.options && q.options.length > 0) {
                        html += '<div style="font-size:12px;color:#646970;margin-left:4px;">';
                        html += 'Options: ';
                        $.each(q.options, function(oi, opt) {
                            html += '<span style="background:#e8e8e8;padding:1px 6px;border-radius:8px;margin-right:4px;">' + escHtml(opt.label) + '</span>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<select class="bv-qt-doc-q-type" data-s="' + si + '" data-q="' + qi + '" style="width:120px;padding:2px 4px;font-size:12px;border:1px solid #8c8f94;border-radius:4px;">';
                    $.each(bvDocTypeLabels, function(val, label) {
                        html += '<option value="' + val + '"' + (q.type === val ? ' selected' : '') + '>' + label + '</option>';
                    });
                    html += '</select>';
                    html += '<span style="font-size:11px;padding:2px 8px;border-radius:10px;color:#fff;background:' + typeColor + ';white-space:nowrap;">' + typeLabel + '</span>';
                    html += '<button type="button" class="button bv-qt-doc-q-delete" data-s="' + si + '" data-q="' + qi + '" style="font-size:11px;padding:1px 6px;height:auto;line-height:1.5;" title="Remove question"><span class="dashicons dashicons-trash" style="font-size:14px;margin:0;"></span></button>';
                    html += '</div>';
                });

                html += '<div style="padding-top:8px;"><button type="button" class="button button-small bv-qt-doc-add-q" data-section-idx="' + si + '" style="font-size:11px;">+ Add Question</button></div>';

                html += '</div>';
                html += '</div>';
            });

            html += '<div style="padding:12px 16px;text-align:center;border-top:2px solid #D4AF37;">';
            html += '<button type="button" class="button" id="bv-qt-doc-add-section-btn" style="font-size:13px;"><span class="dashicons dashicons-plus-alt2" style="margin-top:3px;margin-right:3px;"></span> Add Section</button>';
            html += '</div>';

            $('#bv-qt-doc-sections').html(html);

            bvQTDocBindPreviewEvents();
        }

        // Bind all interactive events in the preview
        function bvQTDocBindPreviewEvents() {
            window.bvQTDocToggleSection = function(idx) {
                var $body = $('#bv-qt-doc-section-' + idx);
                var $arrow = $('#bv-qt-doc-arrow-' + idx);
                $body.slideToggle(200);
                if ($body.is(':visible')) {
                    $arrow.css('transform', 'rotate(0deg)');
                } else {
                    $arrow.css('transform', 'rotate(-90deg)');
                }
            };

            $(document).off('change.bvDocType').on('change.bvDocType', '.bv-qt-doc-q-type', function() {
                var $sel = $(this);
                var newType = $sel.val();
                var $badge = $sel.next('span');
                $badge.text(bvDocTypeLabels[newType] || newType);
                $badge.css('background', bvDocTypeColors[newType] || '#646970');
            });

            $(document).off('click.bvDocDelQ').on('click.bvDocDelQ', '.bv-qt-doc-q-delete', function() {
                var si = $(this).data('s');
                var qi = $(this).data('q');
                $(this).closest('.bv-qt-doc-question').fadeOut(150, function() { $(this).remove(); });
                if (bvQTDocParsedData && bvQTDocParsedData.sections[si]) {
                    bvQTDocParsedData.sections[si].questions.splice(qi, 1);
                }
            });

            $(document).off('click.bvDocAddQ').on('click.bvDocAddQ', '.bv-qt-doc-add-q', function() {
                var si = $(this).data('section-idx');
                var qCount = $('#bv-qt-doc-section-' + si + ' .bv-qt-doc-question').length;
                var html = '<div class="bv-qt-doc-question" style="display:flex;gap:8px;align-items:flex-start;padding:8px 0;border-top:1px dashed #e0e0e0;flex-wrap:wrap;" data-section-idx="' + si + '" data-question-idx="' + qCount + '">'
                    + '<span style="color:#646970;font-size:12px;min-width:24px;text-align:right;margin-top:2px;">' + (qCount + 1) + '.</span>'
                    + '<div style="flex:1;min-width:200px;"><input type="text" class="large-text bv-qt-doc-q-label" data-s="' + si + '" data-q="' + qCount + '" value="" placeholder="Enter question label" style="font-size:13px;margin-bottom:4px;" /></div>'
                    + '<select class="bv-qt-doc-q-type" data-s="' + si + '" data-q="' + qCount + '" style="width:120px;padding:2px 4px;font-size:12px;border:1px solid #8c8f94;border-radius:4px;">';
                $.each(bvDocTypeLabels, function(val, label) {
                    html += '<option value="' + val + '">' + label + '</option>';
                });
                html += '</select><span style="font-size:11px;padding:2px 8px;border-radius:10px;color:#fff;background:#2271b1;white-space:nowrap;">Text</span>'
                    + '<button type="button" class="button bv-qt-doc-q-delete" data-s="' + si + '" data-q="' + qCount + '" style="font-size:11px;padding:1px 6px;height:auto;line-height:1.5;" title="Remove question"><span class="dashicons dashicons-trash" style="font-size:14px;margin:0;"></span></button>'
                    + '</div>';
                $('#bv-qt-doc-section-' + si).find('.bv-qt-doc-add-q').parent().before(html);
                if (bvQTDocParsedData && bvQTDocParsedData.sections[si]) {
                    bvQTDocParsedData.sections[si].questions.push({
                        type: 'text', label: '', placeholder: '', required: true, help_text: '', options: []
                    });
                }
            });

            $('#bv-qt-doc-add-section-btn').off('click').on('click', function() {
                var si = bvQTDocParsedData ? bvQTDocParsedData.sections.length : 0;
                var html = '<div class="bv-qt-doc-section" data-section-idx="' + si + '" style="border-bottom:1px solid #e0e0e0;">'
                    + '<div style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#f6f7f7;cursor:pointer;" onclick="bvQTDocToggleSection(' + si + ')">'
                    + '<span class="dashicons dashicons-arrow-down-alt2 bv-qt-doc-section-arrow" id="bv-qt-doc-arrow-' + si + '" style="font-size:18px;transition:transform 0.2s;"></span>'
                    + '<strong style="flex:1;">New Section</strong>'
                    + '<span class="bv-qt-meta">0 questions</span>'
                    + '</div>'
                    + '<div class="bv-qt-doc-section-body" id="bv-qt-doc-section-' + si + '" style="padding:0 16px 12px;">'
                    + '<div style="margin:8px 0;"><input type="text" class="large-text bv-qt-doc-sec-title" data-section-idx="' + si + '" value="" placeholder="Section title" style="font-size:13px;" /></div>'
                    + '<div style="padding-top:8px;"><button type="button" class="button button-small bv-qt-doc-add-q" data-section-idx="' + si + '" style="font-size:11px;">+ Add Question</button></div>'
                    + '</div></div>';
                $(this).parent().before(html);
                if (bvQTDocParsedData) {
                    bvQTDocParsedData.sections.push({ title: '', description: '', questions: [] });
                }
            });
        }

        // Collect data from the preview and send to import
        $('#bv-qt-doc-confirm-btn').on('click', function() {
            var $btn = $(this).prop('disabled', true).html('<span class="spinner is-active" style="margin:0 4px;"></span> Importing...');

            var templateData = {
                name: $('#bv-qt-doc-name').val().trim(),
                slug: '',
                description: $('#bv-qt-doc-desc').val().trim(),
                version: '1.0',
                status: 'draft',
                sections: []
            };

            if (!templateData.name) {
                alert('Please enter a template name.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Questionnaire');
                return;
            }

            $('.bv-qt-doc-section').each(function(si) {
                var $section = $(this);
                var secTitle = $section.find('.bv-qt-doc-sec-title').val().trim();
                var questions = [];

                $section.find('.bv-qt-doc-question').each(function(qi) {
                    var $q = $(this);
                    var qLabel = $q.find('.bv-qt-doc-q-label').val().trim();
                    var qType = $q.find('.bv-qt-doc-q-type').val();

                    var qOptions = [];
                    if (bvQTDocParsedData && bvQTDocParsedData.sections[si] && bvQTDocParsedData.sections[si].questions[qi]) {
                        qOptions = bvQTDocParsedData.sections[si].questions[qi].options || [];
                    }

                    questions.push({
                        type: qType,
                        label: qLabel,
                        placeholder: '',
                        required: true,
                        help_text: '',
                        options: qOptions
                    });
                });

                templateData.sections.push({
                    title: secTitle,
                    description: '',
                    order: si + 1,
                    questions: questions
                });
            });

            var totalQ = 0;
            $.each(templateData.sections, function(i, s) { totalQ += s.questions.length; });
            if (totalQ === 0) {
                alert('Please add at least one question. You can add questions to each section using the "+ Add Question" button.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Questionnaire');
                return;
            }

            templateData.sections = $.grep(templateData.sections, function(s) {
                return s.title !== '';
            });

            if (templateData.sections.length === 0) {
                alert('All sections have empty titles. Please name at least one section.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Questionnaire');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bv_qt_import_document',
                    nonce: BVQT.nonce,
                    template_data: JSON.stringify(templateData)
                },
                success: function(res) {
                    if (res.success) {
                        var msg = 'Questionnaire "' + escHtml(templateData.name) + '" imported successfully!\n\n'
                            + '• ' + res.data.sections + ' sections\n'
                            + '• ' + res.data.questions + ' questions\n\n'
                            + 'The template is saved as "Draft". You can edit it from the templates list.';
                        bvQTDocCloseModal();
                        alert(msg);
                        loadTemplates();
                    } else {
                        var errMsg = res.data && res.data.message ? res.data.message : 'Unknown error';
                        alert('Import failed: ' + errMsg);
                    }
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Questionnaire');
                },
                error: function(xhr, status, error) {
                    alert('Import failed: ' + (error || status || 'Network error'));
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top:4px;margin-right:3px;"></span> Import Questionnaire');
                }
            });
        });

    });

})(jQuery);
