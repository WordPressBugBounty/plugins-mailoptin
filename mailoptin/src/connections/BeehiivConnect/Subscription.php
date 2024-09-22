<?php

namespace MailOptin\BeehiivConnect;

class Subscription extends AbstractBeehiivConnect
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

    /**
     * @return mixed
     */
    public function subscribe()
    {
        $name_custom_field     = $this->get_integration_data('BeehiivConnect_name_field_key');
        $is_send_welcome_email = $this->get_integration_data('BeehiivConnect_enable_welcome_email');

        $db_tags = $this->get_integration_tags('BeehiivConnect_lead_tags');
        $tags    = ! empty($db_tags) ? array_map('trim', explode(',', $db_tags)) : [];

        try {

            $lead_data = [
                'send_welcome_email' => $is_send_welcome_email,
                'email'              => $this->email
            ];

            if ( ! empty($this->list_id) && $this->list_id != 'all') {

                $lead_data['tier'] = 'premium';

                $lead_data['premium_tier_ids'] = [$this->list_id];
            }

            if ( ! empty($name_custom_field)) {

                $lead_data['custom_fields'][] = [
                    'name'  => $name_custom_field,
                    'value' => $this->name
                ];
            }

            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $BeehiivFieldKey => $customFieldKey) {
                    // we are checking if $customFieldKey is not empty because if a merge field doesnt have a custom field
                    // selected for it, the default "Select..." value is empty ("")
                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {

                        $value = $this->extras[$customFieldKey];

                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }

                        $lead_data['custom_fields'][] = [
                            'name'  => $BeehiivFieldKey,
                            'value' => $value
                        ];
                    }
                }
            }

            $lead_data = apply_filters('mo_connections_beehiiv_subscription_parameters', $lead_data, $this);

            $response = $this->beehiiv_instance()->make_request("publications/{publicationId}/subscriptions", $lead_data, 'post');

            if (isset($response['body']->data->id)) {

                if ( ! empty($tags)) {

                    $this->beehiiv_instance()->make_request(
                        sprintf("publications/{publicationId}/subscriptions/%s/tags", $response['body']->data->id),
                        ['tags' => $tags],
                        'post'
                    );
                }

                return parent::ajax_success();
            }

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'beehiiv', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));
        }
    }
}