<?php

namespace MailOptin\SendpulseConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractSendpulseConnect extends AbstractConnect
{
    /** @var Settings */
    protected $plugin_settings;

    /** @var Connections */
    protected $connections_settings;

    public function __construct()
    {
        $this->plugin_settings      = Settings::instance();
        $this->connections_settings = Connections::instance();

        parent::__construct();
    }

    public static function is_connected($return_error = false)
    {
        $db_options    = $_POST['mailoptin_connections'] ?? get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $client_id     = $db_options['sendpulse_client_id'] ?? '';
        $client_secret = $db_options['sendpulse_client_secret'] ?? '';

        if (empty($client_id)) {
            delete_transient('_mo_sendpulse_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_sendpulse_is_connected');
        }

        //Check for connection details from cache
        if ('true' == get_transient('_mo_sendpulse_is_connected')) {
            return true;
        }

        try {
            $api    = new APIClass($client_id, $client_secret);
            $result = $api->make_request('user/info');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_sendpulse_is_connected', 'true', WEEK_IN_SECONDS);

                return true;
            }

            return $return_error === true ? $result['body']->message : false;

        } catch (\Exception $e) {
            return $return_error === true ? $e->getMessage() : false;
        }
    }

    /**
     * @throws \Exception
     */
    public function sendpulse_instance()
    {
        $client_id     = $this->connections_settings->sendpulse_client_id();
        $client_secret = $this->connections_settings->sendpulse_client_secret();

        if (empty($client_id)) throw new \Exception('SendPulse Client ID not found.');

        if (empty($client_secret)) throw new \Exception('SendPulse Client Secret not found.');

        return new APIClass($client_id, $client_secret);
    }
}
