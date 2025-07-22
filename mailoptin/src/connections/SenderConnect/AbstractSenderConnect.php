<?php

namespace MailOptin\SenderConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractSenderConnect extends AbstractConnect
{
    /** @var Settings */
    protected $plugin_settings;

    /** @var Connections */
    protected $connections_settings;

    public function __construct()
    {
        $this->plugin_settings = Settings::instance();
        $this->connections_settings = Connections::instance();

        parent::__construct();
    }

    /**
     * @param bool $return_error
     *
     * @return bool
     */
    public static function is_connected($return_error = false)
    {
        $db_options = $_POST['mailoptin_connections'] ?? get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $api_token = $db_options['sender_api_token'] ?? '';

        if (empty($api_token)) {
            delete_transient('_mo_sender_is_connected');
            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_sender_is_connected');
        }

        // Check for connection details from cache
        if ('true' == get_transient('_mo_sender_is_connected')) {
            return true;
        }

        try {
            $api = new APIClass($api_token);
            $result = $api->make_request('subscribers');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_sender_is_connected', 'true', WEEK_IN_SECONDS);
                return true;
            }

            return $return_error === true ? $result['body']->message ?? 'Connection failed' : false;

        } catch (\Exception $e) {
            return $return_error === true ? $e->getMessage() : false;
        }
    }

    /**
     * @throws \Exception
     */
    public function sender_instance()
    {
        $api_key = $this->connections_settings->sender_api_token();

        if (empty($api_key)) {
            throw new \Exception('Sender.net API key not found.');
        }

        return new APIClass($api_key);
    }
}