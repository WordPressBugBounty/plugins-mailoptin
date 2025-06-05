<?php

namespace MailOptin\EnchargeConnect;

class Subscription extends AbstractEnchargeConnect
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
     * Subscribe a contact to Encharge.
     *
     * @return mixed
     */
    public function subscribe()
    {
        try {
            // Split names into first and last
            $name_split = self::get_first_last_names($this->name);

            $lead_tags = $this->get_integration_tags('EnchargeConnect_lead_tags');

            // Prepare Encharge payload with default fields
            $properties = [
                'email'     => $this->email,
                'name'      => implode(" ", $name_split),
                'firstName' => $name_split[0] ?? '',
                'lastName'  => $name_split[1] ?? ''
            ];


            $custom_field_mappings = $this->form_custom_field_mappings();
            $list_custom_fields    = $this->connectInstance->get_optin_fields($this->list_id);

            if (is_array($custom_field_mappings) && is_array($list_custom_fields)) {

                $intersect_result = array_intersect(array_keys($custom_field_mappings), array_keys($list_custom_fields));

                if ( ! empty($intersect_result) && ! empty($custom_field_mappings)) {

                    foreach ($custom_field_mappings as $ENCFieldKey => $customFieldKey) {
                        // we are checking if $customFieldKey is not empty because if a merge field doesnt have a custom field
                        // selected for it, the default "Select..." value is empty ("")
                        if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                            $value = $this->extras[$customFieldKey];
                            if (is_array($value)) {
                                $value = implode(', ', $value);
                            }
                            $properties[$ENCFieldKey] = esc_attr($value);
                        }
                    }
                }
            }

            // Create or update contact account - https://app-encharge-resources.s3.amazonaws.com/redoc.html#tag/People/operation/CreateUpdatePeople
            $response = $this->encharge_instance()->make_request('/people', $properties, 'POST');

            if (self::is_http_code_success($response['status_code'])) {
                // Add tag to contact - https://app-encharge-resources.s3.amazonaws.com/redoc.html#tag/Tags/operation/AddTag
                if ( ! empty($lead_tags)) {

                    $this->encharge_instance()->make_request(
                        '/tags',
                        ['tag' => $lead_tags, 'email' => $this->email],
                        'POST'
                    );
                }

                return parent::ajax_success();
            }

            self::save_optin_error_log(wp_json_encode($response), 'encharge', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'encharge', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
