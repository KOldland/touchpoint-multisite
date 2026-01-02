(function ($) {
    'use strict';

    var data = window.khFoldersList || null;
    if (!data) {
        return;
    }

    function findFolder(id) {
        id = parseInt(id, 10);
        if (!id || !data.folders) {
            return null;
        }

        for (var i = 0; i < data.folders.length; i++) {
            if (parseInt(data.folders[i].term_id, 10) === id) {
                return data.folders[i];
            }
        }
        return null;
    }

    function setStatus($target, message, type) {
        if (!$target || !$target.length) {
            return;
        }

        $target.text(message || '');
        $target.toggleClass('is-error', type === 'error');
        $target.toggleClass('is-success', type === 'success');
    }

    function updateChips(objectId, folderId) {
        var $chips = $('[data-kh-folder-chips="' + objectId + '"]');
        if (!$chips.length) {
            return;
        }

        var folder = findFolder(folderId);
        if (!folder) {
            $chips.html('&mdash;');
            return;
        }

        var color = folder.color || '#ccd0d4';
        var chip = '<span class="kh-folder-chip" style="border-color:' + color + ';background:' + color + '1a;">' + folder.name + '</span>';
        $chips.html(chip);
    }

    function handleAssignClick(event) {
        event.preventDefault();
        var $button = $(event.currentTarget);
        var objectId = parseInt($button.data('kh-folder-assign'), 10);
        if (!objectId) {
            return;
        }

        var $control = $('[data-kh-folder-control="' + objectId + '"]');
        var $select = $control.find('[data-kh-folder-select]');
        var $status = $control.find('[data-kh-folder-status]');
        var folderId = parseInt($select.val(), 10);

        if (!folderId) {
            setStatus($status, data.strings.select, 'error');
            return;
        }

        $button.prop('disabled', true).text(data.strings.saving);
        setStatus($status, '', '');

        $.post(data.ajaxUrl, {
            action: 'kh_folders_assign',
            nonce: data.nonce,
            object_id: objectId,
            term_id: folderId
        }).done(function (response) {
            if (!response || !response.success) {
                setStatus($status, data.strings.failed, 'error');
                return;
            }

            updateChips(objectId, folderId);
            setStatus($status, data.strings.saved, 'success');
        }).fail(function () {
            setStatus($status, data.strings.failed, 'error');
        }).always(function () {
            $button.prop('disabled', false).text(data.strings.apply);
        });
    }

    function hydrateSelectors() {
        $('[data-kh-folder-select]').each(function () {
            var $select = $(this);
            if ($select.children().length) {
                return;
            }

            $select.append($('<option>', { value: '', text: data.strings.choose }));
            (data.folders || []).forEach(function (folder) {
                $select.append($('<option>', {
                    value: folder.term_id,
                    text: folder.name
                }));
            });
        });
    }

    function handleTreeToggle(event) {
        event.preventDefault();
        var $btn = $(event.currentTarget);
        var $panel = $('[data-kh-folder-tree-panel]');
        if (!$panel.length) {
            return;
        }
        var isOpen = $panel.hasClass('is-open');
        $panel.toggleClass('is-open', !isOpen);
        var labelShow = $btn.data('labelShow') || 'Show folder tree';
        var labelHide = $btn.data('labelHide') || 'Hide folder tree';
        $btn.text(!isOpen ? labelHide : labelShow);
        var $icon = $btn.find('.dashicons');
        if ($icon.length) {
            $icon.toggleClass('dashicons-arrow-right-alt2', isOpen).toggleClass('dashicons-arrow-left-alt2', !isOpen);
        }
    }

    function handleTreeClick(event) {
        var target = event.target;
        var termId = $(target).data('kh-folder-select-node');
        if (!termId) {
            return;
        }

        event.preventDefault();
        var url = new URL(window.location.href);
        url.searchParams.set('kh_folder', termId);
        window.location.href = url.toString();
    }

    $(document)
        .on('click', '[data-kh-folder-assign]', handleAssignClick)
        .on('click', '[data-kh-folder-tree-toggle]', handleTreeToggle)
        .on('click', '[data-kh-folder-tree] .kh-folder-tree-label', handleTreeClick)
        .ready(hydrateSelectors);
})(jQuery);
