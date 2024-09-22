<?php

namespace MailOptin\BeehiivConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractBeehiivConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'BeehiivConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_filter('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'));
        add_filter('mo_optin_integrations_advance_controls', [$this, 'customizer_advance_controls']);

        add_filter('mo_connections_with_advance_settings_support', function ($val) {
            $val[] = self::$connectionName;

            return $val;
        });

        parent::__construct();
    }

    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT
        ];
    }

    /**
     * Register Beehiiv Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Beehiiv', 'mailoptin');

        return $connections;
    }

    /**
     * @param array $settings
     *
     * @return mixed
     */
    public function integration_customizer_settings($settings)
    {
        $settings['BeehiivConnect_lead_tags']            = '';
        $settings['BeehiivConnect_enable_welcome_email'] = false;

        return $settings;
    }

    /**
     * @param array $controls
     *
     * @return mixed
     */
    public function integration_customizer_controls($controls)
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM') === true) {

            $controls[] = [
                'field'       => 'text',
                'name'        => 'BeehiivConnect_lead_tags',
                'label'       => __('Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Comma-separated list of tags to assign to a new subscriber in Beehiiv', 'mailoptin'),
            ];

            $controls[] = [
                'field'       => 'toggle',
                'name'        => 'BeehiivConnect_enable_welcome_email',
                'label'       => __('Send Welcome Email', 'mailoptin'),
                'description' => __("Enable to send welcome email to new subscriebrs.", 'mailoptin')
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you assign tags to subscribers and send welcome email.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=beehiiv_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'BeehiivConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * @param $controls
     *
     * @return array
     */
    public function customizer_advance_controls($controls)
    {
        // always prefix with the name of the connect/connection service.
        $controls[] = [
            'field'   => 'select',
            'choices' => ['' => '&mdash;&mdash;&mdash;'] + $this->get_optin_fields(),
            'name'    => 'BeehiivConnect_name_field_key',
            'label'   => __('Name Custom Field', 'mailoptin')
        ];

        return $controls;
    }

    /**
     * Replace placeholder tags with actual Beehiiv merge tags.
     *
     * {@inheritdoc}
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
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
        $lists = ['all' => __('Free Tier', 'mailoptin')];

        try {

            $response = $this->beehiiv_instance()->make_request('publications/{publicationId}/tiers');

            if (isset($response['body']->data) && is_array($response['body']->data)) {

                foreach ($response['body']->data as $tier) {

                    $lists[$tier->id] = $tier->name;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'beehiiv');
        }

        return $lists;
    }

    public function get_optin_fields($list_id = '')
    {
        static $cache = null;

        if (is_null($cache)) {

            $custom_fields_array = [];

            try {

                $response = $this->beehiiv_instance()->make_request('publications/{publicationId}/custom_fields');

                if (isset($response['body']->data) && is_array($response['body']->data)) {

                    foreach ($response['body']->data as $customField) {

                        $custom_fields_array[$customField->display] = $customField->display;
                    }
                }

            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'beehiiv');
            }

            $cache = $custom_fields_array;
        }

        return $cache;
    }

    /**
     *
     * {@inheritdoc}
     *
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
        return [];
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