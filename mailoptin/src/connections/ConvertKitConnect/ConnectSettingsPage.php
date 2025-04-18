<?php

namespace MailOptin\ConvertKitConnect;

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
        $connected = AbstractConvertKitConnect::is_connected();
        $status = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg[] = array(
            'section_title_without_status' => 'Kit (ConvertKit)',
            'section_title'                => __('Kit (ConvertKit) Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/kit-integration.svg',
            'convertkit_api_key'           => array(
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Key', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sKit (ConvertKit) account%s to get your api key.', 'mailoptin'),
                    '<a target="_blank" href="https://app.kit.com/account_settings/developer_settings">',
                    '</a>'
                ),
            ),
            'convertkit_api_secret'        => array(
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter API Secret', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sKit (ConvertKit) account%s to get your api secret.', 'mailoptin'),
                    '<a target="_blank" href="https://app.kit.com/account_settings/developer_settings">',
                    '</a>'
                ),
            ),
            'convertkit_template_name'     => array(
                'type'          => 'text',
                'label'         => __('Email Template Name', 'mailoptin'),
                'description'   => sprintf(
                    __('The name of the template to use for all emails we send to your Kit (ConvertKit) subscribers. Leave blank to use your account default. %sLearn more%s.', 'mailoptin'),
                    '<a target="_blank" href="https://mailoptin.io/article/connect-mailoptin-with-convertkit/?utm_source=wp_dashboard&utm_medium=integrations_setting_page&utm_campaign=convertkit">',
                    '</a>'
                ),
            )
        );

        return array_merge($arg, $settingsArg);
    }

    public function output_error_log_link($option, $args)
    {
        //Not a convertkit connection section
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['convertkit_api_key'])) {
            return;
        }

        //Output error log link if  there is one
        echo AbstractConnect::get_optin_error_log_link('convertkit');

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