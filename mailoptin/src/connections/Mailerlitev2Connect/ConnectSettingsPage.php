<?php

namespace MailOptin\Mailerlitev2Connect;

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

        $connected = AbstractMailerlitev2Connect::is_connected();
        $status = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = array(
            'section_title_without_status' => __('MailerLite', 'mailoptin'),
            'section_title'                => __('MailerLite Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/mailerlite-integration.png',
            'mailerlitev2_api_key'         => array(
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sMailerLite account%s to get your API Key.', 'mailoptin'),
                    '<a target="_blank" href="https://dashboard.mailerlite.com/integrations/api">',
                    '</a>'
                ),
            )
        );

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        //Not a mailerlitev2 connection section
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['mailerlitev2_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('mailerlitev2');

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