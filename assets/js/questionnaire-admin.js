/**
 * BusinessVance Questionnaire Admin — JavaScript
 * @version 2.7.19
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

    // Show an error notice that auto-dismisses
    function bvQTShowError(msg) {
        $('#bv-qt-notices').html('<div class="notice notice-error is-dismissible" style="margin:5px 0"><p>' + msg + '</p></div>');
        setTimeout(function(){ $('#bv-qt-notices').empty(); }, 5000);
    }

    // ── Loading Helpers ──

    // Add/remove inline spinner from a button + disable/enable
    function bvQTBtnLoading($btn, loading, text) {
        if (loading) {
            if (!$btn.data('bv-original-html')) {
                $btn.data('bv-original-html', $btn.html());
            }
            $btn.addClass('bv-qt-btn-loading').prop('disabled', true);
            var label = text || $btn.data('bv-original-text') || $btn.text().trim();
            $btn.data('bv-original-text', label);
            $btn.html('<span class="bv-qt-btn-spinner"></span> ' + escHtml(label) + '…');
        } else {
            $btn.removeClass('bv-qt-btn-loading').prop('disabled', false);
            var origHtml = $btn.data('bv-original-html');
            if (origHtml) {
                $btn.html(origHtml);
                $btn.removeData('bv-original-html');
                $btn.removeData('bv-original-text');
            }
        }
    }

    // Show/hide a loading overlay on a container element
    function bvQTOverlay($el, show) {
        if (show) {
            // Remove any existing overlay first
            $el.find('.bv-qt-loading-overlay').remove();
            $el.css('position', 'relative').append('<div class="bv-qt-loading-overlay"><span class="spinner is-active" style="float:none;"></span></div>');
        } else {
            $el.find('.bv-qt-loading-overlay').fadeOut(150, function() { $(this).remove(); });
        }
    }

    // Build type options HTML for a select dropdown (reused in edit form)
    function bvQTTypeOptionsHtml(selectedType) {
        var t = selectedType || 'text';
        var h = '<optgroup label="Basic Inputs">'
            + '<option value="text"' + (t==='text'?' selected':'') + '>Text</option>'
            + '<option value="textarea"' + (t==='textarea'?' selected':'') + '>Textarea</option>'
            + '<option value="number"' + (t==='number'?' selected':'') + '>Number</option>'
            + '<option value="email"' + (t==='email'?' selected':'') + '>Email</option>'
            + '<option value="phone"' + (t==='phone'?' selected':'') + '>Phone</option>'
            + '<option value="url"' + (t==='url'?' selected':'') + '>URL</option>'
            + '<option value="date"' + (t==='date'?' selected':'') + '>Date</option>'
            + '<option value="time"' + (t==='time'?' selected':'') + '>Time</option>'
            + '</optgroup>'
            + '<optgroup label="Selection">'
            + '<option value="select"' + (t==='select'?' selected':'') + '>Select (Dropdown)</option>'
            + '<option value="radio"' + (t==='radio'?' selected':'') + '>Radio Buttons</option>'
            + '<option value="checkbox"' + (t==='checkbox'?' selected':'') + '>Checkboxes</option>'
            + '</optgroup>'
            + '<optgroup label="Specialized">'
            + '<option value="range"' + (t==='range'?' selected':'') + '>Range / Slider</option>'
            + '<option value="color"' + (t==='color'?' selected':'') + '>Color Picker</option>'
            + '<option value="rating"' + (t==='rating'?' selected':'') + '>Star Rating</option>'
            + '<option value="address"' + (t==='address'?' selected':'') + '>Address Block</option>'
            + '<option value="repeatable"' + (t==='repeatable'?' selected':'') + '>Repeatable Table</option>'
            + '</optgroup>'
            + '<optgroup label="Rich Content">'
            + '<option value="wysiwyg"' + (t==='wysiwyg'?' selected':'') + '>Rich Text Editor</option>'
            + '<option value="file"' + (t==='file'?' selected':'') + '>File Upload</option>'
            + '</optgroup>'
            + '<optgroup label="Display Only">'
            + '<option value="heading"' + (t==='heading'?' selected':'') + '>Heading</option>'
            + '<option value="paragraph"' + (t==='paragraph'?' selected':'') + '>Paragraph</option>'
            + '</optgroup>';
        return h;
    }

    // Build options presets HTML
    function bvQTPresetsHtml(cssClass) {
        var cls = cssClass || 'bv-qt-preset-btn';
        return '<span class="bv-qt-opts-presets-label">Quick fill:</span>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="yes_no">Yes / No</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="yes_no_other">Yes / No / Other</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="true_false">True / False</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="agree5">Likert 5</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="satisfaction">Satisfaction</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="rating5">Rating 1-5</button>'
            + '<button type="button" class="button button-small ' + cls + '" data-preset="rating10">Rating 1-10</button>';
    }

    // ── Options Presets ──
    var bvQTPresets = {
        yes_no:      'Yes\nNo',
        yes_no_other: 'Yes\nNo\n__other__|Other',
        true_false:  'True\nFalse',
        agree5:      'Strongly Agree\nAgree\nNeutral\nDisagree\nStrongly Disagree',
        satisfaction: 'Very Satisfied\nSatisfied\nNeutral\nDissatisfied\nVery Dissatisfied',
        rating5:     '1\n2\n3\n4\n5',
        rating10:    '1\n2\n3\n4\n5\n6\n7\n8\n9\n10'
    };

    // Auto-fill option values: if a line has no "|", prepend "slugified_label|"
    // Exception: __other__ values pass through as-is
    function bvQTAutoFillOptions(text) {
        if (!text) return '';
        return text.split('\n').map(function(line) {
            line = line.trim();
            if (!line) return '';
            // Already has value|label format — leave as-is
            if (line.indexOf('|') !== -1) return line;
            // __other__ magic value — preserve as-is
            if (line === '__other__') return '__other__|Other';
            // Auto-generate value from label
            return slugify(line) + '|' + line;
        }).filter(function(l) { return l !== ''; }).join('\n');
    }

    // Process options text: auto-fill values then pass to backend
    function bvQTProcessOptionsText(text) {
        return bvQTAutoFillOptions(text);
    }

    // ── Smart type defaults ──
    var bvQTTypeDefaults = {
        'text':     { placeholder: 'Enter your answer' },
        'textarea': { placeholder: 'Please describe in detail...' },
        'number':   { placeholder: 'Enter a number' },
        'email':    { placeholder: 'email@example.com' },
        'phone':    { placeholder: '+27 ' },
        'url':      { placeholder: 'https://example.com' },
        'date':     { placeholder: '' },
        'time':     { placeholder: '' },
        'file':     { placeholder: '', help_text: 'Accepted formats: PDF, DOC, JPG, PNG (max 10MB)' },
        'range':    { placeholder: '', help_text: 'Set options: min|max|step (e.g. 1|10|1)' },
        'rating':   { placeholder: '', help_text: 'Set options: number of stars (e.g. 5)' },
        'color':    { placeholder: '', help_text: 'Choose a color' },
        'address':  { placeholder: '' },
        'repeatable': { placeholder: '', help_text: 'Set options: column names, one per line' },
        'wysiwyg':  { placeholder: '' }
    };

    function bvQTApplyTypeDefaults($typeSelect, $placeholder, $helpText) {
        var type = $typeSelect.val();
        var defaults = bvQTTypeDefaults[type] || {};
        if (defaults.placeholder !== undefined && !$placeholder.data('user-edited')) {
            $placeholder.val(defaults.placeholder);
        }
        if (defaults.help_text !== undefined && !$helpText.data('user-edited')) {
            $helpText.val(defaults.help_text);
        }
    }

    // ── Field type visibility helpers ──
    function bvQTNeedsOptions(type) {
        return (type === 'select' || type === 'radio' || type === 'checkbox');
    }
    function bvQTNeedsColumns(type) {
        return type === 'repeatable';
    }
    function bvQTNeedsRange(type) {
        return type === 'range';
    }
    function bvQTNeedsRating(type) {
        return type === 'rating';
    }
    function bvQTNeedsPlaceholder(type) {
        return !(type === 'heading' || type === 'paragraph' || type === 'checkbox' || type === 'color' || type === 'rating' || type === 'address' || type === 'wysiwyg' || type === 'range' || type === 'time' || type === 'date' || type === 'file');
    }

    // ── Column Builder (Repeatable Table) ──

    // Column type options for the dropdown
    var bvQTColTypes = {
        'text':   { label: 'Text',   icon: ' short_before_widget' },
        'number': { label: 'Number', icon: '' },
        'email':  { label: 'Email',  icon: '' },
        'phone':  { label: 'Phone',  icon: '' },
        'date':   { label: 'Date',   icon: '' },
        'url':    { label: 'URL',    icon: '' },
        'select': { label: 'Select', icon: '' }
    };

    // Column presets: array of {type, name}
    var bvQTColPresets = {
        contact: [
            { type: 'text', name: 'Full Name' },
            { type: 'email', name: 'Email Address' },
            { type: 'phone', name: 'Phone Number' },
            { type: 'text', name: 'Company' }
        ],
        line_items: [
            { type: 'text', name: 'Item Description' },
            { type: 'number', name: 'Quantity' },
            { type: 'number', name: 'Unit Price' }
        ],
        references: [
            { type: 'text', name: 'Full Name' },
            { type: 'text', name: 'Relationship' },
            { type: 'email', name: 'Email' },
            { type: 'phone', name: 'Phone' }
        ],
        address_book: [
            { type: 'text', name: 'Full Name' },
            { type: 'text', name: 'Street Address' },
            { type: 'text', name: 'City' },
            { type: 'text', name: 'Province/State' },
            { type: 'text', name: 'Postal Code' },
            { type: 'text', name: 'Country' }
        ],
        education: [
            { type: 'text', name: 'Institution' },
            { type: 'text', name: 'Qualification/Degree' },
            { type: 'date', name: 'Start Date' },
            { type: 'date', name: 'End Date' }
        ]
    };

    // Build HTML for a single column row in the builder
    function bvQTColItemHtml(name, type) {
        name = name || '';
        type = type || 'text';
        var typeOpts = '';
        $.each(bvQTColTypes, function(t, info) {
            typeOpts += '<option value="' + t + '"' + (t === type ? ' selected' : '') + '>' + escHtml(info.label) + '</option>';
        });
        return '<div class="bv-qt-col-item">'
            + '<span class="bv-qt-col-drag" title="Drag to reorder">≡</span>'
            + '<input type="text" class="bv-qt-col-name regular-text" placeholder="Column name" value="' + escAttr(name) + '" />'
            + '<select class="bv-qt-col-type">' + typeOpts + '</select>'
            + '<button type="button" class="button button-small bv-qt-col-remove" title="Remove column">&times;</button>'
            + '</div>';
    }

    // Update the table preview from column items
    function bvQTUpdateTablePreview($builder) {
        var $thead = $builder.find('.bv-qt-preview-thead tr').empty();
        var $tbody = $builder.find('.bv-qt-preview-tbody tr').empty();
        var colCount = 0;
        $builder.find('.bv-qt-col-item').each(function() {
            var name = $(this).find('.bv-qt-col-name').val().trim() || 'Column';
            $thead.append('<th>' + escHtml(name) + '</th>');
            $tbody.append('<td><input type="text" disabled placeholder="…" class="bv-qt-preview-input" /></td>');
            colCount++;
        });
        if (colCount > 0) {
            $thead.append('<th class="bv-qt-preview-action-col"></th>');
            $tbody.append('<td class="bv-qt-preview-action-col"><button type="button" disabled class="button button-small" title="Remove row">&times;</button></td>');
        }
        $builder.find('.bv-qt-table-preview').toggle(colCount > 0);
    }

    // Load columns from options array (used when editing an existing question)
    function bvQTLoadColumns($builder, options) {
        var $list = $builder.find('.bv-qt-col-list').empty();
        if (!options || !Array.isArray(options) || options.length === 0) return;
        $.each(options, function(i, o) {
            var type, name;
            if (typeof o === 'object') {
                type = o.value || 'text';
                name = o.label || '';
            } else {
                type = 'text';
                name = String(o);
            }
            $list.append(bvQTColItemHtml(name, type));
        });
        bvQTUpdateTablePreview($builder);
    }

    // Get columns as pipe-delimited text for saving to backend
    function bvQTGetColumnsText($builder) {
        var lines = [];
        $builder.find('.bv-qt-col-item').each(function() {
            var type = $(this).find('.bv-qt-col-type').val();
            var name = $(this).find('.bv-qt-col-name').val().trim();
            if (name) {
                lines.push(type + '|' + name);
            }
        });
        return lines.join('\n');
    }

    // Build options preview for repeatable type in the questions list
    function bvQTRepeatableOptsPreview(q) {
        if (!q.options || !Array.isArray(q.options) || q.options.length === 0) return '';
        var names = [];
        $.each(q.options, function(i, o) {
            if (typeof o === 'object') {
                names.push(o.label || '');
            } else {
                names.push(String(o));
            }
        });
        names = names.filter(function(n) { return n !== ''; });
        if (names.length === 0) return '';
        return ' <span class="bv-qt-opts-preview">(' + escHtml(names.join(' · ')) + ')</span>';
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

    // Build star HTML for rating preview
    function bvQTRatingStarsHtml(count) {
        var html = '';
        for (var i = 0; i < Math.min(Math.max(count, 1), 10); i++) {
            html += '&#9733;';
        }
        return html;
    }

    // ── Template List ──
    function loadTemplates() {
        var $tbody = $('#bv-qt-templates-body');
        $tbody.html('<tr><td colspan="7" style="text-align:center;padding:40px;"><span class="spinner is-active" style="float:none;"></span><br>Loading templates...</td></tr>');
        $.post(ajaxurl, { action: 'bv_qt_get_templates', nonce: BVQT.nonce }, function(res) {
            if (!res.success) {
                $tbody.html(
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
            $tbody.html(rows);
        }).fail(function(xhr, status, error) {
            $tbody.html(
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

        // Show loading overlay
        bvQTOverlay($('#bv-qt-edit-view'), true);

        $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: id }, function(res) {
            bvQTOverlay($('#bv-qt-edit-view'), false);
            if (!res.success) return;
            var t = res.data.template;
            $('#bv-qt-edit-id').val(t.id);
            $('#bv-qt-tpl-name').val(t.name);
            $('#bv-qt-tpl-slug').val(t.slug);
            $('#bv-qt-tpl-description').val(t.description);
            $('#bv-qt-tpl-status').val(t.status);
            $('#bv-qt-edit-title').text('Edit Template: ' + t.name);
            renderSections(t.sections || []);
        }).fail(function() {
            bvQTOverlay($('#bv-qt-edit-view'), false);
            bvQTShowError('Failed to load template.');
        });
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
            var headerInner = '<span class="bv-qt-section-drag-handle" title="Drag to reorder section"><span class="dashicons dashicons-menu"></span></span>'
                + '<span class="bv-qt-section-num-badge">' + (i + 1) + '</span>'
                + '<div style="flex:1;min-width:0;">'
                +   '<strong>' + escHtml(s.title) + '</strong>'
                +   (sectionDesc ? '<div class="bv-qt-section-desc">' + escHtml(sectionDesc) + '</div>' : '')
                + '</div>'
                + '<span class="bv-qt-meta">' + questions.length + ' question' + (questions.length !== 1 ? 's' : '') + '</span>'
                + '<span class="bv-qt-section-actions">'
                +   '<button class="button button-small bv-qt-toggle-section" data-sid="' + s.id + '">Collapse</button> '
                +   '<button class="button button-small bv-qt-move-up bv-qt-mobile-only" data-sid="' + s.id + '" title="Move up">↑</button> '
                +   '<button class="button button-small bv-qt-move-down bv-qt-mobile-only" data-sid="' + s.id + '" title="Move down">↓</button> '
                +   '<button class="button button-small bv-qt-edit-section" data-sid="' + s.id + '">Edit</button> '
                +   '<button class="button button-small bv-qt-del-section" data-sid="' + s.id + '" style="color:#a00;">Delete</button>'
                + '</span>';
            header.html(headerInner);
            li.append(header);

            // Questions list — EXPANDED by default (no display:none)
            var qList = $('<ol class="bv-qt-questions-list"></ol>');
            $.each(questions, function(j, q) {
                var reqBadge = q.is_required == 1 ? ' <span class="bv-qt-req-badge">*required</span>' : '';
                var optsPreview = '';
                if (q.type === 'radio' || q.type === 'select' || q.type === 'checkbox') {
                    optsPreview = bvQTOptsPreview(q);
                } else if (q.type === 'repeatable') {
                    optsPreview = bvQTRepeatableOptsPreview(q);
                } else if (q.type === 'range') {
                    var rn = q.options || [];
                    optsPreview = ' <span class="bv-qt-opts-preview">(' + escHtml((rn[0] || '0') + ' → ' + (rn[1] || '100') + (rn[2] && rn[2] !== '1' ? ', step ' + rn[2] : '')) + ')</span>';
                } else if (q.type === 'rating') {
                    var rs = q.options || [];
                    optsPreview = ' <span class="bv-qt-opts-preview">(' + escHtml((rs[0] || '5') + ' stars') + ')</span>';
                }
                var subInfo = '';
                var subParts = [];
                if (q.placeholder) subParts.push('placeholder: ' + escHtml(q.placeholder));
                if (q.help_text) subParts.push(escHtml(q.help_text));
                if (subParts.length > 0) {
                    subInfo = '<div class="bv-qt-q-sub-info">' + subParts.join(' &middot; ') + '</div>';
                }

                var qli = $('<li data-qid="' + q.id + '"></li>');
                qli.html('<span class="bv-qt-q-drag-handle" title="Drag to reorder or move to another section"><span class="dashicons dashicons-menu"></span></span>'
                    + '<div class="bv-qt-q-main-info">'
                    +   (j + 1) + '. <strong>' + escHtml(q.label) + '</strong> '
                    +   bvQTTypeBadge(q.type)
                    +   reqBadge
                    +   optsPreview
                    + '</div>'
                    + subInfo
                    + ' <span class="bv-qt-q-actions">'
                    +   '<button class="button button-small bv-qt-move-q-up bv-qt-mobile-only" data-qid="' + q.id + '" data-sid="' + s.id + '" title="Move up">↑</button> '
                    +   '<button class="button button-small bv-qt-move-q-down bv-qt-mobile-only" data-qid="' + q.id + '" data-sid="' + s.id + '" title="Move down">↓</button> '
                    +   '<button class="button button-small bv-qt-dup-q" data-qid="' + q.id + '" data-sid="' + s.id + '" title="Duplicate">⧉</button> '
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
        // Show drag-and-drop hint when sections exist
        if (sections.length > 0) {
            var hintHtml = '<div class="bv-qt-drag-hint">'
                + '<span class="dashicons dashicons-move" style="font-size:16px;margin-top:2px;color:#D4AF37;"></span> '
                + 'Drag the <strong>☰ handle</strong> to reorder questions and sections. Questions can be dragged between sections to move them.'
                + '</div>';
            var $secHeader = $('.bv-qt-sections-header');
            $secHeader.next('.bv-qt-drag-hint').remove();
            $secHeader.after(hintHtml);
        }
        // Initialize drag-and-drop sortable
        bvQTInitSortable();
    }

    // ── Drag & Drop Sortable ──

    function bvQTInitSortable() {
        // Destroy existing instances safely to prevent duplicate handlers
        try { $('.bv-qt-questions-list').sortable('destroy'); } catch(e) {}
        try { $('.bv-qt-sections-list').sortable('destroy'); } catch(e) {}

        if ($('#bv-qt-sections-list').length === 0) return;

        // ── Questions sortable — connected across ALL sections ──
        $('.bv-qt-questions-list').sortable({
            handle: '.bv-qt-q-drag-handle',
            connectWith: '.bv-qt-questions-list',
            items: 'li[data-qid]',
            placeholder: 'bv-qt-q-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            opacity: 0.7,
            revert: 120,
            zIndex: 9999,
            scrollSensitivity: 50,
            scrollSpeed: 20,
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
                ui.item.addClass('bv-qt-q-dragging');
                // Temporarily expand collapsed sections so they can accept drops
                $('.bv-qt-section-item').each(function() {
                    var $qList = $(this).find('.bv-qt-questions-list');
                    if (!$qList.is(':visible')) {
                        $(this).addClass('bv-qt-was-collapsed');
                        $qList.show().css({ minHeight: '50px', opacity: 0.5 });
                        $(this).find('.bv-qt-add-q-wrap').show();
                    }
                });
                // Highlight all sections as potential drop targets
                $('.bv-qt-section-item').addClass('bv-qt-section-drop-target');
            },
            stop: function(e, ui) {
                ui.item.removeClass('bv-qt-q-dragging');
                // Re-collapse sections that were temporarily expanded
                $('.bv-qt-was-collapsed').each(function() {
                    $(this).find('.bv-qt-questions-list').hide().css({ minHeight: '', opacity: '' });
                    $(this).find('.bv-qt-add-q-wrap').hide();
                    $(this).removeClass('bv-qt-was-collapsed');
                    $(this).find('.bv-qt-toggle-section').text('Expand');
                });
                // Remove drop target highlights
                $('.bv-qt-section-item').removeClass('bv-qt-section-drop-target bv-qt-section-drop-active');
            },
            over: function(e, ui) {
                $(this).closest('.bv-qt-section-item').addClass('bv-qt-section-drop-active');
            },
            out: function(e, ui) {
                $(this).closest('.bv-qt-section-item').removeClass('bv-qt-section-drop-active');
            },
            receive: function(e, ui) {
                // Question dropped into a DIFFERENT section
                var $destList = $(this);
                var $srcList = ui.sender;
                var qid = ui.item.data('qid');
                var newSid = $destList.closest('.bv-qt-section-item').data('section-id');
                var $destSection = $destList.closest('.bv-qt-section-item');

                // Mark both lists so update handler doesn't double-process
                $destList.data('bv-cross-move', true);
                $srcList.data('bv-cross-move', true);

                // Don't re-collapse the destination section (keep expanded so user sees result)
                $destSection.removeClass('bv-qt-was-collapsed');

                // Update question's section_id in the database
                $.post(ajaxurl, {
                    action: 'bv_qt_move_question',
                    nonce: BVQT.nonce,
                    question_id: qid,
                    new_section_id: newSid
                }).done(function(res) {
                    if (res.success) {
                        // Reorder destination section questions
                        var destIds = [];
                        $destList.children('li[data-qid]').each(function() { destIds.push($(this).data('qid')); });
                        if (destIds.length > 0) {
                            $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'question', ids: destIds.join(',') });
                        }
                        // Reorder source section questions (item already removed from DOM)
                        var srcIds = [];
                        $srcList.children('li[data-qid]').each(function() { srcIds.push($(this).data('qid')); });
                        if (srcIds.length > 0) {
                            $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'question', ids: srcIds.join(',') });
                        }
                        bvQTShowNotice('Question moved to new section.');
                        // Re-render to update numbering and section counts
                        editTemplate(getTemplateId());
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Failed to move question.');
                        $srcList.append(ui.item);
                        editTemplate(getTemplateId());
                    }
                }).fail(function() {
                    alert('Network error moving question. Please try again.');
                    $srcList.append(ui.item);
                    editTemplate(getTemplateId());
                });
            },
            update: function(e, ui) {
                // Skip if this is part of a cross-section move (handled by receive)
                if ($(this).data('bv-cross-move')) {
                    $(this).removeData('bv-cross-move');
                    return;
                }
                // Same-section reorder
                var $list = $(this);
                var ids = [];
                $list.children('li[data-qid]').each(function() { ids.push($(this).data('qid')); });
                if (ids.length === 0) return;
                $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'question', ids: ids.join(',') })
                .done(function(res) {
                    if (res.success) {
                        editTemplate(getTemplateId());
                    }
                })
                .fail(function() {
                    alert('Reorder failed. Please try again.');
                    editTemplate(getTemplateId());
                });
            }
        });

        // ── Sections sortable ──
        $('.bv-qt-sections-list').sortable({
            handle: '.bv-qt-section-drag-handle',
            items: '.bv-qt-section-item',
            placeholder: 'bv-qt-section-sortable-placeholder',
            tolerance: 'pointer',
            cursor: 'grabbing',
            opacity: 0.85,
            revert: 120,
            cancel: '.bv-qt-questions-list, .bv-qt-add-q-wrap, .bv-qt-inline-section-form, .bv-qt-inline-q-form, .bv-qt-edit-q-form',
            start: function(e, ui) {
                ui.placeholder.height(ui.item.outerHeight());
                ui.item.addClass('bv-qt-section-dragging');
            },
            stop: function(e, ui) {
                ui.item.removeClass('bv-qt-section-dragging');
            },
            update: function() {
                var ids = [];
                $(this).children('.bv-qt-section-item').each(function() { ids.push($(this).data('section-id')); });
                if (ids.length === 0) return;
                $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'section', ids: ids.join(',') })
                .done(function(res) {
                    if (res.success) {
                        editTemplate(getTemplateId());
                    }
                })
                .fail(function() {
                    alert('Section reorder failed. Please try again.');
                    editTemplate(getTemplateId());
                });
            }
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
            var $btn = $(this);
            bvQTBtnLoading($btn, true, 'Duplicate');
            var id = $btn.data('id');
            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: id }, function(res) {
                if (!res.success) { bvQTBtnLoading($btn, false); return; }
                var t = res.data.template;
                $.post(ajaxurl, {
                    action: 'bv_qt_save_template', nonce: BVQT.nonce,
                    id: 0, name: t.name + ' (Copy)', slug: '', description: t.description, status: 'draft'
                }, function(res2) {
                    if (!res2.success) { alert('Duplicate failed'); bvQTBtnLoading($btn, false); return; }
                    var newId = res2.data.template_id;
                    var sections = t.sections || [];
                    var pending = sections.length;
                    if (pending === 0) { bvQTBtnLoading($btn, false); loadTemplates(); return; }
                    $.each(sections, function(i, s) {
                        $.post(ajaxurl, {
                            action: 'bv_qt_save_section', nonce: BVQT.nonce,
                            id: 0, template_id: newId, title: s.title, description: s.description, display_order: s.display_order
                        }, function(res3) {
                            var questions = s.questions || [];
                            var qPending = questions.length;
                            if (qPending === 0) { pending--; if(pending===0) { bvQTBtnLoading($btn, false); loadTemplates(); } return; }
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
                                }, function() { qPending--; if(qPending===0){ pending--; if(pending===0) { bvQTBtnLoading($btn, false); loadTemplates(); } } })
                                .fail(function() { qPending--; if(qPending===0){ pending--; if(pending===0) { bvQTBtnLoading($btn, false); loadTemplates(); } } alert('Failed to duplicate a question.'); });
                            });
                        }).fail(function() { pending--; if(pending===0) { bvQTBtnLoading($btn, false); loadTemplates(); } alert('Failed to duplicate a section.'); });
                    });
                }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to create duplicate template.'); });
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to load template for duplication.'); });
        });

        // Delete template
        $(document).on('click', '.bv-qt-delete', function() {
            if (!confirm('Delete this template and ALL its sections and questions?')) return;
            var $btn = $(this);
            bvQTBtnLoading($btn, true, 'Delete');
            $.post(ajaxurl, { action: 'bv_qt_delete_template', nonce: BVQT.nonce, id: $btn.data('id') }, function(res) {
                bvQTBtnLoading($btn, false);
                if (res.success) {
                    bvQTShowNotice('Template deleted successfully.');
                    loadTemplates();
                } else {
                    alert(res.data.message);
                    loadTemplates();
                }
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to delete template. Network error — please check your connection and try again.'); });
        });

        // Auto slug from name
        $('#bv-qt-tpl-name').on('input', function() {
            $('#bv-qt-tpl-slug').val(slugify($(this).val()));
        });

        // Save template
        $('#bv-qt-save-tpl-btn').on('click', function() {
            var $btn = $(this);
            var name = $('#bv-qt-tpl-name').val().trim();
            if (!name) { alert('Template name is required.'); return; }
            bvQTBtnLoading($btn, true, 'Save');
            $.post(ajaxurl, {
                action: 'bv_qt_save_template', nonce: BVQT.nonce,
                id: getTemplateId(),
                name: name,
                slug: $('#bv-qt-tpl-slug').val(),
                description: $('#bv-qt-tpl-description').val(),
                status: $('#bv-qt-tpl-status').val()
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Template saved successfully.');
                var newId = res.data.template_id;
                if (getTemplateId() === 0) {
                    $('#bv-qt-edit-view').data('template-id', newId);
                    $('#bv-qt-edit-title').text('Edit Template: ' + $('#bv-qt-tpl-name').val());
                }
                editTemplate(newId);
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to save template. Network error — please check your connection and try again.'); });
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
            var $btn = $(this);
            var title = $('#bv-qt-sec-title').val().trim();
            if (!title) { alert('Section title is required.'); return; }
            bvQTBtnLoading($btn, true, 'Save');
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: $('#bv-qt-sec-edit-id').val(),
                template_id: getTemplateId(),
                title: title,
                description: $('#bv-qt-sec-description').val(),
                display_order: 0
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Section saved successfully.');
                $('#bv-qt-section-form-wrap').hide();
                editTemplate(getTemplateId());
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to save section. Network error — please check your connection and try again.'); });
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
            var $btn = $(this);
            var sid = $btn.data('sid');
            var item = $btn.closest('.bv-qt-section-item');
            var newTitle = item.find('.bv-qt-inline-sec-title').val().trim();
            var newDesc = item.find('.bv-qt-inline-sec-desc').val();
            if (!newTitle) { alert('Section title is required.'); return; }
            bvQTBtnLoading($btn, true, 'Save');
            $.post(ajaxurl, {
                action: 'bv_qt_save_section', nonce: BVQT.nonce,
                id: sid, template_id: getTemplateId(),
                title: newTitle, description: newDesc, display_order: item.index()
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (res.success) {
                    bvQTShowNotice('Section updated successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to update section. Network error — please check your connection and try again.'); });
        });

        // Cancel inline section edit
        $(document).on('click', '.bv-qt-cancel-inline-sec', function() {
            $(this).closest('.bv-qt-inline-section-form').slideUp(150, function() { $(this).remove(); });
        });

        // Delete section
        $(document).on('click', '.bv-qt-del-section', function() {
            if (!confirm('Delete this section and all its questions?')) return;
            var $btn = $(this);
            bvQTBtnLoading($btn, true, 'Delete');
            $.post(ajaxurl, {
                action: 'bv_qt_delete_section', nonce: BVQT.nonce,
                id: $btn.data('sid')
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (res.success) {
                    bvQTShowNotice('Section deleted successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to delete section. Network error — please check your connection and try again.'); });
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
                alert('Section reorder failed. Reverting — please check your connection and try again.');
                if (dir === 'up') item.insertBefore($prev);
                else item.insertAfter($next);
            });
        });

        // ── Questions ──

        // Move question up/down within its section
        $(document).on('click', '.bv-qt-move-q-up, .bv-qt-move-q-down', function() {
            var $btn = $(this);
            var dir = $btn.hasClass('bv-qt-move-q-up') ? 'up' : 'down';
            var $li = $btn.closest('li[data-qid]');
            var $qList = $li.parent('.bv-qt-questions-list');

            if (dir === 'up' && $li.index() === 0) return;
            if (dir === 'down' && $li.index() === $qList.children('li').length - 1) return;

            var $prev = $li.prev();
            var $next = $li.next();

            // Visual swap
            if (dir === 'up') $prev.before($li);
            else $next.after($li);

            // Collect all question IDs in the current section in new order
            var ids = [];
            $qList.children('li[data-qid]').each(function() { ids.push($(this).data('qid')); });

            // Save new order to backend
            $.post(ajaxurl, { action: 'bv_qt_reorder', nonce: BVQT.nonce, type: 'question', ids: ids.join(',') })
            .done(function(res) {
                if (res.success) {
                    // Re-render to update numbering
                    editTemplate(getTemplateId());
                }
            })
            .fail(function() {
                alert('Question reorder failed. Reverting — please check your connection and try again.');
                if (dir === 'up') $li.insertBefore($prev);
                else $li.insertAfter($next);
            });
        });

        // Add question — INLINE form (reuses hidden template)
        $(document).on('click', '.bv-qt-add-q', function() {
            var $btn = $(this);
            var sid = $btn.data('sid');
            var sectionItem = $btn.closest('.bv-qt-section-item');

            // Remove any existing inline question forms
            $('.bv-qt-inline-q-form, .bv-qt-edit-q-form').slideUp(150, function() { $(this).remove(); });

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
                .replace(/id="bv-qt-q-opts-presets"/g, 'id="bvq-new-opts-presets"')
                .replace(/id="bv-qt-q-options"/g, 'id="bvq-new-options"')
                .replace(/id="bv-qt-q-cols-row"/g, 'id="bvq-new-cols-row" class="bv-qt-new-q-cols-row"')
                .replace(/id="bv-qt-q-col-builder"/g, 'id="bvq-new-col-builder"')
                .replace(/id="bv-qt-q-col-list"/g, 'id="bvq-new-col-list"')
                .replace(/id="bv-qt-q-col-add"/g, 'id="bvq-new-col-add"')
                .replace(/id="bv-qt-q-table-preview"/g, 'id="bvq-new-table-preview"')
                .replace(/id="bv-qt-q-preview-thead"/g, 'id="bvq-new-preview-thead"')
                .replace(/id="bv-qt-q-preview-tbody"/g, 'id="bvq-new-preview-tbody"')
                .replace(/id="bv-qt-q-range-row"/g, 'id="bvq-new-range-row" class="bv-qt-new-q-range-row"')
                .replace(/id="bv-qt-q-range-min"/g, 'id="bvq-new-range-min"')
                .replace(/id="bv-qt-q-range-max"/g, 'id="bvq-new-range-max"')
                .replace(/id="bv-qt-q-range-step"/g, 'id="bvq-new-range-step"')
                .replace(/id="bv-qt-q-rating-row"/g, 'id="bvq-new-rating-row" class="bv-qt-new-q-rating-row"')
                .replace(/id="bv-qt-q-rating-stars"/g, 'id="bvq-new-rating-stars"')
                .replace(/id="bv-qt-q-rating-preview"/g, 'id="bvq-new-rating-preview"')
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

            // Initial toggle for options/placeholder/columns/range/rating visibility
            var initType = wrapper.find('#bvq-new-type').val();
            wrapper.find('.bv-qt-new-q-options-row').toggle(bvQTNeedsOptions(initType));
            wrapper.find('.bv-qt-new-q-cols-row').toggle(bvQTNeedsColumns(initType));
            wrapper.find('.bv-qt-new-q-range-row').toggle(bvQTNeedsRange(initType));
            wrapper.find('.bv-qt-new-q-rating-row').toggle(bvQTNeedsRating(initType));
            wrapper.find('.bv-qt-new-q-placeholder-row').toggle(bvQTNeedsPlaceholder(initType));

            // Initialize column builder with default columns if repeatable
            if (bvQTNeedsColumns(initType)) {
                var $colBuilder = wrapper.find('#bvq-new-col-builder');
                $colBuilder.find('.bv-qt-col-list').append(bvQTColItemHtml('Column 1', 'text'));
                bvQTUpdateTablePreview($colBuilder);
            }

            // Mark placeholder/help as not user-edited initially
            wrapper.find('#bvq-new-placeholder').removeData('user-edited');
            wrapper.find('#bvq-new-help').removeData('user-edited');

            // Apply smart defaults for initial type
            var $phField = wrapper.find('#bvq-new-placeholder');
            var $helpField = wrapper.find('#bvq-new-help');
            bvQTApplyTypeDefaults(wrapper.find('#bvq-new-type'), $phField, $helpField);

            // Focus label field
            setTimeout(function() { wrapper.find('#bvq-new-label').focus(); }, 100);

            // Scroll to form
            wrapper[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        // Toggle options/placeholder/columns/range/rating visibility in new question form
        $(document).on('change', '.bv-qt-new-q-type-select', function() {
            var t = $(this).val();
            var $form = $(this).closest('.bv-qt-inline-q-form');
            $form.find('.bv-qt-new-q-options-row').toggle(bvQTNeedsOptions(t));
            $form.find('.bv-qt-new-q-cols-row').toggle(bvQTNeedsColumns(t));
            $form.find('.bv-qt-new-q-range-row').toggle(bvQTNeedsRange(t));
            $form.find('.bv-qt-new-q-rating-row').toggle(bvQTNeedsRating(t));
            $form.find('.bv-qt-new-q-placeholder-row').toggle(bvQTNeedsPlaceholder(t));
            // Apply smart defaults on type change
            bvQTApplyTypeDefaults($(this), $form.find('#bvq-new-placeholder'), $form.find('#bvq-new-help'));
            // Initialize column builder when switching to repeatable
            if (bvQTNeedsColumns(t)) {
                var $colBuilder = $form.find('#bvq-new-col-builder');
                if ($colBuilder.find('.bv-qt-col-item').length === 0) {
                    $colBuilder.find('.bv-qt-col-list').append(bvQTColItemHtml('Column 1', 'text'));
                    bvQTUpdateTablePreview($colBuilder);
                }
            }
        });

        // Save new question (from inline add form)
        $(document).on('click', '.bv-qt-save-new-q', function() {
            var $btn = $(this);
            var $form = $btn.closest('.bv-qt-inline-q-form');
            var sid = $form.data('section-id');
            var label = $form.find('#bvq-new-label').val().trim();
            if (!label) { alert('Question label is required.'); $form.find('#bvq-new-label').focus(); return; }
            bvQTBtnLoading($btn, true, 'Save');
            var type = $form.find('#bvq-new-type').val();
            var optionsText = '';
            if (bvQTNeedsOptions(type)) {
                optionsText = bvQTProcessOptionsText($form.find('#bvq-new-options').val());
            } else if (bvQTNeedsColumns(type)) {
                optionsText = bvQTGetColumnsText($form.find('#bvq-new-col-builder'));
            } else if (bvQTNeedsRange(type)) {
                var min = $form.find('#bvq-new-range-min').val() || '0';
                var max = $form.find('#bvq-new-range-max').val() || '100';
                var step = $form.find('#bvq-new-range-step').val() || '1';
                optionsText = min + '\n' + max + '\n' + step;
            } else if (bvQTNeedsRating(type)) {
                optionsText = $form.find('#bvq-new-rating-stars').val() || '5';
            }
            $.post(ajaxurl, {
                action: 'bv_qt_save_question', nonce: BVQT.nonce,
                id: 0, section_id: sid,
                type: type,
                label: label,
                placeholder: $form.find('#bvq-new-placeholder').val(),
                is_required: $form.find('#bvq-new-required').is(':checked') ? 1 : 0,
                help_text: $form.find('#bvq-new-help').val(),
                options_text: optionsText
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (!res.success) { alert(res.data.message); return; }
                bvQTShowNotice('Question added successfully.');
                editTemplate(getTemplateId());
            }).fail(function(xhr, status, error) { bvQTBtnLoading($btn, false); alert('Failed to add question. Network error (' + (status || 'unknown') + '). Please check your connection and try again.'); });
        });

        // Cancel new question form
        $(document).on('click', '.bv-qt-cancel-new-q', function() {
            $(this).closest('.bv-qt-inline-q-form').slideUp(150, function() { $(this).remove(); });
        });

        // Enter to save in new question form (from label or help field)
        $(document).on('keydown', '#bvq-new-label, #bvq-new-placeholder, #bvq-new-help', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $(this).closest('.bv-qt-inline-q-form').find('.bv-qt-save-new-q').click();
            }
        });

        // Edit question → opens inline form with CONSISTENT table.form-table layout
        $(document).on('click', '.bv-qt-edit-q', function() {
            var $btn = $(this);
            var qid = $btn.data('qid');
            var sid = $btn.data('sid');

            // Show loading on the button
            bvQTBtnLoading($btn, true, 'Loading');

            // Remove any existing question forms
            $('.bv-qt-inline-q-form, .bv-qt-edit-q-form').slideUp(150, function() { $(this).remove(); });

            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: getTemplateId() }, function(res) {
                bvQTBtnLoading($btn, false);
                if (!res.success) return;
                var q = null;
                $.each(res.data.template.sections, function(i, s) {
                    $.each(s.questions, function(j, qq) {
                        if (qq.id == qid) q = qq;
                    });
                });
                if (!q) return;

                var optsText = '';
                var optsLabelsOnly = '';
                if (q.options && Array.isArray(q.options) && bvQTNeedsOptions(q.type)) {
                    $.each(q.options, function(i, o) {
                        var v = typeof o === 'object' ? (o.value || '') : o;
                        var l = typeof o === 'object' ? (o.label || '') : o;
                        optsText += v + '|' + l + '\n';
                        if (l) optsLabelsOnly += l + '\n';
                    });
                }
                var displayOpts = optsLabelsOnly.trim() || optsText.trim();

                // Range values from options
                var rangeMin = '0', rangeMax = '100', rangeStep = '1';
                if (q.type === 'range' && q.options && Array.isArray(q.options)) {
                    rangeMin = q.options[0] || '0';
                    rangeMax = q.options[1] || '100';
                    rangeStep = q.options[2] || '1';
                }

                // Rating stars from options
                var ratingStars = '5';
                if (q.type === 'rating' && q.options && Array.isArray(q.options) && q.options.length > 0) {
                    ratingStars = String(q.options[0]).replace(/[^0-9]/g, '') || '5';
                }

                // Check visibility for options, columns, range, rating and placeholder
                var showOpts = bvQTNeedsOptions(q.type);
                var showCols = bvQTNeedsColumns(q.type);
                var showRange = bvQTNeedsRange(q.type);
                var showRating = bvQTNeedsRating(q.type);
                var showPH = bvQTNeedsPlaceholder(q.type);

                // Build column presets HTML
                var colPresetsHtml = '<div class="bv-qt-col-presets-row">'
                    + '<span class="bv-qt-opts-presets-label">Presets:</span>'
                    + '<button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="contact">Contact Info</button>'
                    + '<button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="line_items">Line Items</button>'
                    + '<button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="references">References</button>'
                    + '<button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="address_book">Address Book</button>'
                    + '<button type="button" class="button button-small bv-qt-col-preset-btn" data-col-preset="education">Education</button>'
                    + '</div>';

                // Build form using SAME table.form-table layout as the add form
                var formHtml = '<div class="bv-qt-edit-q-form">'
                    + '<h4>Edit Question</h4>'
                    + '<table class="form-table">'
                    + '<tr>'
                    + '<th scope="row"><label for="bvq-type">Type</label></th>'
                    + '<td><select id="bvq-type">' + bvQTTypeOptionsHtml(q.type) + '</select></td>'
                    + '</tr>'
                    + '<tr>'
                    + '<th scope="row"><label for="bvq-label">Label <span style="color:#a00;">*</span></label></th>'
                    + '<td><input type="text" id="bvq-label" class="large-text" value="' + escAttr(q.label) + '" placeholder="Question label" /></td>'
                    + '</tr>'
                    + '<tr class="bvq-placeholder-row"' + (showPH ? '' : ' style="display:none;"') + '>'
                    + '<th scope="row"><label for="bvq-placeholder">Placeholder</label></th>'
                    + '<td><input type="text" id="bvq-placeholder" class="large-text" value="' + escAttr(q.placeholder || '') + '" placeholder="Placeholder text (optional)" /></td>'
                    + '</tr>'
                    + '<tr>'
                    + '<th scope="row"><label>Required</label></th>'
                    + '<td><label><input type="checkbox" id="bvq-required"' + (q.is_required==1?' checked':'') + ' /> This question is required</label></td>'
                    + '</tr>'
                    + '<tr>'
                    + '<th scope="row"><label for="bvq-help">Help Text</label></th>'
                    + '<td><input type="text" id="bvq-help" class="large-text" value="' + escAttr(q.help_text || '') + '" placeholder="Optional help text shown below the field" /></td>'
                    + '</tr>'
                    // Options row (select/radio/checkbox)
                    + '<tr class="bvq-options-row"' + (showOpts ? '' : ' style="display:none;"') + '>'
                    + '<th scope="row">'
                    + '<label>Options</label>'
                    + '<p class="description" style="margin:6px 0 0;font-weight:400;">Type labels one per line — values auto-generate.<br><code>value|Label</code> for custom values.</p>'
                    + '</th>'
                    + '<td>'
                    + '<div class="bv-qt-opts-presets">' + bvQTPresetsHtml('bv-qt-preset-btn bvq-preset-btn') + '</div>'
                    + '<textarea id="bvq-options" rows="4" class="large-text" placeholder="Option Label&#10;Another Option">' + escHtml(displayOpts) + '</textarea>'
                    + '</td>'
                    + '</tr>'
                    // Columns row (repeatable table)
                    + '<tr class="bvq-cols-row"' + (showCols ? '' : ' style="display:none;"') + '>'
                    + '<th scope="row">'
                    + '<label>Table Columns</label>'
                    + '<p class="description" style="margin:6px 0 0;font-weight:400;">Define the columns for the repeatable table. Users can add/remove rows when filling out the form.</p>'
                    + '</th>'
                    + '<td>'
                    + '<div class="bv-qt-col-builder" id="bvq-edit-col-builder">'
                    + colPresetsHtml
                    + '<div class="bv-qt-col-list" id="bvq-edit-col-list"></div>'
                    + '<button type="button" class="button button-small bv-qt-col-add-btn" id="bvq-edit-col-add">'
                    + '<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;margin-right:3px;font-size:14px;"></span>Add Column'
                    + '</button>'
                    + '<div class="bv-qt-table-preview" id="bvq-edit-table-preview">'
                    + '<div class="bv-qt-table-preview-label">Preview:</div>'
                    + '<table><thead id="bvq-edit-preview-thead"><tr></tr></thead><tbody id="bvq-edit-preview-tbody"><tr></tr></tbody></table>'
                    + '</div>'
                    + '</div>'
                    + '</td>'
                    + '</tr>'
                    // Range row
                    + '<tr class="bvq-range-row"' + (showRange ? '' : ' style="display:none;"') + '>'
                    + '<th scope="row">'
                    + '<label>Range Settings</label>'
                    + '<p class="description" style="margin:6px 0 0;font-weight:400;">Set the slider\'s minimum, maximum, and step values.</p>'
                    + '</th>'
                    + '<td>'
                    + '<div class="bv-qt-range-inputs">'
                    + '<div class="bv-qt-range-field"><label for="bvq-range-min">Min</label><input type="number" id="bvq-range-min" class="small-text" value="' + escAttr(rangeMin) + '" /></div>'
                    + '<div class="bv-qt-range-field"><label for="bvq-range-max">Max</label><input type="number" id="bvq-range-max" class="small-text" value="' + escAttr(rangeMax) + '" /></div>'
                    + '<div class="bv-qt-range-field"><label for="bvq-range-step">Step</label><input type="number" id="bvq-range-step" class="small-text" value="' + escAttr(rangeStep) + '" min="0.01" step="any" /></div>'
                    + '</div>'
                    + '</td>'
                    + '</tr>'
                    // Rating row
                    + '<tr class="bvq-rating-row"' + (showRating ? '' : ' style="display:none;"') + '>'
                    + '<th scope="row">'
                    + '<label>Rating Settings</label>'
                    + '<p class="description" style="margin:6px 0 0;font-weight:400;">Set the maximum number of stars.</p>'
                    + '</th>'
                    + '<td>'
                    + '<div class="bv-qt-rating-inputs">'
                    + '<label for="bvq-rating-stars">Stars</label>'
                    + '<input type="number" id="bvq-rating-stars" class="small-text" value="' + escAttr(ratingStars) + '" min="1" max="10" />'
                    + '<span class="bv-qt-rating-preview" id="bvq-edit-rating-preview">' + bvQTRatingStarsHtml(parseInt(ratingStars, 10) || 5) + '</span>'
                    + '</div>'
                    + '</td>'
                    + '</tr>'
                    + '</table>'
                    + '<div class="bv-qt-qform-actions">'
                    + '<button type="button" class="button button-primary" id="bvq-save">Save Question</button> '
                    + '<button type="button" class="button" id="bvq-cancel">Cancel</button>'
                    + '</div>'
                    + '</div>';
                $btn.closest('li').after(formHtml);

                // Load columns into the column builder if repeatable
                if (showCols) {
                    bvQTLoadColumns($('#bvq-edit-col-builder'), q.options || []);
                }

                // Scroll into view
                $('.bv-qt-edit-q-form')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }).fail(function() {
                bvQTBtnLoading($btn, false);
                alert('Failed to load question data.');
            });
        });

        // Toggle options/placeholder/columns/range/rating visibility for edit form
        $(document).on('change', '#bvq-type', function() {
            var t = $(this).val();
            var $form = $(this).closest('.bv-qt-edit-q-form');
            $form.find('.bvq-options-row').toggle(bvQTNeedsOptions(t));
            $form.find('.bvq-cols-row').toggle(bvQTNeedsColumns(t));
            $form.find('.bvq-range-row').toggle(bvQTNeedsRange(t));
            $form.find('.bvq-rating-row').toggle(bvQTNeedsRating(t));
            $form.find('.bvq-placeholder-row').toggle(bvQTNeedsPlaceholder(t));
            // Initialize column builder when switching to repeatable
            if (bvQTNeedsColumns(t)) {
                var $colBuilder = $form.find('#bvq-edit-col-builder');
                if ($colBuilder.find('.bv-qt-col-item').length === 0) {
                    $colBuilder.find('.bv-qt-col-list').append(bvQTColItemHtml('Column 1', 'text'));
                    bvQTUpdateTablePreview($colBuilder);
                }
            }
        });

        // Save question edit
        $(document).on('click', '#bvq-save', function() {
            var $btn = $(this);
            var qid = $btn.closest('.bv-qt-edit-q-form').prev('li').find('.bv-qt-edit-q').data('qid');
            var sid = $btn.closest('.bv-qt-edit-q-form').prev('li').find('.bv-qt-edit-q').data('sid');
            var label = $('#bvq-label').val().trim();
            if (!label) { alert('Question label is required.'); $('#bvq-label').focus(); return; }
            bvQTBtnLoading($btn, true, 'Save');
            var type = $('#bvq-type').val();
            var optionsText = '';
            if (bvQTNeedsOptions(type)) {
                optionsText = bvQTProcessOptionsText($('#bvq-options').val());
            } else if (bvQTNeedsColumns(type)) {
                optionsText = bvQTGetColumnsText($('#bvq-edit-col-builder'));
            } else if (bvQTNeedsRange(type)) {
                var min = $('#bvq-range-min').val() || '0';
                var max = $('#bvq-range-max').val() || '100';
                var step = $('#bvq-range-step').val() || '1';
                optionsText = min + '\n' + max + '\n' + step;
            } else if (bvQTNeedsRating(type)) {
                optionsText = $('#bvq-rating-stars').val() || '5';
            }
            $.post(ajaxurl, {
                action: 'bv_qt_save_question', nonce: BVQT.nonce,
                id: qid, section_id: sid,
                type: type,
                label: label,
                placeholder: $('#bvq-placeholder').val(),
                is_required: $('#bvq-required').is(':checked') ? 1 : 0,
                help_text: $('#bvq-help').val(),
                options_text: optionsText
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (res.success) {
                    bvQTShowNotice('Question updated successfully.');
                    editTemplate(getTemplateId());
                } else {
                    alert(res.data.message);
                }
            }).fail(function(xhr, status, error) { bvQTBtnLoading($btn, false); alert('Failed to save question. Network error (' + (status || 'unknown') + '). Please check your connection and try again.'); });
        });

        // Cancel question edit
        $(document).on('click', '#bvq-cancel', function() {
            $(this).closest('.bv-qt-edit-q-form').slideUp(150, function() { $(this).remove(); });
        });

        // Delete question
        $(document).on('click', '.bv-qt-del-q', function() {
            if (!confirm('Delete this question?')) return;
            var $btn = $(this);
            bvQTBtnLoading($btn, true, 'Delete');
            $.post(ajaxurl, {
                action: 'bv_qt_delete_question', nonce: BVQT.nonce,
                id: $btn.data('qid')
            }, function(res) {
                bvQTBtnLoading($btn, false);
                if (res.success) {
                    bvQTShowNotice('Question deleted successfully.');
                    editTemplate(getTemplateId());
                }
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to delete question. Network error — please check your connection and try again.'); });
        });

        // Enter to save in edit question form
        $(document).on('keydown', '#bvq-label, #bvq-placeholder, #bvq-help', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $('#bvq-save').click();
            }
        });

        // ── Preset Options Buttons ──
        // For new question forms
        $(document).on('click', '.bv-qt-preset-btn:not(.bvq-preset-btn)', function() {
            var preset = $(this).data('preset');
            if (bvQTPresets[preset]) {
                $(this).closest('tr').find('#bvq-new-options').val(bvQTPresets[preset]);
            }
        });
        // For edit question forms
        $(document).on('click', '.bvq-preset-btn', function() {
            var preset = $(this).data('preset');
            if (bvQTPresets[preset]) {
                $('#bvq-options').val(bvQTPresets[preset]);
            }
        });

        // ── Track user-edited fields (so smart defaults don't overwrite) ──
        $(document).on('input', '#bvq-new-placeholder', function() {
            $(this).data('user-edited', true);
        });
        $(document).on('input', '#bvq-new-help', function() {
            $(this).data('user-edited', true);
        });

        // ── Column Builder Event Handlers ──

        // Add column button (works for both new and edit forms)
        $(document).on('click', '.bv-qt-col-add-btn', function() {
            var $builder = $(this).closest('.bv-qt-col-builder');
            $builder.find('.bv-qt-col-list').append(bvQTColItemHtml('', 'text'));
            bvQTUpdateTablePreview($builder);
            // Focus the new column name input
            $builder.find('.bv-qt-col-item:last .bv-qt-col-name').focus();
        });

        // Remove column button
        $(document).on('click', '.bv-qt-col-remove', function() {
            var $builder = $(this).closest('.bv-qt-col-builder');
            // Don't allow removing the last column
            if ($builder.find('.bv-qt-col-item').length <= 1) return;
            $(this).closest('.bv-qt-col-item').slideUp(150, function() {
                $(this).remove();
                bvQTUpdateTablePreview($builder);
            });
        });

        // Update preview when column name changes
        $(document).on('input', '.bv-qt-col-name', function() {
            bvQTUpdateTablePreview($(this).closest('.bv-qt-col-builder'));
        });

        // Update preview when column type changes
        $(document).on('change', '.bv-qt-col-type', function() {
            bvQTUpdateTablePreview($(this).closest('.bv-qt-col-builder'));
        });

        // Column presets (works for both new and edit forms)
        $(document).on('click', '.bv-qt-col-preset-btn', function() {
            var presetKey = $(this).data('col-preset');
            var cols = bvQTColPresets[presetKey];
            if (!cols) return;
            var $builder = $(this).closest('.bv-qt-col-builder');
            var $list = $builder.find('.bv-qt-col-list').empty();
            $.each(cols, function(i, col) {
                $list.append(bvQTColItemHtml(col.name, col.type));
            });
            bvQTUpdateTablePreview($builder);
        });

        // ── Rating Star Preview Update ──
        $(document).on('input', '#bvq-new-rating-stars, #bvq-rating-stars', function() {
            var count = parseInt($(this).val(), 10) || 5;
            count = Math.min(Math.max(count, 1), 10);
            var $preview = $(this).siblings('.bv-qt-rating-preview');
            if ($preview.length === 0) {
                // For edit form, find by ID
                $preview = $('#bvq-edit-rating-preview');
            }
            if ($preview.length) {
                $preview.html(bvQTRatingStarsHtml(count));
            }
        });

        // ── Duplicate Question (within same section) ──
        $(document).on('click', '.bv-qt-dup-q', function() {
            var $btn = $(this);
            var qid = $btn.data('qid');
            var sid = $btn.data('sid');
            bvQTBtnLoading($btn, true, 'Copy');
            $.post(ajaxurl, { action: 'bv_qt_get_template', nonce: BVQT.nonce, template_id: getTemplateId() }, function(res) {
                if (!res.success) { bvQTBtnLoading($btn, false); return; }
                var q = null;
                $.each(res.data.template.sections, function(i, s) {
                    $.each(s.questions, function(j, qq) {
                        if (qq.id == qid) q = qq;
                    });
                });
                if (!q) { bvQTBtnLoading($btn, false); return; }

                // Convert options to pipe-delimited string
                var optsStr = '';
                if (q.options && Array.isArray(q.options)) {
                    $.each(q.options, function(i, o) {
                        var v = typeof o === 'object' ? (o.value || '') : o;
                        var l = typeof o === 'object' ? (o.label || '') : o;
                        optsStr += v + '|' + l + '\n';
                    });
                }

                $.post(ajaxurl, {
                    action: 'bv_qt_save_question', nonce: BVQT.nonce,
                    id: 0, section_id: sid,
                    type: q.type,
                    label: q.label + ' (Copy)',
                    placeholder: q.placeholder,
                    is_required: q.is_required,
                    help_text: q.help_text,
                    options_text: optsStr
                }, function(res2) {
                    bvQTBtnLoading($btn, false);
                    if (res2.success) {
                        bvQTShowNotice('Question duplicated successfully.');
                        editTemplate(getTemplateId());
                    } else {
                        alert(res2.data.message || 'Failed to duplicate question.');
                    }
                }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to duplicate question.'); });
            }).fail(function() { bvQTBtnLoading($btn, false); alert('Failed to load question data.'); });
        });

        // ── Global import function (called from inline button) ──
        window.bvQTImportQuestionnaires = function() {
            if (!confirm('Import Market Research and Business Plan questionnaire templates? Existing templates with the same slug will be skipped.')) return;
            var $btn = $('#bv-qt-import-btn');
            bvQTBtnLoading($btn, true, 'Importing');
            $.post(ajaxurl, { action: 'bv_qt_import_questionnaires', nonce: BVQT.nonce }, function(res) {
                bvQTBtnLoading($btn, false);
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
                bvQTBtnLoading($btn, false);
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
            'email': 'Email', 'phone': 'Phone', 'url': 'URL', 'date': 'Date', 'time': 'Time',
            'select': 'Dropdown', 'radio': 'Radio', 'checkbox': 'Checkbox',
            'range': 'Range / Slider', 'color': 'Color', 'rating': 'Star Rating',
            'address': 'Address', 'repeatable': 'Repeatable Table', 'wysiwyg': 'Rich Text',
            'heading': 'Heading', 'paragraph': 'Paragraph', 'file': 'File Upload'
        };

        // Question type colors for preview badges
        var bvDocTypeColors = {
            'text': '#2271b1', 'textarea': '#2271b1', 'number': '#7e5bf0',
            'email': '#2271b1', 'phone': '#2271b1', 'url': '#2271b1', 'date': '#7e5bf0', 'time': '#7e5bf0',
            'select': '#0e7732', 'radio': '#0e7732', 'checkbox': '#0e7732',
            'range': '#7e5bf0', 'color': '#b32d2e', 'rating': '#f59e0b',
            'address': '#0891b2', 'repeatable': '#0891b2', 'wysiwyg': '#7e5bf0',
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
            var $btn = $(this);
            bvQTBtnLoading($btn, true, 'Importing');

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
                bvQTBtnLoading($btn, false);
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
                bvQTBtnLoading($btn, false);
                return;
            }

            templateData.sections = $.grep(templateData.sections, function(s) {
                return s.title !== '';
            });

            if (templateData.sections.length === 0) {
                alert('All sections have empty titles. Please name at least one section.');
                bvQTBtnLoading($btn, false);
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
                    bvQTBtnLoading($btn, false);
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
                },
                error: function(xhr, status, error) {
                    bvQTBtnLoading($btn, false);
                    alert('Import failed: ' + (error || status || 'Network error'));
                }
            });
        });

    });

})(jQuery);
