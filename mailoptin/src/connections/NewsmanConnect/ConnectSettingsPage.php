<?php

namespace MailOptin\NewsmanConnect;

class ConnectSettingsPage extends AbstractNewsmanConnect
{
    public function __construct()
    {
        parent::__construct();

        add_filter('mailoptin_connections_settings_page', array($this, 'connection_settings'));

        add_filter('wp_cspa_santized_data', [$this, 'remove_access_token_persistence'], 10, 2);
        add_action('wp_cspa_settings_after_title', array($this, 'output_error_log_link'), 10, 2);

        add_action('mailoptin_before_connections_settings_page', [$this, 'handle_access_token_persistence']);
        add_action('mailoptin_before_connections_settings_page', [$this, 'handle_integration_disconnection']);
    }

    /**
     * Build the settings metabox for constact contact
     *
     * @param array $arg
     *
     * @return array
     */
    public function connection_settings($arg)
    {
        $disconnect_integration = '';
        if (self::is_connected()) {
            $status                 = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
            $button_text            = __('RE-AUTHORIZE', 'mailoptin');
            $button_color           = 'mobtnGreen';
            $description            = sprintf(__('Only re-authorize if you want to connect another Newsman account.', 'mailoptin'));
            $disconnect_integration = sprintf(
                '<div style="text-align:center;font-size:14px;"><a onclick="return confirm(\'%s\')" href="%s">%s</a></div>',
                __('Are you sure you want to disconnect?', 'mailoptin'),
                wp_nonce_url(
                    add_query_arg('mo-integration-disconnect', 'newsman', MAILOPTIN_CONNECTIONS_SETTINGS_PAGE),
                    'mo_disconnect_integration'
                ),
                __('Disconnect Integration', 'mailoptin')
            );
        } else {
            $status       = '';
            $button_text  = __('AUTHORIZE', 'mailoptin');
            $button_color = 'mobtnPurple';
            $description  = sprintf(__('Authorization is required to grant <strong>%s</strong> access to interact with your Newsman account.', 'mailoptin'), 'MailOptin');
        }

        $settingsArg[] = array(
            'section_title_without_status' => __('Newsman', 'mailoptin'),
            'section_title'                => __('Newsman Connection', 'mailoptin') . " $status",
            'type'                         => self::EMAIL_MARKETING_TYPE,
            'logo_url'                     => MAILOPTIN_CONNECTION_ASSETS_URL . 'images/newsman-integration.jpeg',
            'newsman_auth'                 => array(
                'type'        => 'arbitrary',
                'data'        => sprintf(
                    '<div class="moBtncontainer"><a href="%s" class="mobutton mobtnPush %s">%s</a></div>%s',
                    $this->get_oauth_url('newsman'),
                    $button_color,
                    $button_text,
                    $disconnect_integration
                ),
                'description' => '<p class="description" style="text-align:center">' . $description . '</p>',
            ),
            'disable_submit_button'        => true
        );

        return array_merge($arg, $settingsArg);
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
            unset($sanitized_data['newsman_access_token']);
            unset($sanitized_data['newsman_user_id']);
        }

        return $sanitized_data;
    }

    public function handle_integration_disconnection($option_name)
    {
        if ( ! isset($_GET['mo-integration-disconnect']) || $_GET['mo-integration-disconnect'] != 'newsman' || ! check_admin_referer('mo_disconnect_integration')) return;

        $old_data = get_option($option_name, []);
        unset($old_data['newsman_access_token']);
        unset($old_data['newsman_user_id']);

        update_option($option_name, $old_data);

        $connection = Connect::$connectionName;

        // delete connection cache
        delete_transient("_mo_connection_cache_$connection");

        \MailOptin\Core\do_admin_redirect(MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
    }

    /**
     * Persist access token.
     *
     * @param string $option_name DB wp_option key for saving connection settings.
     */
    public function handle_access_token_persistence($option_name)
    {
        if ( ! empty($_GET['mo-save-oauth-provider']) && $_GET['mo-save-oauth-provider'] == 'newsman' && ! empty($_GET['access_token'])) {

            check_admin_referer('mo_save_oauth_credentials', 'moconnect_nonce');

            $old_data = get_option($option_name, []);
            $new_data = array_map('rawurldecode', [
                'newsman_access_token' => $_GET['access_token'],
                'newsman_user_id'      => absint($_GET['user_id'])
            ]);

            $new_data = array_filter($new_data, [$this, 'data_filter']);

            update_option($option_name, array_merge($old_data, $new_data));

            $connection = Connect::$connectionName;

            // delete connection cache
            delete_transient("_mo_connection_cache_$connection");

            \MailOptin\Core\do_admin_redirect(MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
        }
    }

    public function output_error_log_link($option, $args)
    {
        if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['newsman_auth'])) {
            return;
        }

        //Output error log link if  there is one
        echo self::get_optin_error_log_link('newsman');

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