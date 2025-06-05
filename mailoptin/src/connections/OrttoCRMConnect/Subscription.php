<?php

namespace MailOptin\OrttoCRMConnect;

use function MailOptin\Core\strtotime_utc;

class Subscription extends AbstractOrttoCRMConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;

    protected $optin_campaign_id;

    public function __construct($email, $name, $list_id, $extras)
    {
        $this->email   = $email;
        $this->name    = $name;
        $this->list_id = $list_id;
        $this->extras  = $extras;

        $this->optin_campaign_id = absint($this->extras['optin_campaign_id']);

        parent::__construct();
    }

    public function subscribe()
    {
        $db_tags = $this->get_integration_tags('OrttoCRMConnect_lead_tags');

        $name_split = self::get_first_last_names($this->name);

        try {

            $fields = [
                'str::email' => $this->email,
                'str::first' => $name_split[0],
                'str::last'  => $name_split[1],
            ];

            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $orttocrmKey => $customFieldKey) {

                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {

                        $value = $rawVal = $this->extras[$customFieldKey];

                        if (is_array($value)) $value = implode(', ', $value);

                        if ((strpos($orttocrmKey, 'bol:') === 0)) {
                            $value = filter_var($rawVal, FILTER_VALIDATE_BOOLEAN);
                        }

                        if (strpos($orttocrmKey, 'int:') === 0) {
                            $value = absint($rawVal);
                        }

                        if (strpos($orttocrmKey, 'sst:') === 0) {
                            $value = array_map('trim', (array)$rawVal);
                        }

                        if (strpos($orttocrmKey, 'tme:') === 0 || strpos($orttocrmKey, 'dtz:') === 0) {
                            $value = gmdate('c', strtotime_utc($rawVal));
                        }

                        if (strpos($orttocrmKey, 'phn::') === 0 && ! empty($rawVal)) {
                            $value = ['phone' => $rawVal, 'parse_with_country_code' => true];
                        }

                        if (strpos($orttocrmKey, 'dtz::b') === 0 && ! empty($rawVal)) {
                            $timestamp = strtotime_utc($rawVal);
                            $value     = [
                                'day'      => absint(gmdate('d', $timestamp)),
                                'month'    => absint(gmdate('m', $timestamp)),
                                'year'     => absint(gmdate('Y', $timestamp)),
                                'timezone' => gmdate('e', $timestamp),
                            ];
                        }

                        if (strpos($orttocrmKey, 'geo::') === 0 && ! empty($rawVal)) {
                            $value = ['name' => $rawVal];
                        }

                        $fields[$orttocrmKey] = $value;
                    }
                }
            }

            $lead_data = [

                "people"   => [
                    [
                        'fields' => $fields,
                        'tags'   => array_map('trim', explode(',', $db_tags)),
                    ]
                ],
                'merge_by' => ['str::email']
            ];

            $lead_data = apply_filters('mo_connections_orttocrm_subscription_parameters', $lead_data, $this);

            $this->orttocrm_instance()->post('person/merge', $lead_data);

            return parent::ajax_success();

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getMessage(), 'orttocrm', $this->extras['optin_campaign_id']);

            return parent::ajax_failure();
        }
    }
}
