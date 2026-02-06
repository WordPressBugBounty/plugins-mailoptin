<?php

namespace MailOptin\MailercloudConnect;

class Subscription extends AbstractMailercloudConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;

    public function __construct($email, $name, $list_id, $extras)
    {
        $this->email   = $email;
        $this->name    = $name;
        $this->list_id = $list_id;
        $this->extras  = $extras;

        parent::__construct();
    }

    public function subscribe()
    {
        $name_split = self::get_first_last_names($this->name);

        $lead_data = [
            'email'      => $this->email,
            'first_name' => $name_split[0],
            'last_name'  => $name_split[1],
            'list_id'    => $this->list_id
        ];

        try {

            $tags = [];

            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {

                $gdpr_tag = apply_filters('mo_connections_mailercloud_acceptance_tag', 'gdpr');
                try {
                    $this->mailercloud_instance()->post('tags', ['name' => $gdpr_tag]);
                } catch (\Exception $e) {
                }

                $tags[] = $gdpr_tag;
            }

            $lead_tags = $this->get_integration_tags('MailercloudConnect_lead_tags');

            if ( ! empty($lead_tags)) {
                if (is_array($lead_tags)) {
                    $tags = array_merge($tags, $lead_tags);
                }
            }

            if ( ! empty($tags)) {
                $lead_data['tags'] = $tags;
            }

            $custom_field_mappings = $this->form_custom_field_mappings();

            $default_fields = [
                'phone',
                'city',
                'state',
                'country',
                'postal_code',
                'middle_name',
                'company_name',
                'job_title',
                'department',
                'industry',
                'salary',
                'lead_source',
                'userip',
                'mailbox_provider'
            ];

            if ( ! empty($custom_field_mappings)) {

                $custom_fields_data = [];

                foreach ($custom_field_mappings as $MailercloudFieldKey => $customFieldKey) {
                    // we are checking if $customFieldKey is not empty because if a merge field doesnt have a custom field
                    // selected for it, the default "Select..." value is empty ("")
                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                        $value = $this->extras[$customFieldKey];

                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }

                        $value = esc_attr($value);

                        if (in_array($MailercloudFieldKey, $default_fields)) {
                            $lead_data[$MailercloudFieldKey] = $value;
                        } else {
                            $custom_fields_data[$MailercloudFieldKey] = $value;
                        }
                    }
                }

                if ( ! empty($custom_fields_data)) {
                    $lead_data['custom_fields'] = $custom_fields_data;
                }
            }

            $lead_data = apply_filters('mo_connections_mailercloud_subscription_parameters', $lead_data, $this);

            $response = $this->mailercloud_instance()->post('contacts/upsert', $lead_data);

            if (isset($response['body']->contact_id)) {
                return parent::ajax_success();
            }

            $error_message = $response['body']->message ?? json_encode($response['body']);

            self::save_optin_error_log($error_message, 'mailercloud', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'mailercloud', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
