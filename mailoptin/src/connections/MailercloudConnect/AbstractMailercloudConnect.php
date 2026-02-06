<?php

namespace MailOptin\MailercloudConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractMailercloudConnect extends AbstractConnect
{
    /** @var Settings */
    protected $plugin_settings;

    /** @var Connections */
    protected $connections_settings;

    protected $api_key;

    public function __construct()
    {
        $this->plugin_settings      = Settings::instance();
        $this->connections_settings = Connections::instance();
        $this->api_key              = $this->connections_settings->mailercloud_api_key();

        parent::__construct();
    }

    /**
     * Is Mailercloud successfully connected?
     *
     * @param bool $return_error
     *
     * @return bool
     */
    public static function is_connected($return_error = false)
    {
        $db_options = $_POST['mailoptin_connections'] ?? get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $api_key    = $db_options['mailercloud_api_key'] ?? '';

        if (empty($api_key)) {
            delete_transient('_mo_mailercloud_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_mailercloud_is_connected');
        }

        //Check for connection status from cache
        if ('true' == get_transient('_mo_mailercloud_is_connected')) {
            return true;
        }

        try {

            $api = new APIClass($api_key);

            $result = $api->get('client/plan');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_mailercloud_is_connected', 'true', WEEK_IN_SECONDS);

                return true;
            }

            if ($return_error === true) {

                if (isset($result['body']->errors[0]->message)) {
                    return $result['body']->errors[0]->message;
                }

                return 'Unknown error';
            }

            return false;

        } catch (\Exception $e) {

            return $return_error === true ? $e->getMessage() : false;
        }
    }

    /**
     * Returns instance of API class.
     *
     * @return APIClass
     * @throws \Exception
     *
     */
    public function mailercloud_instance()
    {
        $api_key = $this->connections_settings->mailercloud_api_key();

        if (empty($api_key)) {
            throw new \Exception(__('Mailercloud API Key not found.', 'mailoptin'));
        }

        return new APIClass($api_key);
    }
}
