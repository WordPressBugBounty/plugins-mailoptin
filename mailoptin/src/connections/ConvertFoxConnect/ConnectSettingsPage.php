<?php

namespace MailOptin\ConvertFoxConnect;

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
        $connected = AbstractConvertFoxConnect::is_connected();
        $status = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = array(
            'section_title_without_status' => __('Gist', 'mailoptin'),
            'section_title'                => __('Gist Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/gist-integration.jpeg',
            'convertfox_api_key'           => array(
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Learn how to %sget your Gist API Key%s.', 'mailoptin'),
                    '<a target="_blank" href="https://mailoptin.io/article/connect-mailoptin-with-convertfox/?utm_source=wp_dashboard&utm_medium=connections_page&utm_campaign=convertfox">',
                    '</a>'
                ),
            )
        );

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        //Not a convertfox connection section
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['convertfox_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('convertfox');

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