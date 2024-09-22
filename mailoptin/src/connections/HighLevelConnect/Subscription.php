<?php

namespace MailOptin\HighLevelConnect;

use MailOptin\Core\Repositories\OptinCampaignsRepository as OCR;

use function MailOptin\Core\strtotime_utc;

class Subscription extends AbstractHighLevelConnect
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

            $properties = [
                'email'      => $this->email,
                'name'       => $this->name,
                'locationId' => '{locationId}'
            ];

            $workflow = $this->get_integration_data('HighLevelConnect_workflows');

            $tags = $this->get_integration_data('HighLevelConnect_lead_tags');

            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $HLKey => $customFieldKey) {
                    // we are checking if $customFieldKey is not empty because if a merge field doesn't have a custom field
                    // selected for it, the default "Select..." value is empty ("")
                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {

                        $is_custom_field = strstr($HLKey, 'ghl_custom_') !== false;

                        $value = $this->extras[$customFieldKey];

                        // HS accept date in unix timestamp in milliseconds
                        if (OCR::get_custom_field_type_by_id($customFieldKey, $this->extras['optin_campaign_id']) == 'date') {
                            $value = gmdate('Y-m-d', strtotime_utc($value));
                        } elseif (OCR::get_custom_field_type_by_id($customFieldKey, $this->extras['optin_campaign_id']) == 'checkbox') {
                            if (is_array($value)) $value = (array)$value;
                        } else {
                            if (is_array($value)) $value = implode(';', $value);
                        }

                        if ($is_custom_field) {
                            $properties['customFields'][] = [
                                'id'          => str_replace('ghl_custom_', '', $HLKey),
                                'field_value' => $value
                            ];
                        } else {
                            $properties[$HLKey] = $value;
                        }
                    }
                }
            }

            $properties = apply_filters(
                'mo_connections_highlevel_optin_properties',
                array_filter($properties, [$this, 'data_filter']),
                $this
            );

            $response = $this->make_request(
                'contacts/upsert',
                'POST',
                $properties
            );

            if (isset($response->contact->id)) {

                $this->add_tags_to_contact($response->contact->id, $tags);
                $this->add_contact_to_workflow($response->contact->id, $workflow);

                return parent::ajax_success();
            }

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'highlevel', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));
        }
    }

    protected function add_tags_to_contact($contact_id, $lead_tags)
    {
        try {

            if ( ! empty($lead_tags)) {

                $lead_tags = array_map('trim', explode(',', $lead_tags));

                $this->make_request(
                    "contacts/{$contact_id}/tags",
                    'POST',
                    ['tags' => $lead_tags]
                );

            }
        } catch (\Exception $e) {

        }
    }

    public function add_contact_to_workflow($contact_id, $workflow_id)
    {
        try {

            $this->make_request(
                "contacts/{$contact_id}/workflow/{$workflow_id}",
                'POST'
            );

        } catch (\Exception $e) {

        }
    }
}