<?php

namespace MailOptin\SenderConnect;

use MailOptin\Core\Connections\AbstractConnect;

class ConnectSettingsPage
{
    public function __construct()
    {
        add_filter('mailoptin_connections_settings_page', [$this, 'connection_settings']);
        add_action('wp_cspa_settings_after_title', [$this, 'output_error_log_link'], 10, 2);
    }

    public function connection_settings($arg)
    {
        $connected = AbstractSenderConnect::is_connected();
        $status    = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = [
            'section_title_without_status' => __('Sender', 'mailoptin'),
            'section_title'                => __('Sender Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/sender-integration.svg',
            'sender_api_token'             => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sSender.net account%s, go to "Settings" -> "API access tokens" to get your API key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.sender.net/settings/tokens">',
                    '</a>'
                ),
            ],
        ];

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['sender_api_token'])) {
            return;
        }

        // Output error log link if there is one
        echo AbstractConnect::get_optin_error_log_link('sender');
    }

    /**
     * @return self
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
