(function ($) {
    'use strict';

    var selectors = {
        createButton: '[data-kh-folders-create]',
        list: '#kh-folders-list',
        notice: '#kh-folders-notices',
        bulkDelete: '[data-kh-folders-bulk-delete]',
        selectAll: '#kh-folders-select-all',
        treeList: '.kh-folder-tree-list'
    };

    var state = {
        folders: window.khFoldersAdmin.folders || []
    };

    function notify(message, type) {
        var $notice = $(selectors.notice);
        if (!$notice.length) {
            return;
        }

        $notice.removeClass('is-error is-success').addClass(type === 'error' ? 'is-error' : 'is-success');
        $notice.text(message).fadeIn();

        setTimeout(function () {
            $notice.fadeOut();
        }, 3500);
    }

    function sendAjax(action, data) {
        data = $.extend({}, data, {
            action: action,
            nonce: window.khFoldersAdmin.nonce
        });

        return $.post(window.khFoldersAdmin.ajaxUrl, data)
            .fail(function () {
                notify('Folder service request failed', 'error');
            });
    }

    function esc(value) {
        return $('<div>').text(value).html();
    }

    function folderRowTemplate(folder) {
        var badge = folder.shared ? '' : '<span class="kh-folder-badge">' + window.khFoldersAdmin.strings.personal + '</span>';
        return [
            '<tr data-kh-folder-row data-term-id="' + folder.term_id + '">',
            '<td class="column-handle"><span class="kh-folder-drag dashicons dashicons-move" title="' + window.khFoldersAdmin.strings.drag + '"></span></td>',
            '<td>' + esc(folder.name) + ' ' + badge + '</td>',
            '<td><input type="color" value="' + folder.color + '" data-kh-folder-color="' + folder.term_id + '"></td>',
            '<td><input type="number" class="small-text" value="' + folder.order + '" data-kh-folder-order="' + folder.term_id + '"></td>',
            '<td><button class="button button-link-delete" data-kh-folder-delete="' + folder.term_id + '">' + window.khFoldersAdmin.strings.deleteLabel + '</button></td>',
            '<td class="column-select"><input type="checkbox" data-kh-folder-select="' + folder.term_id + '"></td>',
            '</tr>'
        ].join('');
    }

    function renderList() {
        var $list = $(selectors.list);
        if (!$list.length) {
            return;
        }

        if (!state.folders.length) {
            $list.html('<tr class="no-items"><td colspan="6">' + window.khFoldersAdmin.strings.empty + '</td></tr>');
            destroySortable();
            updateBulkState();
            return;
        }

        var rows = state.folders.map(folderRowTemplate).join('');
        $list.html(rows);
        initSortable();
        updateBulkState();
    }

    function addFolder(folder) {
        folder.order = parseInt(folder.order, 10) || 0;
        state.folders.push(folder);
        state.folders.sort(function (a, b) {
            return a.order - b.order;
        });
        renderList();
    }

    function removeFolder(termId) {
        state.folders = state.folders.filter(function (folder) {
            return folder.term_id !== termId;
        });
        renderList();
        removeTreeNode(termId);
    }

    function updateFolder(updated) {
        updated.order = parseInt(updated.order, 10) || 0;
        state.folders = state.folders.map(function (folder) {
            return folder.term_id === updated.term_id ? updated : folder;
        });
        state.folders.sort(function (a, b) {
            return a.order - b.order;
        });
        renderList();
    }

    function destroySortable() {
        var $list = $(selectors.list);
        if ($list && $list.hasClass('ui-sortable')) {
            $list.sortable('destroy');
        }
    }

    function initSortable() {
        var $list = $(selectors.list);
        if (!$list.length) {
            return;
        }

        if ($list.hasClass('ui-sortable')) {
            $list.sortable('destroy');
        }

        $list.sortable({
            handle: '.kh-folder-drag',
            helper: function (event, ui) {
                ui.children().each(function () {
                    $(this).width($(this).width());
                });
                return ui;
            },
            stop: function () {
                var ordered = $list.children('tr').map(function () {
                    return $(this).data('term-id');
                }).get();

                sendAjax('kh_folders_reorder', { order: ordered })
                    .done(function (response) {
                        if (response && response.success && response.data && response.data.folders) {
                            state.folders = response.data.folders;
                            renderList();
                            notify(window.khFoldersAdmin.strings.reordered, 'success');
                        }
                    });
            }
        });
    }

    function handleCreateClick(event) {
        event.preventDefault();

        var folderName = window.prompt(window.khFoldersAdmin.i18n.enterName);
        if (!folderName) {
            return;
        }

        var shared = true;
        if (!window.khFoldersAdmin.permissions.canShare) {
            shared = false;
        } else {
            shared = window.confirm(window.khFoldersAdmin.i18n.confirmShared);
        }

        sendAjax('kh_folders_create', { name: folderName, shared: shared ? 1 : 0 })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.data ? response.data.message || response.data : 'Unknown error', 'error');
                    return;
                }

                addFolder(response.data);
                appendTreeNode(response.data, shared ? 0 : window.khFoldersAdmin.permissions.userId);
                notify(window.khFoldersAdmin.i18n.created.replace('%s', response.data.name), 'success');
            });
    }

    function handleDeleteClick(event) {
        event.preventDefault();
        var termId = parseInt($(event.currentTarget).data('kh-folder-delete'), 10);
        if (!termId) {
            return;
        }

        sendAjax('kh_folders_delete', { term_id: termId })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.data ? response.data.message || response.data : 'Unknown error', 'error');
                    return;
                }

                removeFolder(termId);
                notify(window.khFoldersAdmin.strings.deleted, 'success');
            });
    }

    function handleMetaChange(event) {
        var $input = $(event.currentTarget);
        var termId = parseInt($input.data('kh-folder-color') || $input.data('kh-folder-order'), 10);
        if (!termId) {
            return;
        }

        var payload = { term_id: termId };
        if ($input.is('[data-kh-folder-color]')) {
            payload.color = $input.val();
        } else {
            payload.order = $input.val();
        }

        sendAjax('kh_folders_update_meta', payload)
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.data ? response.data.message || response.data : 'Unknown error', 'error');
                    return;
                }

                updateFolder(response.data);
                notify(window.khFoldersAdmin.strings.updated, 'success');
            });
    }

    function getSelectedIds() {
        return $(selectors.list).find('[data-kh-folder-select]:checked').map(function () {
            return parseInt($(this).data('kh-folder-select'), 10);
        }).get();
    }

    function updateBulkState() {
        var selected = getSelectedIds();
        $(selectors.bulkDelete).prop('disabled', selected.length === 0);

        var totalCheckboxes = $(selectors.list).find('[data-kh-folder-select]').length;
        if (!totalCheckboxes) {
            $(selectors.selectAll).prop('checked', false);
            return;
        }

        $(selectors.selectAll).prop('checked', selected.length === totalCheckboxes);
    }

    function handleSelectAll(event) {
        var checked = $(event.currentTarget).is(':checked');
        $(selectors.list).find('[data-kh-folder-select]').prop('checked', checked);
        updateBulkState();
    }

    function handleSelectChange() {
        updateBulkState();
    }

    function handleBulkDelete(event) {
        event.preventDefault();
        var ids = getSelectedIds();
        if (!ids.length) {
            return;
        }

        if (!window.confirm(window.khFoldersAdmin.strings.bulkConfirm)) {
            return;
        }

        sendAjax('kh_folders_bulk_delete', { term_ids: ids })
            .done(function (response) {
                if (!response || !response.success) {
                    notify(response && response.data ? response.data.message || response.data : 'Unknown error', 'error');
                    return;
                }

                var deleted = response.data.deleted || [];
                state.folders = state.folders.filter(function (folder) {
                    return deleted.indexOf(folder.term_id) === -1;
                });
                renderList();
                deleted.forEach(removeTreeNode);
                notify(window.khFoldersAdmin.strings.bulkDeleted, 'success');
            });
    }

    function initTree() {
        var $trees = $(selectors.treeList);
        if (! $trees.length) {
            return;
        }

        $trees.each(function () {
            var $list = $(this);
            if ($list.hasClass('ui-sortable')) {
                $list.sortable('destroy');
            }
            $list.sortable({
                connectWith: selectors.treeList,
                placeholder: 'kh-folder-tree-placeholder',
                start: function (event, ui) {
                    var $parentNode = ui.item.parent().closest('.kh-folder-tree-node');
                    ui.item.data('old-parent', $parentNode.length ? parseInt($parentNode.data('term-id'), 10) : 0);
                },
                stop: handleTreeDrop,
                handle: '.kh-folder-tree-item'
            });
        });

        $(document).on('click', '.kh-folder-toggle', function () {
            var $button = $(this);
            var expanded = $button.attr('aria-expanded') === 'true';
            var $children = $('#' + $button.attr('aria-controls'));
            if ($children.length) {
                $children.toggle(!expanded);
                $button.attr('aria-expanded', expanded ? 'false' : 'true');
                $button.find('.dashicons').toggleClass('dashicons-arrow-right', expanded).toggleClass('dashicons-arrow-down', !expanded);
            }
        });
    }

    function handleTreeDrop(event, ui) {
        var $item = ui.item;
        var termId = parseInt($item.data('term-id'), 10);
        if (!termId) {
            return;
        }

        var $parentNode = $item.parent().closest('.kh-folder-tree-node');
        var parentId = $parentNode.length ? parseInt($parentNode.data('term-id'), 10) : 0;
        var oldParent = parseInt($item.data('old-parent'), 10) || 0;
        var siblings = $item.parent().children().map(function () {
            return $(this).data('term-id');
        }).get();

        sendAjax('kh_folders_update_parent', {
            term_id: termId,
            parent_id: parentId,
            siblings: siblings
        }).done(function (response) {
            if (!response || !response.success) {
                notify(response && response.data ? response.data.message || response.data : 'Unknown error', 'error');
                return;
            }

            updateFolder(response.data);
            ensureToggleForNode($parentNode);
            if (oldParent !== parentId) {
                ensureToggleForNode($('.kh-folder-tree-node[data-term-id=\"' + oldParent + '\"]'));
            }
            notify(window.khFoldersAdmin.strings.parentUpdated, 'success');
        });
    }

    function appendTreeNode(folder, owner) {
        owner = owner || 0;
        var $tree = $(selectors.treeList).first();
        if (!$tree.length) {
            return;
        }

        var html = '<li class="kh-folder-tree-node" data-term-id="' + folder.term_id + '" data-owner="' + owner + '" data-shared="' + (folder.shared ? '1' : '0') + '"><div class="kh-folder-tree-item"><span class="kh-folder-toggle is-empty" aria-controls="kh-folder-children-' + folder.term_id + '"></span><span class="kh-folder-tree-label">' + esc(folder.name) + '</span></div><ul id="kh-folder-children-' + folder.term_id + '" class="kh-folder-tree-list kh-folder-tree-children"></ul></li>';
        $tree.append(html);
        initTree();
        ensureToggleForNode($tree.closest('.kh-folder-tree-node'));
    }

    function removeTreeNode(termId) {
        var $node = $('.kh-folder-tree-node[data-term-id="' + termId + '"]');
        var $parentList = $node.parent();
        $node.remove();
        if ($parentList.length && !$parentList.children().length) {
            var $parentNode = $parentList.closest('.kh-folder-tree-node');
            ensureToggleForNode($parentNode);
        }
    }

    function ensureToggleForNode($node) {
        if (!$node || !$node.length) {
            return;
        }

        var $children = $node.children('.kh-folder-tree-children');
        var hasChildren = $children.children().length > 0;
        var $toggle = $node.children('.kh-folder-tree-item').find('.kh-folder-toggle').first();
        var controlId = 'kh-folder-children-' + $node.data('term-id');

        if (hasChildren && $toggle.hasClass('is-empty')) {
            $toggle.replaceWith('<button type="button" class="kh-folder-toggle" aria-expanded="true" aria-controls="' + controlId + '"><span class="dashicons dashicons-arrow-down"></span></button>');
        } else if (!hasChildren && !$toggle.hasClass('is-empty')) {
            $toggle.replaceWith('<span class="kh-folder-toggle is-empty" aria-controls="' + controlId + '"></span>');
        }
    }

    function bindUI() {
        var $createButton = $(selectors.createButton);
        if (!$createButton.length) {
            return;
        }

        window.khFoldersAdmin.strings.deleteLabel = window.khFoldersAdmin.strings.deleteLabel || window.khFoldersAdmin.strings.delete;

        state.folders = window.khFoldersAdmin.folders || [];
        renderList();
        initTree();

        $createButton.on('click', handleCreateClick);
        $(document)
            .on('click', '[data-kh-folder-delete]', handleDeleteClick)
            .on('change', '[data-kh-folder-color], [data-kh-folder-order]', handleMetaChange)
            .on('change', '[data-kh-folder-select]', handleSelectChange)
            .on('click', selectors.bulkDelete, handleBulkDelete)
            .on('change', selectors.selectAll, handleSelectAll);
    }

    $(document).ready(bindUI);
})(jQuery);
