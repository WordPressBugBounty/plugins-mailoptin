<?php

namespace MailOptin\HubspotConnect;

use MailOptin\Core\Repositories\OptinCampaignsRepository as OCR;

use function MailOptin\Core\is_valid_data;
use function MailOptin\Core\strtotime_utc;

class Subscription extends AbstractHubspotConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;
    /** @var Connect */
    public $connectInstance;

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
     * @return mixed
     */
    public function subscribe()
    {
        try {

            //Contact properties ... name/email etc
            $name_split = self::get_first_last_names($this->name);
            $properties = [
                'email'     => $this->email,
                'firstname' => $name_split[0],
                'lastname'  => $name_split[1],
            ];

            $lead_status      = $this->get_integration_data('HubspotConnect_lead_status');
            $life_cycle_stage = $this->get_integration_data('HubspotConnect_life_cycle_stage');
            $owner            = $this->get_integration_data('HubspotConnect_lead_owner');

            if ( ! empty($life_cycle_stage)) {
                $properties['lifecyclestage'] = $life_cycle_stage;
            }

            if ( ! empty($lead_status)) {
                $properties['hs_lead_status'] = $lead_status;
            }

            if ( ! empty($owner)) {
                $properties['hubspot_owner_id'] = $owner;
            }

            //GDPR consent
            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {

                $gdpr_tag = apply_filters('mo_connections_hubspot_acceptance_tag', 'gdpr');

                try {

                    $this->hubspotInstance()->apiRequest(
                        "properties/v1/contacts/properties",
                        'POST',
                        [
                            'name'      => $gdpr_tag,
                            'label'     => 'GDPR',
                            'type'      => 'string',
                            'groupName' => 'contactinformation',
                            'fieldType' => 'booleancheckbox'
                        ]);
                } catch (\Exception $e) {
                }

                $properties[$gdpr_tag] = 'true';
            }

            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $HSKey => $customFieldKey) {
                    // we are checking if $customFieldKey is not empty because if a merge field doesn't have a custom field
                    // selected for it, the default "Select..." value is empty ("")
                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                        $value = $this->extras[$customFieldKey];
                        // HS accept date in unix timestamp in milliseconds
                        if (OCR::get_custom_field_type_by_id($customFieldKey, $this->extras['optin_campaign_id']) == 'date') {
                            $properties[$HSKey] = strtotime_utc($value) * 1000;
                            continue;
                        }

                        // see https://developers.hubspot.com/docs/faq/how-do-i-set-multiple-values-for-checkbox-properties
                        if (OCR::get_custom_field_type_by_id($customFieldKey, $this->extras['optin_campaign_id']) == 'checkbox') {
                            if (is_array($value)) {
                                $properties[$HSKey] = implode(';', $value);
                                continue;
                            }
                        }

                        if (is_array($value)) {
                            $value = implode(';', $value);
                        }

                        $properties[$HSKey] = $value;
                    }
                }
            }

            $properties = apply_filters('mo_connections_hubspot_optin_properties', $properties, $this);

            //Create the contact
            $contact_data = ['properties' => []];

            $validProperties = array_keys($this->connectInstance->get_optin_fields());
            foreach ($properties as $property => $value) {
                if (in_array($property, $validProperties) && is_valid_data($value)) {
                    $contact_data['properties'][$property] = $value;
                }
            }

            $payload = apply_filters('mo_connections_hubspot_optin_payload', array_filter($contact_data, [$this, 'data_filter']), $this);

            if (strstr($this->list_id, 'v3_') === false) {
                $this->list_id = $this->get_listid_from_legacy_id($this->list_id);
            } else {
                $this->list_id = str_replace('v3_', '', $this->list_id);
            }

            $response = $this->hubspotInstance()->addSubscriber($this->list_id, $this->email, $payload);

            if (isset($response->id)) {
                return parent::ajax_success();
            }

            return parent::ajax_failure();

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'hubspot', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }

    private function get_listid_from_legacy_id($list_id)
    {
        $cache_key = 'mo_hubspot_listid_from_legacy_id_' . $list_id;

        $v3_list_id = get_transient($cache_key);

        if (empty($v3_list_id)) {

            $response = $this->hubspotInstance()->apiRequest('crm/v3/lists/idmapping?legacyListId=' . $list_id);

            if (isset($response->listId)) {
                $v3_list_id = $response->listId;
                set_transient($cache_key, $v3_list_id, MONTH_IN_SECONDS);
            }
        }

        return $v3_list_id ?: $list_id;
    }
}