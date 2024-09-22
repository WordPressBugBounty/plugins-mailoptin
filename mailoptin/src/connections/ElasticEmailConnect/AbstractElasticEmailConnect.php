<?php

namespace MailOptin\ElasticEmailConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;

class AbstractElasticEmailConnect extends AbstractConnect
{
    /** @var Connections */
    protected $connections_settings;

    public function __construct()
    {
        $this->connections_settings = Connections::instance();

        parent::__construct();
    }

    /**
     * @return bool
     */
    public static function is_connected($return_error = false)
    {
        $db_options = isset($_POST['mailoptin_connections']) ? $_POST['mailoptin_connections'] : get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $api_key    = isset($db_options['elasticemail_api_key']) ? $db_options['elasticemail_api_key'] : '';

        //If the user has not setup Benchmark Email, abort early
        if (empty($api_key)) {
            delete_transient('_mo_elasticemail_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_elasticemail_is_connected');
        }

        //Check for connection status from cache
        if ('true' == get_transient('_mo_elasticemail_is_connected')) {
            return true;
        }

        try {
            $api = new APIClass($api_key);

            $result = $api->make_request('lists', array('limit' => 1));

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_elasticemail_is_connected', 'true', WEEK_IN_SECONDS);

                return true;
            }

            return $return_error === true ? $result['body']->Error : false;

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
    public function elasticemail_instance()
    {
        $api_key = $this->connections_settings->elasticemail_api_key();

        if (empty($api_key)) {
            throw new \Exception(__('Elastic Email API Key not found.', 'mailoptin'));
        }

        return new APIClass($api_key);
    }
}
