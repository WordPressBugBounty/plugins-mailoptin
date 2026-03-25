<?php

namespace MailOptin\BentoConnect;

class Subscription extends AbstractBentoConnect
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
     * Subscribe a contact to Bento.
     *
     * @return mixed
     */
    public function subscribe()
    {
        try {
            // Split names into first and last
            $name_split = self::get_first_last_names($this->name);

            $lead_tags = $this->get_integration_tags('BentoConnect_lead_tags');

            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {
                $gdpr_tag  = apply_filters('mo_connections_bento_acceptance_tag', 'gdpr');
                $lead_tags = "{$gdpr_tag}," . $lead_tags;
            }

            $first_name_key = apply_filters('mo_connections_bento_first_name_key', 'first_name');
            $last_name_key  = apply_filters('mo_connections_bento_last_name_key', 'last_name');

            // Prepare Bento payload with default fields
            $properties = [
                'email'         => $this->email,
                $first_name_key => $name_split[0] ?? '',
                $last_name_key  => $name_split[1] ?? '',
                'tags'          => $lead_tags
            ];

            $custom_field_mappings = $this->form_custom_field_mappings();
            $list_custom_fields    = $this->connectInstance->get_optin_fields($this->list_id);

            if (is_array($custom_field_mappings) && is_array($list_custom_fields)) {

                $intersect_result = array_intersect(array_keys($custom_field_mappings), array_keys($list_custom_fields));

                if ( ! empty($intersect_result) && ! empty($custom_field_mappings)) {

                    foreach ($custom_field_mappings as $BNTFieldKey => $customFieldKey) {
                        // we are checking if $customFieldKey is not empty because if a merge field doesnt have a custom field
                        // selected for it, the default "Select..." value is empty ("")
                        if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                            $value = $this->extras[$customFieldKey];
                            if (is_array($value)) {
                                $value = implode(', ', $value);
                            }
                            $properties[$BNTFieldKey] = esc_attr($value);
                        }
                    }
                }
            }

            $response = $this->bento_instance()->make_request('batch/subscribers', ['subscribers' => [$properties]], 'post');

            if (self::is_http_code_success($response['status_code'])) return parent::ajax_success();

            throw new \Exception(
                ! is_string($response['body']) ? wp_json_encode($response['body']) : $response['body'],
                $response['status_code']
            );

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'bento', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
