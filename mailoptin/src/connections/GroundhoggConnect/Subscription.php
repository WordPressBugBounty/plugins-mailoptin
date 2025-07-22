<?php

namespace MailOptin\GroundhoggConnect;

use MailOptin\Core\Connections\AbstractConnect;

class Subscription extends AbstractConnect
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
     * @return array|true[]
     */
    public function subscribe()
    {
        try {

            $name_split   = self::get_first_last_names($this->name);
            $owner        = $this->get_integration_data('GroundhoggConnect_contact_owner');
            $optin_status = $this->get_integration_data('GroundhoggConnect_optin_status');

            $lead_tags = $this->get_integration_tags('GroundhoggConnect_lead_tags');

            $properties = [
                'email'        => $this->email,
                'first_name'   => $name_split[0] ?? '',
                'last_name'    => $name_split[1] ?? '',
                'owner_id'     => $owner,
                'optin_status' => $optin_status,
            ];

            $contact = new \Groundhogg\Contact(['email' => $this->email]);

            // Handle custom field mappings
            $custom_field_mappings = $this->form_custom_field_mappings();
            $list_custom_fields    = $this->connectInstance->get_optin_fields($this->list_id);

            if (is_array($custom_field_mappings) && is_array($list_custom_fields)) {

                foreach ($custom_field_mappings as $groundhoggFieldKey => $customFieldKey) {

                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {

                        $value = $this->extras[$customFieldKey];

                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }

                        $properties[$groundhoggFieldKey] = $value;
                    }
                }
            }

            $properties = apply_filters('mo_connections_groundhogg_subscription_parameters', $properties, $this);

            if ($contact->exists()) {

                $contact->update($properties);

                unset($properties['user_id']);
                unset($properties['owner_id']);
                unset($properties['optin_status']);
                unset($properties['first_name']);
                unset($properties['last_name']);
                unset($properties['email']);

                foreach ($properties as $key => $value) {
                    $contact->update_meta($key, $value);
                }

                $contact->apply_tag($lead_tags);

                \Groundhogg\after_form_submit_handler($contact);

                do_action('mo_connections_grounhogg_after_subscribe', $contact, $this->list_id, $this->email);

                return parent::ajax_success();
            }

            throw new \Exception('Groundhogg contact does not exist. something went wrong.');

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'groundhogg', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
