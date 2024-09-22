<?php

namespace MailOptin\ElasticEmailConnect;

class Subscription extends AbstractElasticEmailConnect
{
    public string $email;
    public $name;
    public string $list_id;
    public $extras;

    public function __construct($email, $name, $list_id, $extras)
    {
        $this->email   = $email;
        $this->name    = $name;
        $this->list_id = $list_id;
        $this->extras  = $extras;

        parent::__construct();
    }

    public function subscribe()
    {
        try {

            $name_split = self::get_first_last_names($this->name);

            $args = [
                'Email'     => $this->email,
                'FirstName' => $name_split[0],
                'LastName'  => $name_split[1],
                'Status'    => 'Active'
            ];

            $args = apply_filters(
                'mo_connections_elasticemail_optin_payload',
                array_filter($args, [$this, 'data_filter']),
                $this
            );

            $response = $this->elasticemail_instance()->post(
                'contacts?listnames=' . $this->list_id,
                [$args]
            );

            if (self::is_http_code_success($response['status_code'])) {
                return parent::ajax_success();
            }

            if (isset($response['body']['Error'])) {
                self::save_optin_error_log($response['body']['Error'], 'elasticemail', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);
            }

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'elasticemail', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure(__('There was an error saving your contact. Please try again.', 'mailoptin'));
        }
    }
}
