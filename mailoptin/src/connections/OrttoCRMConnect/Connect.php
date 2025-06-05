<?php

namespace MailOptin\OrttoCRMConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractOrttoCRMConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'OrttoCRMConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_filter('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'));

        parent::__construct();
    }

    /**
     * @return array
     */
    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT
        ];
    }

    /**
     * @param $connections
     *
     * @return mixed
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Ortto', 'mailoptin');

        return $connections;
    }

    public function integration_customizer_settings($settings)
    {
        $settings['OrttoCRMConnect_lead_tags'] = apply_filters('mailoptin_customizer_optin_campaign_OrttoCRMConnect_lead_tags', '');

        return $settings;
    }

    public function integration_customizer_controls($controls)
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM') === true) {
            $controls[] = [
                'field'       => 'text',
                'name'        => 'OrttoCRMConnect_lead_tags',
                'label'       => __('Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Comma-separated list of tags to assign to a new subscriber in Ortto', 'mailoptin'),
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you assign tags to subscribers.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=orttocrm_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'OrttoCRMConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * @param $content
     * @param $type
     *
     * @return mixed
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        return $this->replace_footer_placeholder_tags($content);
    }

    /**
     * @return array
     */
    public function get_email_list()
    {
        return ['people' => __('People', 'mailoptin')];
    }

    /**
     * @param $list_id
     *
     * @return array
     */
    public function get_optin_fields($list_id = '')
    {
        static $cache = null;

        if (is_null($cache)) {

            $custom_fields = [
                /** @see https://help.ortto.com/a-257-create-or-update-one-or-more-people-merge#Valid-request-body-elements */
                'phn::phone'    => esc_html__('Phone number', 'fusewp'),
                'str::language' => esc_html__('Language', 'fusewp'),
                'geo::country'  => esc_html__('Country', 'fusewp'),
                'geo::region'   => esc_html__('Region', 'fusewp'),
                'geo::city'     => esc_html__('City', 'fusewp'),
                'str::postal'   => esc_html__('Postal code', 'fusewp'),
                'dtz::b'        => esc_html__('Birthday', 'fusewp')
            ];

            try {

                $response = $this->orttocrm_instance()->post('person/custom-field/get');

                $fields = $response['body']['fields'] ?? [];

                if ( ! empty($fields) && is_array($fields)) {
                    foreach ($fields as $item) {
                        $field = $item['field'] ?? [];

                        $custom_fields[$field['id']] = $field['name'];
                    }
                }

            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'orttocrm');
            }

            $cache = $custom_fields;
        }

        return $cache;
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
        return [];
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
