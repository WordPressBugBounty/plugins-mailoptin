<?php

namespace MailOptin\BentoConnect;

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
        $connected = AbstractBentoConnect::is_connected();
        $status    = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = [
            'section_title_without_status' => __('Bento', 'mailoptin'),
            'section_title'                => __('Bento Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/bento-integration.png',
            'bento_publishable_key'        => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter Publishable Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sBento account%s to get your publishable key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.bentonow.com/">',
                    '</a>'
                ),
            ],
            'bento_secret_key'             => [
                'type'          => 'password',
                'obfuscate_val' => true,
                'label'         => __('Enter Secret Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sBento account%s to get your secret key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.bentonow.com/">',
                    '</a>'
                ),
            ],
            'bento_site_uuid'             => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter Site UUID', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sBento account%s to get your site UUID.', 'mailoptin'),
                    '<a target="_blank" href="https://app.bentonow.com/">',
                    '</a>'
                ),
            ],
        ];

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['bento_publishable_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('bento');
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