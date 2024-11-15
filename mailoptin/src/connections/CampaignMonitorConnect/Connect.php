<?php

namespace MailOptin\CampaignMonitorConnect;

use MailOptin\Core\Admin\Customizer\CustomControls\WP_Customize_Chosen_Single_Select_Control;
use MailOptin\Core\Admin\Customizer\EmailCampaign\Customizer;
use MailOptin\Core\Connections\ConnectionInterface;
use MailOptin\Core\Logging\CampaignLogRepository;
use MailOptin\Core\Repositories\EmailCampaignRepository;

define('MAILOPTIN_CAMPAIGNMONITOR_CONNECT_ASSETS_URL', plugins_url('assets/', __FILE__));

class Connect extends AbstractCampaignMonitorConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'CampaignMonitorConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_action('init', [$this, 'campaign_log_public_preview']);

        add_filter('mailoptin_email_campaign_customizer_page_settings', array($this, 'campaign_customizer_settings'));
        add_filter('mailoptin_email_campaign_customizer_settings_controls', array(
            $this,
            'campaign_customizer_controls'
        ), 10, 4);

        add_action('mailoptin_email_campaign_enqueue_customizer_js', function () {
            wp_enqueue_script(
                'campaign-monitor-segment-control',
                MAILOPTIN_CAMPAIGNMONITOR_CONNECT_ASSETS_URL . 'emailcustomizer.js',
                array('jquery', 'customize-controls'),
                MAILOPTIN_VERSION_NUMBER
            );
        });

        add_action('wp_ajax_mailoptin_customizer_fetch_campaign_monitor_segment', [
            $this,
            'customizer_fetch_campaign_monitor_segment'
        ]);

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

    public function campaign_log_public_preview()
    {
        if (isset($_GET['campaignmonitor_preview_type'], $_GET['uuid'])) {
            $preview_type = sanitize_text_field($_GET['campaignmonitor_preview_type']);

            if ( ! in_array($preview_type, ['text', 'html'])) {
                return;
            }

            $campaign_uuid = sanitize_text_field($_GET['uuid']);

            $campaign_log_id = absint($this->uuid_to_campaignlog_id($campaign_uuid, 'campaignmonitor_email_fetcher'));

            $type_method = 'retrieveContent' . ucfirst($preview_type);

            echo $this->replace_placeholder_tags(CampaignLogRepository::instance()->$type_method($campaign_log_id), $preview_type);
            exit;
        }
    }

    /**
     * Register Campaign Monitor Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Campaign Monitor', 'mailoptin');

        return $connections;
    }

    public function campaign_customizer_settings($settings)
    {
        $settings['CampaignMonitorConnect_segment'] = array(
            'default'   => '',
            'type'      => 'option',
            'transport' => 'postMessage',
        );

        return $settings;
    }

    public function get_list_segments($listid)
    {
        $output = get_transient("mo_campaign_monitor_get_list_segment_$listid");

        if ($output === false) {

            $segments = ['' => __('Select...', 'mailoptin')];

            try {

                $response = $this->campaignmonitorInstance()->apiRequest("lists/{$listid}/segments.json");

                if (is_array($response) && ! empty($response)) {

                    foreach ($response as $item) {
                        $segments[$item->SegmentID] = $item->Title;
                    }
                }

            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'campaignmonitor');
            }

            $output = $segments;

            set_transient("mo_campaign_monitor_get_list_segment_$listid", $segments, 10 * MINUTE_IN_SECONDS);
        }

        return $output;
    }

    /**
     * Fetch Campaign Monitor segment of a list for Email Customizer.
     */
    public function customizer_fetch_campaign_monitor_segment()
    {
        check_ajax_referer('customizer-fetch-email-list', 'security');

        \MailOptin\Core\current_user_has_privilege() || exit;

        $list_id = sanitize_text_field($_REQUEST['list_id']);

        $segments = $this->get_list_segments($list_id);

        ob_start();

        if (count($segments) > 1) {
            foreach ($segments as $key => $value) {
                echo '<option value="' . esc_attr($key) . '">' . $value . '</option>';
            }
        }

        $structure = ob_get_clean();

        wp_send_json_success($structure);

        wp_die();
    }

    /**
     * @param $controls
     * @param $wp_customize
     * @param $option_prefix
     * @param Customizer $customizerClassInstance
     *
     * @return mixed
     */
    public function campaign_customizer_controls($controls, $wp_customize, $option_prefix, $customizerClassInstance)
    {
        $segments = $this->get_list_segments(
            EmailCampaignRepository::get_merged_customizer_value(
                $customizerClassInstance->email_campaign_id,
                'connection_email_list'
            )
        );

        // always prefix with the name of the connect/connection service.
        $controls['CampaignMonitorConnect_segment'] = new WP_Customize_Chosen_Single_Select_Control(
            $wp_customize,
            $option_prefix . '[CampaignMonitorConnect_segment]',
            apply_filters('mailoptin_customizer_settings_campaign_CampaignMonitorConnect_segment_args', array(
                    'label'       => __('Campaign Monitor Segment'),
                    'section'     => $customizerClassInstance->campaign_settings_section_id,
                    'settings'    => $option_prefix . '[CampaignMonitorConnect_segment]',
                    'description' => __('Select a segment to send to. Leave empty to send to all list subscribers.', 'mailoptin'),
                    'choices'     => $segments,
                    'priority'    => 199
                )
            )
        );

        return $controls;
    }

    /**
     * Fulfill interface contract.
     *
     * {@inheritdoc}
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        if ($type == 'text') {
            $search = [
                '{{webversion}}',
                '{{unsubscribe}}'
            ];

            $replace = [
                '[webversion]',
                '[unsubscribe]',
            ];

            $content = str_replace($search, $replace, $content);
        }

        if ($type == 'html') {
            // use regex to replace the "view web version" and unsubribe mailoptin tag with campaignmonitor html tag equivalent.
            // see https://help.campaignmonitor.com/topic.aspx?t=97
            $pattern     = [
                '/<a .+ href="{{webversion}}(?:.+)?">(.+)<\/a>/',
                '/<a .+ href="{{unsubscribe}}(?:.+)?">(.+)<\/a>/'
            ];
            $replacement = ['<webversion>$1</webversion>', '<unsubscribe>$1</unsubscribe>'];
            $content     = preg_replace($pattern, $replacement, $content);
        }

        // search and replace this if this operation is for text content.
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

            $response = $this->campaignmonitorInstance()->getEmailList($this->client_id);

            // an array with list id as key and name as value.
            $lists_array = array();
            if (is_array($response) && ! empty($response)) {
                foreach ($response as $list) {
                    $lists_array[$list->ListID] = $list->Name;
                }
            }

            return $lists_array;

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'campaignmonitor');

            return [];
        }
    }

    public function get_optin_fields($list_id = '')
    {
        try {

            $response = $this->campaignmonitorInstance()->getListCustomFields($list_id);

            $custom_fields_array = [];

            if (is_array($response) && ! empty($response)) {
                foreach ($response as $customField) {
                    $fieldKey                       = str_replace(['[', ']'], '', $customField->Key);
                    $custom_fields_array[$fieldKey] = $customField->FieldName;
                }

                return $custom_fields_array;
            }

            self::save_optin_error_log($response->result_message, 'campaignmonitor');

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'campaignmonitor');
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