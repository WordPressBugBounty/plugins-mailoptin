<?php

namespace MailOptin\BeehiivConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractBeehiivConnect extends AbstractConnect
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

    /**
     * Is Beehiiv successfully connected to?
     *
     * @return bool
     */
    public static function is_connected($return_error = false)
    {
        $db_options     = isset($_POST['mailoptin_connections']) ? $_POST['mailoptin_connections'] : get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $api_key        = isset($db_options['beehiiv_api_key']) ? $db_options['beehiiv_api_key'] : '';
        $publication_id = isset($db_options['beehiiv_publication_id']) ? $db_options['beehiiv_publication_id'] : '';

        //If the user has not setup beehiiv, abort early
        if (empty($api_key)) {
            delete_transient('_mo_beehiiv_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_beehiiv_is_connected');
        }

        //Check for connection status from cache
        if ('true' == get_transient('_mo_beehiiv_is_connected')) {
            return true;
        }

        try {

            $api    = new APIClass($api_key, $publication_id);
            $result = $api->make_request('publications/' . $publication_id);

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_beehiiv_is_connected', 'true', WEEK_IN_SECONDS);

                return true;
            }

            return $return_error === true ? $result['body']->message : false;

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
    public function beehiiv_instance()
    {
        $api_key = $this->connections_settings->beehiiv_api_key();
        $publication_id = $this->connections_settings->beehiiv_publication_id();

        if (empty($api_key)) {
            throw new \Exception(__('Beehiiv API Key not found.', 'mailoptin'));
        }

        if (empty($publication_id)) {
            throw new \Exception(__('Beehiiv publication ID not found.', 'mailoptin'));
        }

        return new APIClass($api_key, $publication_id);
    }
}