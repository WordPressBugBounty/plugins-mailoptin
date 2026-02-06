<?php

namespace MailOptin\MailercloudConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractMailercloudConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'MailercloudConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_action('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'));

        parent::__construct();
    }

    /**
     * Register Mailercloud Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Mailercloud', 'mailoptin');

        return $connections;
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
     * @param array $settings
     *
     * @return mixed
     */
    public function integration_customizer_settings($settings)
    {
        return $settings;
    }

    /**
     * @param $controls
     *
     * @return array
     */
    public function integration_customizer_controls($controls)
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM') === true) {

            $controls[] = [
                'field'       => 'chosen_select',
                'name'        => 'MailercloudConnect_lead_tags',
                'choices'     => $this->get_tags(),
                'label'       => __('Tags', 'mailoptin'),
                'description' => __('Select tags to assign to subscribers who opt-in via this campaign', 'mailoptin'),
            ];
        } else {

            $content = sprintf(
                __("Upgrade to %sMailOptin Premium%s to map custom fields and assign tags to leads.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=mailercloud_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            $controls[] = [
                'name'    => 'MailercloudConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * Replace placeholder tags with actual Mailercloud tags.
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
            '%%view_in_browser%%',
            '%%unsubscribe%%'
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

            $list_array = [];
            $page       = 1;
            $limit      = 100;
            $status     = true;

            while (true === $status) {
                $response = $this->mailercloud_instance()->post('lists/search', [
                    'limit'       => $limit,
                    'list_type'   => 1,
                    'page'        => $page,
                    'search_name' => '',
                    'sort_field'  => 'name',
                    'sort_order'  => 'asc'
                ]);

                if (isset($response['body']->data) && is_array($response['body']->data)) {
                    foreach ($response['body']->data as $list) {
                        $list_array[$list->id] = $list->name;
                    }

                    if (count($response['body']->data) < $limit) {
                        $status = false;
                    }

                    $page++;
                } else {
                    $status = false;
                }
            }

            return $list_array;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'mailercloud');

            return [];
        }
    }

    public function get_optin_fields($list_id = '')
    {
        try {

            $custom_fields_array = ['middle_name' => __('Middle Name', 'mailoptin')];

            $page   = 1;
            $limit  = 100;
            $status = true;

            $exclude_list = [
                'first_name',
                'last_name',
                'middle_name',
                'name',
                'email'
            ];

            while (true === $status) {

                $response = $this->mailercloud_instance()->post('contact/property/search', [
                    'limit'  => $limit,
                    'page'   => $page,
                    'search' => ''
                ]);

                if (isset($response['body']->data) && is_array($response['body']->data)) {

                    foreach ($response['body']->data as $customField) {
                        $field_value = $customField->field_value;
                        $field_name  = $customField->field_name;

                        if (in_array(strtolower($field_value), $exclude_list)) continue;

                        $custom_fields_array[$field_value] = $field_name;
                    }

                    if (count($response['body']->data) < $limit) {
                        $status = false;
                    }

                    $page++;
                } else {
                    $status = false;
                }
            }

            return $custom_fields_array;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'mailercloud');

            return [];
        }
    }

    /**
     * Fetch available tags from Mailercloud
     *
     * @return array
     */
    public function get_tags()
    {
        try {

            $tags_array = [];
            $page       = 1;
            $limit      = 100;
            $status     = true;

            while (true === $status) {
                $response = $this->mailercloud_instance()->post('tags/search', [
                    'limit'  => $limit,
                    'page'   => $page,
                    'search' => ''
                ]);

                if (isset($response['body']->data) && is_array($response['body']->data)) {
                    foreach ($response['body']->data as $tag) {
                        $tags_array[$tag->tag_name] = $tag->tag_name;
                    }

                    if (count($response['body']->data) < $limit) $status = false;

                    $page++;
                } else {
                    $status = false;
                }
            }

            return $tags_array;

        } catch (\Exception $e) {
            return [];
        }
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
     * Singleton poop.
     *
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
