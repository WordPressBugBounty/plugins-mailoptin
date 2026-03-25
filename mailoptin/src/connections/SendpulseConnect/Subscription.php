<?php

namespace MailOptin\SendpulseConnect;

use MailOptin\Core\PluginSettings\Settings;
use MailOptin\Core\PluginSettings;

class Subscription extends AbstractSendpulseConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;
    /** @var Connect */
    protected $connectInstance;

    public function __construct($email, $name, $list_id, $extras, $connectInstance)
    {
        $this->email           = $email;
        $this->name            = $name;
        $this->list_id         = $list_id;
        $this->extras          = $extras;
        $this->connectInstance = $connectInstance;

        parent::__construct();
    }

    /**
     * True if double optin is not disabled.
     *
     * @return bool
     */
    public function is_double_optin()
    {
        $optin_campaign_id = absint($this->extras['optin_campaign_id']);

        $setting = $this->get_integration_data('SendpulseConnect_enable_double_optin');

        //external forms
        if ($optin_campaign_id == 0) {
            $setting = $this->extras['is_double_optin'];
        }

        $val = ($setting === true);

        return apply_filters('mo_connections_sendpulse_is_double_optin', $val, $optin_campaign_id);
    }

    private function get_gdpr_tag_id()
    {
        try {

            $gdpr_tag_name = apply_filters('mo_connections_sendpulse_acceptance_tag', 'gdpr');

            // Create the tag on the fly if it doesn't exist.
            try {

                $this->sendpulse_instance()->make_request(
                    'tags',
                    ['name' => $gdpr_tag_name, 'color' => '#eef8f7'],
                    'post'
                );
            } catch (\Exception $e) {
            }

            $tags = $this->sendpulse_instance()->make_request('tags');

            if (isset($tags['body']['tags']) && is_array($tags['body']['tags'])) {
                $matched = wp_list_filter($tags['body']['tags'], ['name' => $gdpr_tag_name]);
                if ( ! empty($matched)) {
                    return array_values($matched)[0]['id'];
                }
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Subscribe a contact to SendPulse.
     *
     * @return mixed
     */
    public function subscribe()
    {
        try {

            $tags = [];

            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {

                $gdpr_tag_id = $this->get_gdpr_tag_id();

                if ( ! empty($gdpr_tag_id)) {
                    $tags[] = $gdpr_tag_id;
                }
            }

            $lead_tags = $this->get_integration_tags('SendpulseConnect_lead_tags');

            if ( ! empty($lead_tags)) {
                if (is_array($lead_tags)) {
                    $tags = array_merge($tags, $lead_tags);
                }
            }

            // Prepare SendPulse payload with default fields
            $properties = [
                'emails' => [
                    [
                        'email'     => $this->email,
                        'variables' => [
                            'Name' => $this->name,
                        ]
                    ]
                ],
                'tags'   => $tags
            ];

            $form_custom_fields    = $this->form_custom_fields();
            $custom_field_mappings = $this->form_custom_field_mappings();
            $list_custom_fields    = $this->connectInstance->get_optin_fields($this->list_id);

            if (is_array($form_custom_fields) && is_array($custom_field_mappings) && is_array($list_custom_fields)) {

                $intersect_result = array_intersect(array_keys($custom_field_mappings), array_keys($list_custom_fields));

                if ( ! empty($intersect_result) && ! empty($custom_field_mappings)) {

                    foreach ($custom_field_mappings as $SDPFieldKey => $customFieldKey) {
                        // we are checking if $customFieldKey is not empty because if a merge field doesnt have a custom field
                        // selected for it, the default "Select..." value is empty ("")
                        if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                            $value = $this->extras[$customFieldKey];
                            if (is_array($value)) {
                                $value = implode(', ', $value);
                            }
                            $properties['emails'][0]['variables'][$SDPFieldKey] = esc_attr($value);
                        }
                    }
                }

                // pass additional form custom fields as variables
                $mapped_custom_fields = array_filter($custom_field_mappings, function ($field) {
                    return ! empty($field);
                });

                foreach ($form_custom_fields as $field) {

                    $field_id    = $field['cid'];
                    $placeholder = $field['placeholder'];

                    if ( ! in_array($field_id, $mapped_custom_fields)) {
                        $properties['emails'][0]['variables'][$placeholder] = esc_attr(is_array($this->extras[$field_id]) ? implode(', ', $this->extras[$field_id]) : $this->extras[$field_id]);
                    }
                }
            }

            // Add double opt-in parameters when enabled
            if ($this->is_double_optin()) {

                unset($properties['tags']);

                $sender_email = Settings::instance()->from_email();

                $template_id = PluginSettings\Connections::instance()->sendpulse_doi_template_id();
                $doi_lang    = PluginSettings\Connections::instance()->sendpulse_doi_message_lang();

                if (empty($doi_lang)) $doi_lang = 'en';

                if (empty($sender_email)) {
                    throw new \Exception('Sender email is required for double opt-in but not configured in MailOptin settings.');
                }

                $properties['confirmation'] = 'force';
                $properties['sender_email'] = $sender_email;
                $properties['message_lang'] = $doi_lang;

                if ( ! empty($template_id)) {
                    $properties['template_id'] = $template_id;
                }
            }

            $response = $this->sendpulse_instance()->make_request(
                "addressbooks/" . $this->list_id . "/emails",
                apply_filters('mo_connections_sendpulse_subscription_parameters', $properties, $this),
                'post'
            );

            if (self::is_http_code_success($response['status_code'])) return parent::ajax_success();

            throw new \Exception(
                ! is_string($response['body']) ? wp_json_encode($response['body']) : $response['body'],
                $response['status_code']
            );

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'sendpulse', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
