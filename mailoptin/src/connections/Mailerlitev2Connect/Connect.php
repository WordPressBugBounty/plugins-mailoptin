<?php

namespace MailOptin\Mailerlitev2Connect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractMailerlitev2Connect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'Mailerlitev2Connect';

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
            self::EMAIL_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT
        ];
    }

    /**
     * Register MailerLite Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('MailerLite', 'mailoptin');

        return $connections;
    }

    /**
     * Replace placeholder tags with actual MailerLite tags.
     *
     * {@inheritdoc}
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        $search = [
            '{{webversion}}',
            '{{unsubscribe}}'
        ];

        $replace = [
            '{$url}',
            '{$unsubscribe}'
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    /**
     * {@inherit_doc}
     *
     * Return array of email list
     *
     * @return mixed
     */
    public function get_email_list()
    {
        try {

            $page  = 1;
            $loop  = true;
            $limit = 100;

            $lists_array = [];

            while ($loop === true) {

                $allGroups = $this->mailerlitev2_instance()->make_request('groups', ['limit' => $limit, 'page' => $page]);

                if (isset($allGroups['body']['data'])) {

                    foreach ($allGroups['body']['data'] as $list) {
                        $lists_array[$list['id']] = $list['name'];
                    }

                    if (count($allGroups['body']['data']) < $limit) {
                        $loop = false;
                    }

                    $page++;

                } else {
                    $loop = false;
                }
            }

            return $lists_array;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'mailerlitev2');

            return [];
        }
    }

    public function get_optin_fields($list_id = '')
    {
        $custom_fields_array = [];

        try {

            $response = $this->mailerlitev2_instance()->make_request('fields', ['limit' => 100]);

            $skip = ['name', 'last_name'];

            if (isset($response['body']['data'])) {
                foreach ($response['body']['data'] as $customField) {
                    if (is_array($customField) && ! in_array($customField['key'], $skip)) {
                        $custom_fields_array[$customField['key']] = $customField['name'];
                    }
                }
            } else {
                self::save_optin_error_log(wp_json_encode($response['body']), 'mailerlitev2');
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'mailerlitev2');
        }

        return $custom_fields_array;
    }

    /**
     * @param int $email_campaign_id
     * @param int $campaign_log_id
     * @param string $subject
     * @param string $content_html
     * @param string $content_text
     *
     * @return array
     * @throws \Exception
     *
     */
    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return (new SendCampaign($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text))->send();
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $list_id ID of email list to add subscriber to
     * @param mixed|null $extras
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