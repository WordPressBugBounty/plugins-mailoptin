<?php

namespace MailOptin\OrttoCRMConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractOrttoCRMConnect extends AbstractConnect
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
        $api_key    = $db_options['orttocrm_api_key'] ?? '';
        $region     = $db_options['orttocrm_region'] ?? '';

        if (empty($api_key)) {
            delete_transient('_mo_orttocrm_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_orttocrm_is_connected');
        }

        // Check for connection status from cache
        if ('true' == get_transient('_mo_orttocrm_is_connected')) {
            return true;
        }

        try {
            $api    = new APIClass($api_key, $region);
            $result = $api->post('person/custom-field/get');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_orttocrm_is_connected', 'true', WEEK_IN_SECONDS);

                return true;
            }

            return $return_error === true ? $result['body']['fields'] : false;

        } catch (\Exception $e) {
            return $return_error === true ? $e->getMessage() : false;
        }
    }

    /**
     * @return APIClass
     * @throws \Exception
     */
    public function orttocrm_instance()
    {
        $api_key = $this->connections_settings->orttocrm_api_key();
        $region  = $this->connections_settings->orttocrm_region();

        if (empty($api_key)) {
            throw new \Exception(__('Ortto API Key not found.', 'mailoptin'));
        }

        return new APIClass($api_key, $region);
    }
}
