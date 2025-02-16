<?php

namespace MailOptin\GoogleSheetConnect;

use MailOptin\Core\Connections\AbstractConnect;

use function MailOptin\Core\moVar;

class ConnectSettingsPage extends AbstractGoogleSheetConnect
{
    public function __construct()
    {
        parent::__construct();

        add_filter('mailoptin_connections_settings_page', array($this, 'connection_settings'));

        add_filter('wp_cspa_santized_data', [$this, 'remove_access_token_persistence'], 10, 2);
        add_action('wp_cspa_settings_after_title', array($this, 'output_error_log_link'), 10, 2);

        add_action('mailoptin_before_connections_settings_page', [$this, 'handle_integration_disconnection']);

        add_action('admin_init', [$this, 'clear_connection_cache']);
    }

    /**
     * @param array $arg
     *
     * @return array
     */
    public function connection_settings($arg)
    {
        if ( ! defined('MAILOPTIN_DETACH_LIBSODIUM')) {

            $url = 'https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=googlesheet';

            $settingsArg[] = [
                'section_title'         => __('Google Sheets', 'mailoptin'),
                'type'                  => AbstractConnect::OTHER_TYPE,
                'gsheet_instruction'    => [
                    'type' => 'arbitrary',
                    'data' => sprintf(
                        '<p style="text-align:center;font-size: 15px;" class="description">%s</p><div class="moBtncontainer"><a target="_blank" href="%s" style="padding:0;margin: 0 auto;" class="mobutton mobtnPush mobtnGreen">%s</a></div>',
                        __('Google Sheets integration is not available on your plan. Upgrade to any of our premium offering to get it.', 'mailoptin'),
                        $url,
                        __('Upgrade Now!', 'mailoptin')
                    )
                ],
                'disable_submit_button' => true
            ];

            return array_merge($arg, $settingsArg);
        }

        if (self::is_connected()) {
            $status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
        } else {
            $status = sprintf('<span style="color:#FF0000">(%s)</span>', __('Not Connected', 'mailoptin'));
        }

        $disconnect_integration = sprintf(
            '<div style="text-align:center;font-size:14px;margin-top:20px;"><a class="button" onclick="return confirm(\'%s\')" href="%s">%s</a></div>',
            __('Are you sure you want to disconnect?', 'mailoptin'),
            wp_nonce_url(
                add_query_arg('mo-integration-disconnect', 'googlesheet', MAILOPTIN_CONNECTIONS_SETTINGS_PAGE),
                'mo_disconnect_integration'
            ),
            __('Disconnect Integration', 'mailoptin')
        );

        $doc_url = 'https://mailoptin.io/article/connect-wordpress-with-google-sheet/';
        $html    = '<ol>';
        $html    .= '<li>' . sprintf(esc_html__('Create a Google API project in %1$sGoogle console%2$s.', 'mailoptin'), '<a href="https://console.cloud.google.com/apis" target="_blank" rel="noopener">', '</a>') . '</li>';
        $html    .= '<li>' . sprintf(esc_html__('Copy the %1$sproject keys%2$s and include them below.', 'mailoptin'), '<a href="' . esc_url($doc_url) . '" target="_blank" rel="noopener">', '</a>') . '</li>';
        $html    .= '<li>' . sprintf(esc_html__('Use %1$s as the Authorized Redirect URI.', 'mailoptin'), '<code>' . admin_url('admin.php?page=mailoptin-integrations&moauth=gsheet') . '</code>');
        $html    .= '<li>' . sprintf(esc_html__('Click the Authorize button to complete the connection. %sLearn more%s', 'mailoptin'), '<a href="' . $doc_url . '" target="_blank">', '</a>') . '</li>';
        $html    .= '</ol>';

        $settingsArg = [
            'section_title_without_status' => __('Google Sheets', 'mailoptin'),
            'section_title'                => __('Google Sheets Connection', 'mailoptin') . " $status",
            'type'                         => self::OTHER_TYPE,
            'gsheet_info'                  => [
                'type' => 'arbitrary',
                'data' => ''
            ],
            'gsheet_instruction'           => [
                'type' => 'arbitrary',
                'data' => $html
            ],
            'gsheet_client_id'             => [
                'type'  => 'text',
                'label' => __('Client ID', 'mailoptin')
            ],
            'gsheet_client_secret'         => [
                'type'          => 'text',
                'obfuscate_val' => true,
                'label'         => __('Client Secret', 'mailoptin')
            ],
            'gsheet_auth_connect'          => [
                'type' => 'arbitrary',
                'data' => sprintf(
                    '<div class="moBtncontainer"><a href="%s" class="mobutton mobtnPush %s">%s</a></div>',
                    self::callback_url(),
                    'mobtnPurple',
                    __('AUTHORIZE YOUR ACCOUNT', 'mailoptin')
                ),
            ],
            'gsheet_auth_disconnect'       => [
                'type' => 'arbitrary',
                'data' => $disconnect_integration
            ],
            'gsheet_clear_cache'           => [
                'type' => 'arbitrary',
                'data' => sprintf(
                    '<div class="mo-connection-clear-cache-wrap"><a href="%s">%s</a></div>',
                    esc_url(wp_nonce_url(add_query_arg('mo-connection-clear-cache', 'googlesheet'), 'mo-connection-clear-googlesheet-cache')),
                    esc_html__('Clear Cache', 'mailoptin')
                )
            ]
        ];

        if (self::is_connected()) {
            unset($settingsArg['gsheet_instruction']);
        }

        if (( ! self::is_connected() && ! self::is_api_saved()) || self::is_connected()) {
            unset($settingsArg['gsheet_auth_connect']);
        }

        if (self::is_api_saved()) {
            unset($settingsArg['gsheet_client_id']);
            unset($settingsArg['gsheet_client_secret']);
            $settingsArg['disable_submit_button'] = true;
        } else {
            unset($settingsArg['gsheet_auth_disconnect']);
            unset($settingsArg['gsheet_clear_cache']);
        }

        return array_merge($arg, [$settingsArg]);
    }

    public function clear_connection_cache()
    {
        if (moVar($_GET, 'mo-connection-clear-cache') != 'googlesheet') return;

        check_admin_referer('mo-connection-clear-googlesheet-cache');

        delete_transient('mo_connections_google_sheet_files');
        delete_transient('mo_connections_google_sheets_columns');
        delete_transient('mo_connections_google_sheet_files_sheets');

        wp_safe_redirect(MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
        exit;
    }

    /**
     * Prevent access token from being overridden when settings page is saved.
     *
     * @param array $sanitized_data
     * @param string $option_name
     *
     * @return mixed
     */
    public function remove_access_token_persistence($sanitized_data, $option_name)
    {
        // remove the access token from being overridden on save of settings.
        if ($option_name == MAILOPTIN_CONNECTIONS_DB_OPTION_NAME) {
            unset($sanitized_data['gsheet_access_token']);
            unset($sanitized_data['gsheet_refresh_token']);
            unset($sanitized_data['gsheet_expires_at']);
        }

        return $sanitized_data;
    }

    public function handle_integration_disconnection($option_name)
    {
        if ( ! isset($_GET['mo-integration-disconnect']) || $_GET['mo-integration-disconnect'] != 'googlesheet' || ! check_admin_referer('mo_disconnect_integration')) return;

        $old_data = get_option($option_name, []);
        unset($old_data['gsheet_client_id']);
        unset($old_data['gsheet_client_secret']);
        unset($old_data['gsheet_access_token']);
        unset($old_data['gsheet_refresh_token']);
        unset($old_data['gsheet_expires_at']);

        update_option($option_name, $old_data);

        $connection = Connect::$connectionName;
        // delete connection cache
        delete_transient("_mo_connection_cache_$connection");

        \MailOptin\Core\do_admin_redirect(MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
    }

    public function output_error_log_link($option, $args)
    {
        //Not a gsheet connection section
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['gsheet_info'])) return;

        echo self::get_optin_error_log_link('googlesheet');
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
