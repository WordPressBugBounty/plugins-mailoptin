<?php

namespace MailOptin\EnchargeConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractEnchargeConnect extends AbstractConnect
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
        $db_options = $_POST['mailoptin_connections'] ?? get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $api_key    = $db_options['encharge_api_key'] ?? '';

        if (empty($api_key)) {
            delete_transient('_mo_encharge_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_encharge_is_connected');
        }

        //Check for connection details from cache
        if ('true' == get_transient('_mo_encharge_is_connected')) {
            return true;
        }

        try {
            $api    = new APIClass($api_key);
            $result = $api->make_request('/accounts/info');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_encharge_is_connected', 'true', WEEK_IN_SECONDS);

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
    public function encharge_instance()
    {
        $api_key = $this->connections_settings->encharge_api_key();

        if (empty($api_key)) {
            throw new \Exception('Encharge API key not found.');
        }

        return new APIClass($api_key);
    }
}
