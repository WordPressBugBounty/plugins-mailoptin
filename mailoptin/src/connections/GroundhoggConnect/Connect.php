<?php

namespace MailOptin\GroundhoggConnect;

use Groundhogg\Preferences;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'GroundhoggConnect';

    public function __construct()
    {
        add_filter('mailoptin_registered_connections', [$this, 'register_connection']);

        add_filter('mo_optin_form_integrations_default', [$this, 'integration_customizer_settings']);
        add_filter('mo_optin_integrations_controls_after', [$this, 'integration_customizer_controls']);

        parent::__construct();
    }

    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT
        ];
    }

    public static function is_connected()
    {
        return class_exists('\Groundhogg\Plugin');
    }

    /**
     * Register Groundhogg Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        if (self::is_connected()) {
            $connections[self::$connectionName] = 'Groundhogg';
        }

        return $connections;
    }

    /**
     * @param $content
     * @param string $type
     *
     * @return mixed|string
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
        return ['all' => __('All Contacts', 'mailoptin')];
    }

    /**
     * @return array
     */
    public function get_tags()
    {
        $bucket = [];


        if (self::is_connected()) {

            try {

                $tags = \Groundhogg\get_db('tags')->query();

                foreach ($tags as $tag) {
                    $bucket[$tag->tag_id] = $tag->tag_name;
                }

            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'groundhogg');
            }
        }

        return $bucket;
    }

    public function get_optin_fields($list_id = '')
    {
        $custom_fields = [
            'primary_phone'           => esc_html__('Primary Phone', 'mailoptin'),
            'primary_phone_extension' => esc_html__('Primary Phone Ext.', 'mailoptin'),
            'mobile_phone'            => esc_html__('Mobile Phone', 'mailoptin'),
            'birthday'                => esc_html__('Birthday', 'mailoptin'),
            'company_name'            => esc_html__('Company', 'mailoptin'),
            'company_website'         => esc_html__('Company Website', 'mailoptin'),
            'company_address'         => esc_html__('Company Address', 'mailoptin'),
            'company_phone'           => esc_html__('Company Phone', 'mailoptin'),
            'job_title'               => esc_html__('Job Title', 'mailoptin'),
            'street_address_1'        => esc_html__('Address Line 1', 'mailoptin'),
            'street_address_2'        => esc_html__('Address Line 2', 'mailoptin'),
            'city'                    => esc_html__('City', 'mailoptin'),
            'postal_zip'              => esc_html__('Postal/Zip Code', 'mailoptin'),
            'region'                  => esc_html__('State/Region', 'mailoptin'),
            'country'                 => esc_html__('Country', 'mailoptin'),
        ];


        if (self::is_connected()) {

            try {

                $gh_fields = \Groundhogg\Properties::instance()->get_fields();

                foreach ($gh_fields as $field) {
                    $custom_fields[$field['name']] = $field['label'];
                }
            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'groundhogg');
            }
        }

        return $custom_fields;
    }

    /**
     * @return array
     */
    public function get_optin_status()
    {

        if ( ! self::is_connected()) return [];

        return [
            Preferences::CONFIRMED    => _x('Confirmed', 'optin_status', 'groundhogg'),
            Preferences::UNCONFIRMED  => _x('Unconfirmed', 'optin_status', 'groundhogg'),
            Preferences::UNSUBSCRIBED => _x('Unsubscribed', 'optin_status', 'groundhogg'),
            Preferences::WEEKLY       => _x('Subscribed Weekly', 'optin_status', 'groundhogg'),
            Preferences::MONTHLY      => _x('Subscribed Monthly', 'optin_status', 'groundhogg'),
        ];
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
     * @return array|true[]
     */
    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
    }

    private function get_owners()
    {
        $bucket = ['' => '&mdash;&mdash;&mdash;'];

        if (self::is_connected()) {
            foreach (\Groundhogg\get_owners() as $owner) {
                $bucket[$owner->ID] = sprintf('%s (%s)', $owner->display_name, $owner->user_email);
            }
        }

        return $bucket;
    }

    public function integration_customizer_settings($settings)
    {
        if (self::is_connected()) {
            $settings['GroundhoggConnect_optin_status'] = apply_filters('mailoptin_customizer_optin_campaign_GroundhoggConnect_optin_status', Preferences::CONFIRMED);
        }

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
                'field'       => 'chosen_select',
                'name'        => 'GroundhoggConnect_lead_tags',
                'choices'     => $this->get_tags(),
                'label'       => __('Tags', 'mailoptin'),
                'description' => __('Select tags to assign to leads.', 'mailoptin')
            ];

            $controls[] = [
                'field'   => 'select',
                'name'    => 'GroundhoggConnect_contact_owner',
                'label'   => __('Contact Owner', 'mailoptin'),
                'choices' => ['' => '––––––––––'] + $this->get_owners(),
            ];

            $controls[] = [
                'field'   => 'select',
                'name'    => 'GroundhoggConnect_optin_status',
                'label'   => __('Opt-in Status', 'mailoptin'),
                'choices' => ['' => '––––––––––'] + $this->get_optin_status(),
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you assign tags to contacts, set contact owner and optin status.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=groundhogg_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'GroundhoggConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    /**
     * Singleton poop.
     *
     * @return self
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
