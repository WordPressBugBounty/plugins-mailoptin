<?php

namespace MailOptin\BeehiivConnect;

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
        $connected = AbstractBeehiivConnect::is_connected();
        $status = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = [
            'section_title_without_status' => __('Beehiiv', 'mailoptin'),
            'section_title'                => __('Beehiiv Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/beehiiv-integration.png',
            'beehiiv_api_key'              => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sBeehiiv account%s to get your api key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.beehiiv.com/settings/workspace/api">',
                    '</a>'
                ),
            ],
            'beehiiv_publication_id'       => [
                'type'        => 'text',
                'label'       => __('Enter Publication ID', 'mailoptin'),
                'description' => sprintf(
                    __('Log in to your %sBeehiiv account%s to get your publication ID.', 'mailoptin'),
                    '<a target="_blank" href="https://app.beehiiv.com/settings/workspace/api">',
                    '</a>'
                ),
            ]
        ];

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        //Not a beehiiv connection section
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['beehiiv_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('beehiiv');

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