<?php

namespace MailOptin\DripConnect;

use DrewM\Drip\Dataset;
use MailOptin\Core\Repositories\OptinCampaignsRepository;

class Subscription extends AbstractDripConnect
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

    /**
     * @return mixed
     */
    public function subscribe()
    {
        try {

            $name_split = self::get_first_last_names($this->name);

            $lead_data = [
                'email'      => $this->email,
                'first_name' => $name_split[0],
                'last_name'  => $name_split[1],
                'ip_address' => \MailOptin\Core\get_ip_address()
            ];

            $lead_tags = $this->get_integration_tags('DripConnect_lead_tags');

            $custom_field_data = [];

            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $dripKey => $customFieldKey) {
                    // we are checking if $customFieldKey is not empty because if a merge field doesn't have a custom field
                    // selected for it, the default "Select..." value is empty ("")
                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                        $value = $this->extras[$customFieldKey];
                        if (is_array($value)) $value = implode(', ', $value);
                        if ($dripKey == 'sms_number' && (empty($value) || strpos(trim($value), '+') !== 0)) continue;

                        if (in_array($dripKey, array_keys(self::get_core_custom_fields()))) {
                            $lead_data[$dripKey] = esc_attr($value);
                        } else {
                            $custom_field_data[$dripKey] = esc_attr($value);
                        }
                    }
                }
            }

            $lead_data['custom_fields'] = array_filter($custom_field_data, [$this, 'data_filter']);

            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {
                $lead_data['eu_consent']         = 'granted';
                $lead_data['eu_consent_message'] = OptinCampaignsRepository::get_merged_customizer_value($this->extras['optin_campaign_id'], 'note');
            }

            if ( ! empty($lead_tags)) {
                $lead_data['tags'] = array_map('trim', explode(',', $lead_tags));
            }

            $lead_data = apply_filters('mo_connections_drip_optin_payload', array_filter($lead_data, [$this, 'data_filter']), $this);

            $data = new Dataset('subscribers', $lead_data);

            $response = $this->drip_instance()->post("subscribers", $data);

            if ($response->status >= 200 && $response->status <= 299) {
                return parent::ajax_success();
            }

            if (isset($response->error, $response->message)) {
                if (strpos($response->message, 'already subscribed') !== false) {
                    return parent::ajax_success();
                }
            }

            self::save_optin_error_log($response->error . ': ' . $response->message, 'drip', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'drip', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}