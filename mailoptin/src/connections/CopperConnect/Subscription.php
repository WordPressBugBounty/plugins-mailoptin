<?php

namespace MailOptin\CopperConnect;

use function MailOptin\Core\strtotime_utc;

class Subscription extends AbstractCopperConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;
    /** @var Connect */
    public $connectInstance;

    public function __construct($email, $name, $list_id, $extras, $connectInstance)
    {
        $this->email = $email;
        $this->name = $name;
        $this->list_id = $list_id;
        $this->extras = $extras;
        $this->connectInstance = $connectInstance;

        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function subscribe()
    {
        try {

            if ($this->is_contact_exist($this->email, $this->list_id)) return parent::ajax_success();

            $lead_tags = $this->get_integration_tags('CopperConnect_lead_tags');

            if (isset($this->extras['mo-acceptance']) && $this->extras['mo-acceptance'] == 'yes') {
                $gdpr_tag = apply_filters('mo_connections_copper_acceptance_tag', 'gdpr');
                $lead_tags = "{$gdpr_tag}," . $lead_tags;
            }

            if ($this->list_id === 'leads') {

                $properties = [
                    'email' => [
                        'email' => $this->email,
                        'category' => 'work'
                    ]
                ];

            } else {

                $properties = [
                    'emails' => [
                        [
                            'email' => $this->email,
                            'category' => 'work'
                        ]
                    ]
                ];
            }

            $name_split = self::get_first_last_names($this->name);

            $properties['first_name'] = $name_split[0];
            $properties['last_name'] = $name_split[1];
            $properties['name'] = $name_split[0] . ' ' . $name_split[1]; // Copper API requires a name.

            if (!empty($lead_tags)) $properties['tags'] = array_map('trim', explode(',', $lead_tags));

            $custom_field_mappings = $this->form_custom_field_mappings();
            $list_custom_fields = $this->connectInstance->get_optin_fields($this->list_id);

            if (is_array($custom_field_mappings) && is_array($list_custom_fields)) {

                $intersect_result = array_intersect(array_keys($custom_field_mappings), array_keys($list_custom_fields));

                if (!empty($intersect_result) && !empty($custom_field_mappings)) {

                    $custom_field_option_ids = get_option("mailoptin_copper_custom_field_option_ids", []);

                    foreach ($custom_field_mappings as $CopperFieldKey => $customFieldKey) {
                        // we are checking if $customFieldKey is not empty because if a merge field doesn't have a custom field
                        // selected for it, the default "Select..." value is empty ("")
                        if (!empty($customFieldKey) && !empty($this->extras[$customFieldKey])) {

                            $value = $raw_value = $this->extras[$customFieldKey];

                            if (is_array($value)) $value = implode(', ', $value);

                            if (strstr($CopperFieldKey, 'phone_numbers.')) {

                                $__explode = explode('.', $CopperFieldKey);

                                $properties['phone_numbers'][] = [
                                    'number' => esc_attr($value),
                                    'category' => $__explode[1]
                                ];
                                continue;
                            }

                            if (strstr($CopperFieldKey, 'address.')) {
                                $_explode = explode('.', $CopperFieldKey);
                                $properties['address'][$_explode[1]] = esc_attr($value);
                                continue;
                            }

                            if (strstr($CopperFieldKey, 'websites.')) {
                                $__explode = explode('.', $CopperFieldKey);

                                $properties['websites'][] = [
                                    'url' => (string)$value,
                                    'category' => $__explode[1]
                                ];
                                continue;
                            }

                            if (strstr($CopperFieldKey, 'socials.')) {
                                $__explode = explode('.', $CopperFieldKey);

                                $properties['socials'][] = [
                                    'url' => (string)$value,
                                    'category' => $__explode[1]
                                ];
                                continue;
                            }

                            if (strstr($CopperFieldKey, 'mocpcus_')) {

                                $field_id_combo = str_replace('mocpcus_', '', $CopperFieldKey);
                                [$fieldId, $fieldType] = explode('|', $field_id_combo);

                                $valid_option_ids = $custom_field_option_ids[$fieldId] ?? [];

                                if ($fieldType == 'MultiSelect' && is_array($raw_value) && !empty($raw_value)) {

                                    // ensure value for both fieldTypes is an actual valid option ID.
                                    $__result = array_filter($raw_value, function ($val) use ($valid_option_ids) {
                                        return in_array($val, $valid_option_ids);
                                    });

                                    if (empty($__result)) continue;

                                    $value = array_map('absint', $__result);
                                }

                                if ($fieldType == 'Dropdown') {
                                    $value = absint($value);
                                    // ensure value for both fieldTypes is an actual valid option ID.
                                    if (!in_array($value, $valid_option_ids)) continue;
                                }

                                // see comment above. Percentage field in Copper is integer
                                if ($fieldType == 'Percentage') $value = absint($value);
                                if ($fieldType == 'Float') $value = $this->castFloatSmart($value);
                                if ($fieldType == 'Date' && !is_numeric($value)) $value = strtotime_utc($value);

                                $properties['custom_fields'][] = [
                                    'custom_field_definition_id' => $fieldId,
                                    'value' => $value
                                ];

                                continue;
                            }

                            $properties[$CopperFieldKey] = esc_attr($value);
                        }
                    }
                }
            }

            $properties = apply_filters('mo_connections_copper_optin_payload', array_filter($properties, [$this, 'data_filter']), $this);

            $response = $this->copper_instance()->apiRequest(
                $this->list_id,
                'POST',
                $properties,
                ['Content-Type' => 'application/json']
            );

            if (!empty($response->id)) return parent::ajax_success();

            return parent::ajax_failure();

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'copper', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }

    private function is_contact_exist($email_address, $record_type)
    {
        if (!empty($email_address)) {

            try {

                if ($record_type == 'leads') {

                    $response = $this->copper_instance()->apiRequest(
                        'leads/search',
                        'POST',
                        ['emails' => $email_address, 'page_size' => 1],
                        ['Content-Type' => 'application/json']
                    );

                    if (is_array($response) && isset($response[0]->id)) return $response[0];

                } else {

                    $response = $this->copper_instance()->apiRequest(
                        'people/fetch_by_email',
                        'POST',
                        ['email' => $email_address],
                        ['Content-Type' => 'application/json']
                    );

                    if (!empty($response->id)) return $response;
                }

            } catch (\Exception $e) {
            }
        }

        return false;
    }

    private function castFloatSmart($value)
    {
        $floatVal = (float)$value;
        return ($floatVal == (int)$floatVal) ? (int)$floatVal : $floatVal;
    }
}