<?php

namespace MailOptin\EnchargeConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractEnchargeConnect implements ConnectionInterface
{
    public static $connectionName = 'EnchargeConnect';

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

    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Encharge', 'mailoptin');

        return $connections;
    }

    public function replace_placeholder_tags($content, $type = 'html')
    {
        return $this->replace_footer_placeholder_tags($content);
    }

    public function get_email_list()
    {
        return ['all' => __('All Contacts', 'mailoptin')];
    }

    /**
     * @param string $list_id *
     *
     * @return array
     */
    public function get_optin_fields($list_id = '')
    {
        $bucket = [];

        try {
            // Fetch custom field metadata for Encharge Contacts
            $response = $this->encharge_instance()->make_request('/fields');

            if ( ! empty($response['body']->items)) {
                foreach ($response['body']->items as $field) {
                    if (
                        in_array($field->name, ['firstName', 'lastName', 'email', 'name', 'userId']) ||
                        empty($field->title)
                    ) {
                        continue;
                    }

                    $bucket[$field->name] = $field->title;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'encharge');
        }

        return $bucket;
    }

    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return [];
    }

    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function integration_customizer_settings($settings)
    {
        $settings['EnchargeConnect_lead_tags'] = apply_filters('mailoptin_customizer_optin_campaign_EnchargeConnect_lead_tags', '');

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
            // always prefix with the name of the connect/connection service.

            $controls[] = [
                'field'       => 'text',
                'name'        => 'EnchargeConnect_lead_tags',
                'label'       => __('Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Enter comma-separated list of tags to assign to subscribers who opt-in via this campaign.', 'mailoptin'),
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you to apply tags to subscribers.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=encharge_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'Encharge_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
