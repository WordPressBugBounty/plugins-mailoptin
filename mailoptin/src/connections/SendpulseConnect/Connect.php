<?php

namespace MailOptin\SendpulseConnect;

use MailOptin\Core\Connections\ConnectionInterface;

class Connect extends AbstractSendpulseConnect implements ConnectionInterface
{
    public static $connectionName = 'SendpulseConnect';

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
            self::OPTIN_CUSTOM_FIELD_SUPPORT,
            self::EMAIL_CAMPAIGN_SUPPORT
        ];
    }

    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('SendPulse', 'mailoptin');

        return $connections;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function integration_customizer_settings($settings)
    {
        $settings['SendpulseConnect_lead_tags']           = apply_filters('mailoptin_customizer_optin_campaign_SendpulseConnect_lead_tags', '');
        $settings['SendpulseConnect_enable_double_optin'] = apply_filters('mailoptin_customizer_optin_campaign_SendpulseConnect_enable_double_optin', false);

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
                'field'       => 'chosen_select',
                'name'        => 'SendpulseConnect_lead_tags',
                'choices'     => $this->get_tags(),
                'label'       => __('Tags', 'mailoptin'),
                'description' => __('Select tags to assign to subscribers who opt-in via this campaign', 'mailoptin'),
            ];

            $controls[] = [
                'field'       => 'toggle',
                'name'        => 'SendpulseConnect_enable_double_optin',
                'label'       => __('Enable Double Optin', 'mailoptin'),
                'description' => __("Double optin requires users to confirm their email address before they are added or subscribed.", 'mailoptin')
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you to assign tags to subscribers and enable double optin.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=sendpulse_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'SendpulseConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    public function replace_placeholder_tags($content, $type = 'html')
    {
        $search = [
            '{{webversion}}',
            '{{unsubscribe}}'
        ];

        $replace = [
            '{{webversion}}',
            '{{unsubscribe_url}}',
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    public function get_email_list()
    {
        $list_array = [];

        try {

            $offset = 0;
            $loop   = true;
            $limit  = 100;

            while ($loop === true) {

                $response = $this->sendpulse_instance()->make_request(
                    'addressbooks',
                    ['limit' => $limit, 'offset' => $offset]
                );

                $lists = $response['body'] ?? [];

                if (is_array($lists) && ! empty($lists)) {
                    foreach ($lists as $list) {
                        $list_array[$list['id']] = $list['name'];
                    }

                    if (count($lists) < $limit) {
                        $loop = false;
                    }

                    $offset += $limit;
                } else {
                    $loop = false;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'sendpulse');
        }

        return $list_array;
    }

    public function get_tags()
    {
        $tag_array = [];

        try {

            $response = $this->sendpulse_instance()->make_request('tags');

            $tags = $response['body']['tags'] ?? [];

            if (is_array($tags) && ! empty($tags)) {
                foreach ($tags as $tag) {
                    $tag_array[$tag['id']] = $tag['name'];
                }
            }
        } catch (\Exception $e) {

        }

        return $tag_array;
    }

    /**
     * @param string $list_id *
     *
     * @return array
     */
    public function get_optin_fields($list_id = '')
    {
        return ['phone' => esc_html__('Phone', 'mailoptin')];
    }

    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return (new SendCampaign($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text))->send();
    }

    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
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
