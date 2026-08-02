(function($) {
    'use strict';

    if (typeof bvAdmin === 'undefined') return;

    var $modal      = $('#bv-agreement-modal');
    var $form       = $('#bv-agreement-form');
    var $title      = $('#bv-agreement-modal-title');
    var $nameField  = $('#bv-agreement-name');
    var $slugField  = $('#bv-agreement-slug');
    var $typeField  = $('#bv-agreement-type');
    var $contentField = $('#bv-agreement-content');
    var $defaultField = $('#bv-agreement-default');
    var $idField    = $form.find('input[name="id"]');
    var editingId   = 0;

    // Auto-generate slug from name.
    $nameField.on('input', function() {
        if (editingId === 0) {
            $slugField.val( $(this).val().toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-+|-+$/g, '') );
        }
    });

    // Open modal for adding.
    $('#bv-add-agreement').on('click', function() {
        editingId = 0;
        $title.text(bvAdmin.strings.add_title);
        $form[0].reset();
        $idField.val('');
        $modal.show();
        $nameField.focus();
    });

    // Open modal for editing.
    $(document).on('click', '.bv-edit-btn', function() {
        var id = $(this).data('id');
        editingId = id;

        $.post(bvAdmin.ajax_url, {
            action: 'bv_get_agreement_template',
            nonce: bvAdmin.nonce,
            id: id
        }, function(res) {
            if (res.success) {
                var t = res.data;
                $title.text(bvAdmin.strings.edit_title);
                $idField.val(t.id);
                $nameField.val(t.name);
                $slugField.val(t.slug);
                $typeField.val(t.type);
                $contentField.val(t.content);
                $defaultField.prop('checked', parseInt(t.is_default) === 1);
                $modal.show();
                $nameField.focus();
            } else {
                alert(res.data.message || bvAdmin.strings.error);
            }
        });
    });

    // Close modal.
    $('.bv-modal-close, .bv-cancel-btn, .bv-modal-overlay').on('click', function() {
        $(this).closest('.bv-modal').hide();
    });

    // Save template.
    $form.on('submit', function(e) {
        e.preventDefault();

        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.text(bvAdmin.strings.saving).prop('disabled', true);

        $.post(bvAdmin.ajax_url, {
            action: 'bv_save_agreement_template',
            nonce: bvAdmin.nonce,
            id: $idField.val(),
            name: $nameField.val(),
            slug: $slugField.val(),
            type: $typeField.val(),
            content: $contentField.val(),
            is_default: $defaultField.is(':checked') ? 1 : 0
        }, function(res) {
            $submitBtn.text(originalText).prop('disabled', false);
            if (res.success) {
                $modal.hide();
                location.reload();
            } else {
                alert(res.data.message || bvAdmin.strings.error);
            }
        }).fail(function() {
            $submitBtn.text(originalText).prop('disabled', false);
            alert(bvAdmin.strings.error);
        });
    });

    // Delete template.
    $(document).on('click', '.bv-delete-btn', function() {
        if (!confirm(bvAdmin.strings.confirm_delete)) {
            return;
        }
        var id = $(this).data('id');

        $.post(bvAdmin.ajax_url, {
            action: 'bv_delete_agreement_template',
            nonce: bvAdmin.nonce,
            id: id
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message || bvAdmin.strings.error);
            }
        });
    });

    // Set default.
    $(document).on('click', '.bv-set-default-btn', function() {
        var id = $(this).data('id');

        $.post(bvAdmin.ajax_url, {
            action: 'bv_set_default_agreement',
            nonce: bvAdmin.nonce,
            id: id
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data.message || bvAdmin.strings.error);
            }
        });
    });

    // ============================================================
    // PDF Import
    // ============================================================
    var $importModal    = $('#bv-import-pdf-modal');
    var $importForm     = $('#bv-import-pdf-form');
    var $fileInput      = $('#bv-import-pdf-file');
    var $dropZone       = $('#bv-pdf-drop-zone');
    var $dropContent    = $('#bv-pdf-drop-zone-content');
    var $fileInfo       = $('#bv-pdf-file-info');
    var $fileName       = $('#bv-pdf-file-name');
    var $fileSize       = $('#bv-pdf-file-size');
    var $removeFile     = $('#bv-pdf-remove-file');
    var $importSubmit   = $('#bv-import-pdf-submit');
    var selectedFile    = null;

    // Open import modal
    $('#bv-import-agreement-pdf').on('click', function() {
        $importForm[0].reset();
        resetFileSelection();
        $importModal.show();
    });

    // Click to select file
    $dropZone.on('click', function(e) {
        if ($(e.target).is('#bv-pdf-remove-file') || $(e.target).closest('#bv-pdf-remove-file').length) return;
        $fileInput.trigger('click');
    });

    // File selected via input
    $fileInput.on('change', function() {
        if (this.files && this.files[0]) {
            handleFileSelect(this.files[0]);
        }
    });

    // Drag & drop
    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#D4AF37').css('background', '#fffdf5');
    }).on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#ccc').css('background', '');
    }).on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).css('border-color', '#ccc').css('background', '');
        var files = e.originalEvent.dataTransfer.files;
        if (files && files[0]) {
            handleFileSelect(files[0]);
        }
    });

    // Handle file selection
    function handleFileSelect(file) {
        // Validate type
        if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
            alert(bvAdmin.strings.invalid_file);
            return;
        }
        selectedFile = file;
        $fileName.text(file.name);
        $fileSize.text(formatFileSize(file.size));
        $dropContent.hide();
        $fileInfo.show();
        $dropZone.css('border-color', '#2A9D8F').css('border-style', 'solid');
    }

    // Reset file selection
    function resetFileSelection() {
        selectedFile = null;
        $fileInput.val('');
        $dropContent.show();
        $fileInfo.hide();
        $dropZone.css('border-color', '#ccc').css('border-style', 'dashed').css('background', '');
    }

    // Remove selected file
    $removeFile.on('click', function(e) {
        e.stopPropagation();
        resetFileSelection();
    });

    // Format file size
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // Submit import form
    $(document).on('submit', '#bv-import-pdf-form', function(e) {
        e.preventDefault();

        if (!selectedFile) {
            alert(bvAdmin.strings.select_pdf);
            return;
        }

        var $btn = $importSubmit;
        var originalText = $btn.text();
        $btn.text(bvAdmin.strings.importing).prop('disabled', true);

        var formData = new FormData();
        formData.append('action', 'bv_import_agreement_pdf');
        formData.append('nonce', bvAdmin.nonce);
        formData.append('pdf_file', selectedFile);
        formData.append('name', $('#bv-import-name').val());
        formData.append('type', $('#bv-import-type').val());
        formData.append('is_default', $('#bv-import-default').is(':checked') ? 1 : 0);

        $.ajax({
            url: bvAdmin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $btn.text(originalText).prop('disabled', false);
                if (res.success) {
                    $importModal.hide();
                    location.reload();
                } else {
                    alert(res.data.message || bvAdmin.strings.import_error);
                }
            },
            error: function() {
                $btn.text(originalText).prop('disabled', false);
                alert(bvAdmin.strings.import_error);
            }
        });
    });

    // Close on Escape.
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            if ($modal.is(':visible')) $modal.hide();
            if ($importModal.is(':visible')) $importModal.hide();
        }
    });

})(jQuery);
