<?php

namespace MailOptin\GoogleSheetConnect;

use Authifly\Provider\Google;
use Authifly\Storage\OAuthCredentialStorage;
use Exception;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;

class AbstractGoogleSheetConnect extends AbstractConnect
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function callback_url()
    {
        return add_query_arg(['moauth' => 'gsheet'], MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
    }

    /**
     * Is Google Sheets successfully connected to?
     *
     * @return bool
     */
    public static function is_connected()
    {
        $db_options = get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);

        return ! empty($db_options['gsheet_client_id']) &&
               ! empty($db_options['gsheet_client_secret']) &&
               ! empty($db_options['gsheet_access_token']);
    }

    /**
     * @return bool
     */
    public static function is_api_saved()
    {
        $db_options = get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);

        return ! empty($db_options['gsheet_client_id']) &&
               ! empty($db_options['gsheet_client_secret']);
    }

    /**
     * @param string $apiBaseType sheet or drive
     *
     * @return Google
     * @throws Exception
     */
    public function gsheetInstance($apiBaseType = 'sheet')
    {
        $connections_settings = Connections::instance(true);
        $client_id            = $connections_settings->gsheet_client_id();
        $client_secret        = $connections_settings->gsheet_client_secret();
        $access_token         = $connections_settings->gsheet_access_token();
        $refresh_token        = $connections_settings->gsheet_refresh_token();
        $expires_at           = $connections_settings->gsheet_expires_at();

        if (empty($access_token)) {
            throw new Exception('Google access token not found.');
        }

        if (empty($refresh_token)) {
            throw new Exception('Google refresh token not found.');
        }

        $config = [
            'callback' => self::callback_url(),
            'keys'     => ['id' => $client_id, 'secret' => $client_secret]
        ];

        $instance = new Google($config, null,
            new OAuthCredentialStorage([
                'google.access_token'  => $access_token,
                'google.refresh_token' => $refresh_token,
                'google.expires_at'    => $expires_at,
            ])
        );

        if ($instance->hasAccessTokenExpired()) {

            try {

                $instance->refreshAccessToken();

                $option_name = MAILOPTIN_CONNECTIONS_DB_OPTION_NAME;
                $old_data    = get_option($option_name, []);
                $expires_at  = $this->oauth_expires_at_transform($instance->getStorage()->get('google.expires_at'));
                $new_data    = [
                    'gsheet_access_token' => $instance->getStorage()->get('google.access_token'),
                    // refreshtoken is the same as google oauth does not return a new one on token refresh
                    // See https://developers.google.com/identity/protocols/oauth2#5.-refresh-the-access-token,-if-necessary.
                    'gsheet_expires_at'   => $expires_at
                ];

                update_option($option_name, array_merge($old_data, $new_data));

                $instance = new Google($config, null,
                    new OAuthCredentialStorage([
                        'google.access_token'  => $instance->getStorage()->get('google.access_token'),
                        'google.refresh_token' => $instance->getStorage()->get('google.refresh_token'),
                        'google.expires_at'    => $expires_at,
                    ]));

            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }

        if ('sheet' === $apiBaseType) {
            $instance->apiBaseUrl = 'https://sheets.googleapis.com/v4/spreadsheets/';
        }

        return $instance;
    }
}