<?php

namespace MailOptin\SendpulseConnect;

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
        $connected = AbstractSendpulseConnect::is_connected();
        $status    = '';
        if (true === $connected) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        }

        $settingsArg = [
            'section_title_without_status' => __('SendPulse', 'mailoptin'),
            'section_title'                => __('SendPulse Connection', 'mailoptin') . " $status",
            'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/sendpulse-integration.svg',
            'sendpulse_client_id'          => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Enter Client ID', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sSendPulse account%s to get your Client ID.', 'mailoptin'),
                    '<a target="_blank" href="https://login.sendpulse.com/settings">',
                    '</a>'
                ),
            ],
            'sendpulse_client_secret'      => [
                'type'          => 'password',
                'obfuscate_val' => true,
                'label'         => __('Enter Client Secret', 'mailoptin'),
                'description'   => sprintf(
                    __('Log in to your %sSendPulse account%s to get your Client Secret.', 'mailoptin'),
                    '<a target="_blank" href="https://login.sendpulse.com/settings">',
                    '</a>'
                ),
            ]
        ];

        $settingsArg['sendpulse_doi_message_lang'] = [
            'type'    => 'select',
            'label'   => __('Double-Optin Email Language', 'mailoptin'),
            'options' => [
                'en' => esc_html__('English', 'mailoptin'),
                'ru' => esc_html__('Russian', 'mailoptin'),
                'ua' => esc_html__('Ukrainian', 'mailoptin'),
                'tr' => esc_html__('Turkish', 'mailoptin'),
                'es' => esc_html__('Spanish', 'mailoptin'),
                'pt' => esc_html__('Portuguese', 'mailoptin')
            ]
        ];

        $settingsArg['sendpulse_doi_template_id'] = [
            'type'        => 'text',
            'label'       => __('Confirmation Template ID (optional)', 'mailoptin'),
            'description' => sprintf(
                esc_html__('Optional. Confirmation email template ID from %sSendPulse Service Settings%s. Leave empty to use default.', 'mailoptin'),
                '<a target="_blank" href="https://login.sendpulse.com/emailservice/confirmation-letters/">', '</a>'
            ),
        ];

        return array_merge($arg, [$settingsArg]);
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['sendpulse_publishable_key'])) {
            return;
        }

        //Output error log link if there is one
        echo AbstractConnect::get_optin_error_log_link('sendpulse');
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