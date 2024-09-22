<?php

namespace MailOptin\ElasticEmailConnect;

use MailOptin\Connections\ElasticEmailConnect\SendCampaign;
use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractElasticEmailConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'ElasticEmailConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', [$this, 'register_connection']);

        parent::__construct();
    }

    /**
     * Register ElasticEmail Email Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Elastic Email', 'mailoptin');

        return $connections;
    }

    /**
     * @return array
     */
    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::EMAIL_CAMPAIGN_SUPPORT,
        ];
    }

    /**
     * @param $content
     * @param $type
     *
     * @return mixed
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        $search = [
            '{{webversion}}',
            '{{unsubscribe}}'
        ];

        $replace = [
            '{view}',
            '{unsubscribe}',
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    /**
     * @return mixed
     */
    public function get_email_list()
    {
        $list = [];

        try {

            $response = $this->elasticemail_instance()->make_request('lists/', ['limit' => 500]);

            if (isset($response['body'])) {

                foreach ($response['body'] as $item) {
                    $list[$item['ListName']] = $item['ListName'];
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'elasticemail');
        }

        return $list;
    }

    /**
     * @param $list_id
     *
     * @return mixed
     */
    public function get_optin_fields($list_id = '')
    {
        return [];
    }

    /**
     * @param $email_campaign_id
     * @param $campaign_log_id
     * @param $subject
     * @param $content_html
     * @param $content_text
     *
     * @return mixed
     */
    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return (new SendCampaign($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text))->send();
    }

    /**
     * @param $email
     * @param $name
     * @param $list_id
     * @param $extras
     *
     * @return mixed
     */
    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras))->subscribe();
    }

    /**
     * @return Connect|null
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
