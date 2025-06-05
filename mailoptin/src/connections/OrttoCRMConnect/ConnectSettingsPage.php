<?php

namespace MailOptin\OrttoCRMConnect;

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
        $connected = AbstractOrttoCRMConnect::is_connected();
        $status    = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = [
            'section_title_without_status' => __('Ortto', 'mailoptin'),
            'section_title'                => __('Ortto Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::CRM_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/ortto-integration.svg',
            'orttocrm_api_key'             => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your Ortto account to get your api key. %sLearn more%s', 'mailoptin'),
                    '<a target="_blank" href="https://mailoptin.io/article/connect-wordpress-ortto/">',
                    '</a>'
                ),
            ],
            'orttocrm_region'              => [
                'type'        => 'select',
                'label'       => __('Select Region', 'mailoptin'),
                'options'     => [
                    'eu'     => __('Europe (EU)', 'mailoptin'),
                    'au'     => __('Australia (AU)', 'mailoptin'),
                    'others' => __('Rest of the World', 'mailoptin'),
                ],
                'description' => __('Select the Ortto region where your account is hosted.', 'mailoptin'),
            ]
        ];

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['orttocrm_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('orttocrm');
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
