(function (api, $) {
    "use strict";

    var gsheet = {};

    gsheet.toggle_fields_visibility = function (e, connect_service, parent) {

        if ($("select[name='connection_email_list']", parent).val() === '') {
            $('.gsheet-group-block', parent).hide();
        }
    };

    gsheet.connection_email_list_handler = function () {

        function add_spinner(placement) {
            var spinner_html = $('<img class="mo-spinner fetch-email-list" src="' + mailoptin_globals.admin_url + '/images/spinner.gif">');
            $(placement).after(spinner_html);
        }

        function remove_spinner(parent) {
            $('.mo-spinner.fetch-email-list', parent).remove();
        }

        var parent = $(this).parents('.mo-integration-widget');

        // hide all GoogleSheet fields.
        $('div[class*="GoogleSheetConnect"]', parent).hide();

        var connection_service = $("select[name='connection_service']", parent).val();

        if (connection_service !== 'GoogleSheetConnect') return;

        var list_id = $(this).val();

        add_spinner(this);

        $.post(
            ajaxurl, {
                action: 'mailoptin_customizer_fetch_gsheetfile_sheets',
                list_id: list_id,
                security: $("input[data-customize-setting-link*='[ajax_nonce]']").val()
            },
            function (response) {

                if (_.isObject(response) && 'success' in response && 'data' in response) {
                    var $el = $('div.GoogleSheetConnect_file_sheets select', parent);
                    $el.empty(); // remove old options
                    $el.append($("<option></option>").attr('value', '').text('———'));
                    $.each(response.data, function (key, value) {
                        $el.append($("<option></option>").attr('value', value).text(key));
                    });
                }

                remove_spinner();

                if ($("select[name='connection_email_list']", parent)) {
                    $('div[class*="GoogleSheetConnect"]', parent).show();
                }
            }
        );
    };

    gsheet.init = function () {

        $(document).on('change', "select[name='connection_email_list']", gsheet.connection_email_list_handler);
        $(document).on('mo_new_email_list_data_found mo_email_list_data_not_found', gsheet.toggle_fields_visibility);
    };

    $(window).on('load', gsheet.init);

})(wp.customize, jQuery);