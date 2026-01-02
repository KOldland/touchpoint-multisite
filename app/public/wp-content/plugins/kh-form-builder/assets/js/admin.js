(function ($) {
    'use strict';

    function template(field, index) {
        var types = Object.keys(khFormBuilder.fieldTypes).map(function (key) {
            var selected = field.type === key ? 'selected' : '';
            return '<option value="' + key + '" ' + selected + '>' + khFormBuilder.fieldTypes[key] + '</option>';
        }).join('');

        var required = field.required ? 'checked' : '';

        return '<tr class="kh-form-field" data-index="' + index + '">' +
            '<td><input type="text" class="regular-text" value="' + (field.label || '') + '" data-field="label"></td>' +
            '<td><select data-field="type">' + types + '</select></td>' +
            '<td><input type="checkbox" data-field="required" ' + required + '></td>' +
            '<td><button type="button" class="button-link delete-field">' + wp.i18n.__( 'Remove', 'kh-form-builder' ) + '</button></td>' +
            '</tr>';
    }

    function loadFields($table, fields) {
        var rows = fields.map(function (field, index) {
            return template(field, index);
        }).join('');

        $table.find('tbody').html(rows);
    }

    function serialize($table) {
        return $table.find('tbody tr').map(function () {
            var $row = $(this);
            return {
                label: $row.find('[data-field="label"]').val(),
                type: $row.find('[data-field="type"]').val(),
                required: $row.find('[data-field="required"]').is(':checked')
            };
        }).get();
    }

    $(function () {
        var $builder = $('#kh-form-builder');
        if (! $builder.length) {
            return;
        }

        var fields = $builder.data('fields') || [];
        var $table = $builder.find('.kh-form-fields-table');
        loadFields($table, fields);

        $table.find('tbody').sortable({
            handle: 'td',
            helper: function (event, ui) {
                ui.children().each(function () {
                    $(this).width($(this).width());
                });
                return ui;
            }
        });

        $('#kh-form-add-field').on('click', function (event) {
            event.preventDefault();
            fields.push({ label: '', type: 'text', required: false });
            loadFields($table, fields);
        });

        $table.on('click', '.delete-field', function (event) {
            event.preventDefault();
            $(this).closest('tr').remove();
        });

        $('#post').on('submit', function () {
            var data = serialize($table);
            $('<input>').attr({
                type: 'hidden',
                name: 'kh_form_fields'
            }).val(JSON.stringify(data)).appendTo('#post');
        });
    });
})(jQuery);
