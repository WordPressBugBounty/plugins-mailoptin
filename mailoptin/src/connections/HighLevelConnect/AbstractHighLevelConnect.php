<?php

namespace MailOptin\HighLevelConnect;

use Authifly\Provider\HighLevel;
use Authifly\Storage\OAuthCredentialStorage;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;

class AbstractHighLevelConnect extends AbstractConnect
{
    public $locationId = '';

    /**
     * Is highlevel successfully connected to?
     *
     * @return bool
     */
    public static function is_connected()
    {
        $db_options = get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);

        return ! empty($db_options['highlevel_access_token']);
    }

    /**
     * Return instance of highlevel class.
     *
     * @return HighLevel|mixed
     * @throws \Exception
     *
     */
    public function highlevelInstance()
    {
        $connections_settings = Connections::instance(true);
        $access_token         = $connections_settings->highlevel_access_token();
        $refresh_token        = $connections_settings->highlevel_refresh_token();
        $expires_at           = $connections_settings->highlevel_expires_at();
        $this->locationId     = $connections_settings->highlevel_locationId();

        if (empty($access_token)) {
            throw new \Exception(__('HighLevel access token not found.', 'mailoptin'));
        }

        $config = [
            // secret key and callback not needed but authifly requires they have a value hence the MAILOPTIN_OAUTH_URL constant and "__"
            'callback' => MAILOPTIN_OAUTH_URL,
            'keys'     => ['id' => '6600557433000f847442ab62-lub121g5', 'secret' => '__'],
            'scope'    => 'contacts',
        ];

        $instance = new HighLevel($config, null,
            new OAuthCredentialStorage([
                'highlevel.access_token'  => $access_token,
                'highlevel.refresh_token' => $refresh_token,
                'highlevel.expires_at'    => $expires_at,
            ]));

        if ($instance->hasAccessTokenExpired()) {

            try {

                $result = $this->oauth_token_refresh('gohl', $refresh_token);

                $option_name = MAILOPTIN_CONNECTIONS_DB_OPTION_NAME;
                $old_data    = get_option($option_name, []);

                $expires_at = $this->oauth_expires_at_transform($result['data']['expires_at']);
                $new_data   = [
                    'highlevel_access_token'  => $result['data']['access_token'],
                    'highlevel_refresh_token' => $result['data']['refresh_token'],
                    'highlevel_expires_at'    => $expires_at
                ];

                update_option($option_name, array_merge($old_data, $new_data));

                $instance = new HighLevel($config, null,
                    new OAuthCredentialStorage([
                        'highlevel.access_token'  => $result['data']['access_token'],
                        'highlevel.refresh_token' => $result['data']['refresh_token'],
                        'highlevel.expires_at'    => $expires_at
                    ]));

            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        return $instance;
    }

    /**
     * @throws \Exception
     */
    public function make_request($url, $method = 'GET', $parameters = [])
    {
        $instance = $this->highlevelInstance();

        $headers = [
            'Content-Type' => 'application/json',
            'Version'      => '2021-07-28',
        ];

        $url = str_replace('{locationId}', $this->locationId, $url);

        $parameters = array_map(function ($val) {

            if ($val == "{locationId}") $val = $this->locationId;

            return $val;

        }, $parameters);


        return $instance->apiRequest($url, $method, $parameters, $headers);
    }
}