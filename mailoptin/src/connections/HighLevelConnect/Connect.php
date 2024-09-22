<?php

namespace MailOptin\HighLevelConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractHighLevelConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'HighLevelConnect';

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
     * Register HighLevel Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('HighLevel', 'mailoptin');

        return $connections;
    }

    /**
     * @param array $settings
     *
     * @return mixed
     */
    public function integration_customizer_settings($settings)
    {
        $settings['HighLevelConnect_workflows'] = apply_filters('mailoptin_customizer_optin_campaign_HighLevelConnect_workflows', '');
        $settings['HighLevelConnect_lead_tags'] = apply_filters('mailoptin_customizer_optin_campaign_HighLevelConnect_lead_tags', '');

        return $settings;
    }

    public function get_workflows()
    {
        try {

            $response = $this->make_request('workflows/?locationId={locationId}');

            $options = [];

            if (isset($response->workflows)) {

                $options = array_reduce($response->workflows, function ($carry, $item) {
                    $carry[$item->id] = $item->name;

                    return $carry;
                }, []);
            }

            return $options;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'highlevel');

            return [];
        }
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
                'field'       => 'select',
                'name'        => 'HighLevelConnect_workflows',
                'label'       => __('Workflow', 'mailoptin'),
                'choices'     => ['' => '––––––––––'] + $this->get_workflows(),
                'description' => __('Select workflow to add contacts to.', 'mailoptin'),
            ];

            $controls[] = [
                'field'       => 'text',
                'name'        => 'HighLevelConnect_lead_tags',
                'label'       => __('Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Comma-separated list of tags to assign to a new contacts in HighLevel', 'mailoptin'),
            ];

        } else {

            $content = sprintf(
                __("Upgrade to %sMailOptin Premium%s to map custom fields, assign tags and add leads to workflows.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=highlevel_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            $controls[] = [
                'name'    => 'HighLevelConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * Fulfill interface contract.
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
        return ['all' => __('All Contacts', 'mailoptin')];
    }

    /**
     * {@inherit_doc}
     *
     * @return mixed
     */
    public function get_optin_fields($list_id = '')
    {
        $fields = [
            'gender'      => 'Gender',
            'phone'       => 'Phone',
            'address1'    => 'Street Address',
            'city'        => 'City',
            'state'       => 'State',
            'country'     => 'Country',
            'postalCode'  => 'Postal Code',
            'website'     => 'Website',
            'dateOfBirth' => 'Date of Birth',
            'companyName' => 'Company Name',
            'timezone'    => 'Time Zone',
            'source'      => 'Source',
        ];

        try {

            $custom_fields = $this->make_request('locations/{locationId}/customFields');

            if (isset($custom_fields->customFields)) {
                foreach ($custom_fields->customFields as $custom_field) {
                    $fields['ghl_custom_' . $custom_field->id] = $custom_field->name;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'highlevel');
        }

        return $fields;
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