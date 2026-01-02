(function ($) {
    'use strict';

    var data = window.khFoldersMedia;
    if (!data || !window.wp || !wp.media) {
        return;
    }

    var currentFolder = '';

    function setUploaderFolder(folderId) {
        if (!wp.Uploader || !wp.Uploader.defaults) {
            return;
        }

        wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};

        if (folderId) {
            wp.Uploader.defaults.multipart_params.kh_folder = folderId;
        } else if (wp.Uploader.defaults.multipart_params.kh_folder) {
            delete wp.Uploader.defaults.multipart_params.kh_folder;
        }
    }

    var FolderFilter = wp.media.view.AttachmentFilters.extend({
        createFilters: function () {
            var filters = {};

            filters.all = {
                text: data.strings.allFolders,
                props: {
                    kh_folder: ''
                }
            };

            data.folders.forEach(function (folder) {
                filters['kh-folder-' + folder.term_id] = {
                    text: folder.name,
                    props: {
                        kh_folder: folder.term_id
                    }
                };
            });

            this.filters = filters;
        },

        change: function () {
            var filter = this.filters[this.el.value];
            if (!filter) {
                return;
            }

            currentFolder = filter.props.kh_folder || '';
            this.model.set('kh_folder', currentFolder);
            this.collection.props.set('kh_folder', currentFolder);
            this.collection.props.trigger('change');
            setUploaderFolder(currentFolder);
            wp.media.view.AttachmentFilters.prototype.change.apply(this, arguments);
        }
    });

    var OriginalBrowser = wp.media.view.AttachmentsBrowser;
    wp.media.view.AttachmentsBrowser = OriginalBrowser.extend({
        createToolbar: function () {
            OriginalBrowser.prototype.createToolbar.apply(this, arguments);

            this.toolbar.set('khFoldersFilter', new FolderFilter({
                controller: this.controller,
                collection: this.collection,
                model: this.collection.props,
                priority: 80
            }));
        }
    });

    setUploaderFolder(currentFolder);
})(jQuery);
