<?php

namespace MailOptin\CopperConnect;

use Authifly\Provider\Copper;
use Authifly\Storage\OAuthCredentialStorage;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\PluginSettings\Connections;
use MailOptin\Core\PluginSettings\Settings;

class AbstractCopperConnect extends AbstractConnect
{
    /** @var \MailOptin\Core\PluginSettings\Settings */
    protected $plugin_settings;

    /** @var \MailOptin\Core\PluginSettings\Connections */
    protected $connections_settings;

    public function __construct()
    {
        $this->plugin_settings = Settings::instance();
        $this->connections_settings = Connections::instance();

        parent::__construct();
    }

    /**
     * @return bool
     */
    public static function is_connected()
    {
        $db_options = get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME);

        return !empty($db_options['copper_access_token']);
    }

    /**
     * @return Copper
     * @throws \Exception
     *
     */
    public function copper_instance()
    {
        $access_token = $this->connections_settings->copper_access_token();

        if (empty($access_token)) {
            throw new \Exception('Copper access token not found.');
        }

        $config = [
            'callback' => MAILOPTIN_OAUTH_URL,
            'keys' => ['key' => 'OkZY1c4sp8xALJ4fMZ9ehGiSZUfoK9vuXRucAtjYbhA', 'secret' => '_']
        ];

        return new Copper($config, null, new OAuthCredentialStorage([
            'copper.access_token' => $access_token
        ]));
    }
}