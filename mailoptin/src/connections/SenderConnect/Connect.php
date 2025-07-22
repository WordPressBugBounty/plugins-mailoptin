<?php

namespace MailOptin\SenderConnect;

use MailOptin\Connections\SenderConnect\SendCampaign;
use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractSenderConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'SenderConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        parent::__construct();
    }

    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT,
            self::EMAIL_CAMPAIGN_SUPPORT
        ];
    }

    /**
     * @param $connections
     *
     * @return mixed
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Sender', 'mailoptin');

        return $connections;
    }

    public function replace_placeholder_tags($content, $type = 'html')
    {
        $search = [
            '{{unsubscribe}}',
            '{{webversion}}',
        ];

        $replace = [
            '{$unsubscribe_link}',
            '[BROWSER_PREVIEW]'
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    public function get_email_list()
    {
        $lists = get_transient('mo_sender_get_email_list');

        try {

            if (empty($lists)) {
                $response = $this->sender_instance()->make_request('groups');

                $lists = [];

                if ( ! empty($response['body']->data)) {
                    foreach ($response['body']->data as $group) {
                        $lists[$group->id] = $group->title;
                    }
                }

                set_transient('mo_sender_get_email_list', $lists, HOUR_IN_SECONDS);
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'sender');
        }

        return $lists;
    }

    /**
     * @param string $list_id
     *
     * @return array
     */
    public function get_optin_fields($list_id = '')
    {
        $bucket = [];

        try {
            $response = $this->sender_instance()->make_request('fields');

            if ( ! empty($response['body']->data)) {
                foreach ($response['body']->data as $field) {

                    $key = preg_replace('/^{{(.+)}}$/', '$1', $field->name);

                    if (in_array($key, ['email', 'firstname', 'lastname'])) {
                        continue;
                    }

                    $bucket[$key] = $field->title;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'sender');
        }

        return $bucket;
    }

    /**
     * @param $email_campaign_id
     * @param $campaign_log_id
     * @param $subject
     * @param $content_html
     * @param $content_text
     *
     * @return array
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
     * @return array|mixed
     */
    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
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
