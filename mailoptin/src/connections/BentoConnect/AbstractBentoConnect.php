<?php

namespace MailOptin\BentoConnect;

use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractBentoConnect extends AbstractConnect
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
        $db_options      = $_POST['mailoptin_connections'] ?? get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);
        $publication_key = $db_options['bento_publishable_key'] ?? '';
        $secret_key      = $db_options['bento_secret_key'] ?? '';
        $site_uuid       = $db_options['bento_site_uuid'] ?? '';

        if (empty($publication_key)) {
            delete_transient('_mo_bento_is_connected');

            return false;
        }

        if (isset($_POST['wp_csa_nonce'])) {
            delete_transient('_mo_bento_is_connected');
        }

        //Check for connection details from cache
        if ('true' == get_transient('_mo_bento_is_connected')) {
            return true;
        }

        try {
            $api    = new APIClass($publication_key, $secret_key, $site_uuid);
            $result = $api->make_request('stats/site');

            if (self::is_http_code_success($result['status_code'])) {
                set_transient('_mo_bento_is_connected', 'true', WEEK_IN_SECONDS);

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
    public function bento_instance()
    {
        $publishable_key = $this->connections_settings->bento_publishable_key();
        $secret_key      = $this->connections_settings->bento_secret_key();
        $site_uuid       = $this->connections_settings->bento_site_uuid();

        if (empty($publishable_key)) throw new \Exception('Bento Publishable Key not found.');

        if (empty($secret_key)) throw new \Exception('Bento Secret Key not found.');

        if (empty($site_uuid)) throw new \Exception('Bento Site UUID not found.');

        return new APIClass($publishable_key, $secret_key, $site_uuid);
    }
}
