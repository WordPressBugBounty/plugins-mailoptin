<?php

namespace MailOptin\EnchargeConnect;

use MailOptin\Core\Connections\AbstractConnect;

class ConnectSettingsPage
{
    public function __construct()
    {
        add_filter('mailoptin_connections_settings_page', array($this, 'connection_settings'));
        add_action('wp_cspa_settings_after_title', array($this, 'output_error_log_link'), 10, 2);
    }

    public function connection_settings($arg)
    {
        $connected = AbstractEnchargeConnect::is_connected(true);
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        } else {
            $msg = '';
            if (is_string($connected)) {
                $msg = esc_html(" &mdash; $connected");
            }
            $status = sprintf("<span style='color:#FF0000'>(%s$msg) </span>", __('Not Connected', 'mailoptin'));
        }

        $settingsArg[] = [
            'section_title_without_status' => __('Encharge', 'mailoptin'),
            'section_title'                => __('Encharge Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::CRM_TYPE,
            'encharge_api_key'             => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sEncharge account%s to get your api key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.encharge.io/account/info">',
                    '</a>'
                ),
            ],
        ];

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['encharge_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('encharge');
    }

    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}