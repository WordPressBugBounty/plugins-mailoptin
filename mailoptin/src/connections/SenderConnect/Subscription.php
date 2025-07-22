<?php

namespace MailOptin\SenderConnect;

class Subscription extends AbstractSenderConnect
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
     * Subscribe a contact to Sender.net.
     *
     * @return array|true[]
     */
    public function subscribe()
    {
        try {

            $name_split = self::get_first_last_names($this->name);

            $properties = [
                'email'     => $this->email,
                'firstname' => $name_split[0] ?? '',
                'lastname'  => $name_split[1] ?? ''
            ];

            $properties['groups'] = [$this->list_id];

            // Handle custom field mappings
            $custom_field_mappings = $this->form_custom_field_mappings();

            if ( ! empty($custom_field_mappings)) {

                foreach ($custom_field_mappings as $senderFieldKey => $customFieldKey) {

                    if ( ! empty($customFieldKey) && ! empty($this->extras[$customFieldKey])) {
                        $value = $this->extras[$customFieldKey];
                        if (is_array($value)) {
                            $value = implode(', ', $value);
                        }
                        $properties['fields'][$senderFieldKey] = esc_attr($value);
                    }
                }
            }

            $response = $this->sender_instance()->make_request('subscribers', $properties, 'POST');

            if (self::is_http_code_success($response['status_code'])) {
                return parent::ajax_success();
            }

            throw new \Exception(wp_json_encode($response));

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'sender', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}
