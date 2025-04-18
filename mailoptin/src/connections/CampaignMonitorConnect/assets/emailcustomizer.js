(function (api, $) {
    "use strict";

    var mc = {};

    mc.add_spinner = function (placement) {
        var spinner_html = $('<img class="mo-spinner fetch-email-list" src="' + mailoptin_globals.admin_url + 'images/spinner.gif">');
        $(placement).after(spinner_html);
    };

    mc.remove_spinner = function () {
        $('.mo-spinner.fetch-email-list').remove();
    };

    mc.hide_show_segment_select_chosen = function () {
        var segments_select_obj = $("select[data-customize-setting-link*='CampaignMonitorConnect_segment'] option");

        if (segments_select_obj.length === 0) {
            $("div#customize-theme-controls li[id*='CampaignMonitorConnect_segment']").hide()
        }
    };

    mc.fetch_segments = function () {

        $("select[data-customize-setting-link*='connection_email_list']").on('change', function (e) {
            var list_id = this.value;

            if ($("select[data-customize-setting-link*='connection_service']").val() !== 'CampaignMonitorConnect') return;

            $("div#customize-theme-controls li[id*='CampaignMonitorConnect_segment']").hide();

            mc.add_spinner(this);

            $.post(ajaxurl, {
                    action: 'mailoptin_customizer_fetch_campaign_monitor_segment',
                    list_id: list_id,
                    security: $("input[data-customize-setting-link*='[ajax_nonce]']").val()
                },
                function (response) {
                    if (_.isObject(response) && 'success' in response && 'data' in response) {

                        var campaign_monitor_segment_chosen = $("select[data-customize-setting-link*='CampaignMonitorConnect_segment']");

                        campaign_monitor_segment_chosen.html(response.data);

                        campaign_monitor_segment_chosen.trigger('chosen:updated');

                        if (response.data !== '') {
                            $("div#customize-theme-controls li[id*='CampaignMonitorConnect_segment']").show();
                        }

                        mc.remove_spinner();
                    }
                }
            );
        });
    };


    $(window).on('load', function () {
        mc.hide_show_segment_select_chosen();
        mc.fetch_segments();
    });


})(wp.customize, jQuery);