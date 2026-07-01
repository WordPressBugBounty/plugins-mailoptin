<?php

namespace MailOptin\CopperConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractCopperConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'CopperConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_filter('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'));

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
     * @param array $settings
     *
     * @return mixed
     */
    public function integration_customizer_settings($settings)
    {
        $settings['CopperConnect_lead_tags'] = apply_filters('mailoptin_customizer_optin_campaign_CopperConnect_lead_tags', '');

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
            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'field' => 'text',
                'name' => 'CopperConnect_lead_tags',
                'label' => __('Lead Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Enter comma-separated list of tags to assign to subscribers who opt-in via this campaign.', 'mailoptin'),
            ];

        } else {

            $content = sprintf(
                __("Upgrade to %sMailOptin Premium%s to apply tags to leads as well as get access to loads of conversion features.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=copper_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            $controls[] = [
                'name' => 'CopperConnect_upgrade_notice',
                'field' => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * Register MailChimp Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Copper CRM', 'mailoptin');

        return $connections;
    }

    /**
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
        return [
            'people' => esc_html__('People', 'fusewp'),
            'leads' => esc_html__('Leads', 'fusewp'),
        ];
    }

    public function get_optin_fields($list_id = '')
    {
        $custom_fields_array = [];

        // gotten from api response of person/lead model eg https://developer.copper.com/people/fetch-a-person-by-id.html
        // and https://developer.copper.com/leads/fetch-a-lead-by-id.html
        $core_fields = [
            'middle_name' => 'Middle Name',
            'prefix' => 'Prefix',
            'suffix' => 'Suffix',
            'title' => 'Title',

            'address.street' => 'Street (Address)',
            'address.city' => 'City (Address)',
            'address.state' => 'State (Address)',
            'address.postal_code' => 'Zip Code (Address)',
            'address.country' => 'Country (Address)',

            'details' => 'Description',

            'phone_numbers.work' => 'Work Phone',
            'phone_numbers.mobile' => 'Mobile Phone',
            'phone_numbers.home' => 'Home Phone',
            'phone_numbers.other' => 'Other Phone',

            'socials.facebook' => 'Facebook',
            'socials.twitter' => 'Twitter',
            'socials.linkedin' => 'LinkedIn',
            'socials.youtube' => 'YouTube',
            'socials.instagram' => 'Instagram',
            'socials.quora' => 'Quora',

            'websites.work' => 'Work Website',
            'websites.personal' => 'Personal Website',
            'websites.other' => 'Other Website',
        ];

        foreach ($core_fields as $_id => $_label) {
            $custom_fields_array[$_id] = $_label;
        }

        try {

            $custom_field_option_ids = [];

            $custom_fields = $this->copper_instance()->apiRequest('custom_field_definitions');

            if (is_array($custom_fields) && !empty($custom_fields)) {
                $recordType = $list_id == 'leads' ? 'lead' : 'person';
                foreach ($custom_fields as $field) {

                    if (isset($field->options) && is_array($field->options)) {
                        $custom_field_option_ids[$field->id] = wp_list_pluck($field->options, 'id');
                    }

                    if (in_array($recordType, $field->available_on)) {
                        $custom_fields_array['mocpcus_' . $field->id . '|' . $field->data_type] = $field->name;
                    }
                }
            }

            if (!empty($custom_field_option_ids)) {
                update_option('mailoptin_copper_custom_field_option_ids', $custom_field_option_ids);
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'copper');
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
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
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