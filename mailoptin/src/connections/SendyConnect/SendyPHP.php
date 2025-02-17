<?php

namespace MailOptin\SendyConnect;

class SendyPHP
{
    protected $installation_url;
    protected $api_key;
    public $list_id;

    public function __construct(array $config)
    {
        //error checking
        $list_id          = $config['list_id'] ?? '';
        $installation_url = $config['installation_url'] ?? '';
        $api_key          = $config['api_key'] ?? '';

        if (empty($installation_url)) {
            throw new \Exception("Required config parameter [installation_url] is not set or empty", 1);
        }

        if (empty($api_key)) {
            throw new \Exception("Required config parameter [api_key] is not set or empty", 1);
        }

        $this->list_id          = $list_id;
        $this->installation_url = $installation_url;
        $this->api_key          = $api_key;
    }

    public function setListId($list_id)
    {
        if (empty($list_id)) {
            throw new \Exception("Required config parameter [list_id] is not set", 1);
        }

        $this->list_id = $list_id;
    }

    public function getListId()
    {
        return $this->list_id;
    }

    public function subscribe(array $values)
    {
        $type = 'subscribe';

        $global_options = array('api_key' => $this->api_key);

        $values = array_merge($global_options, $values);

        $result = strval($this->buildAndSend($type, $values));

        switch ($result) {
            case '1':
                return array(
                    'status'  => true,
                    'message' => 'Subscribed'
                );
                break;

            case 'Already subscribed.':
                return array(
                    'status'  => true,
                    'message' => 'Already subscribed.'
                );
                break;

            default:
                return array(
                    'status'  => false,
                    'message' => $result
                );
                break;
        }
    }

    public function unsubscribe($email)
    {
        $type = 'unsubscribe';

        //Send the unsubscribe
        $result = strval($this->buildAndSend($type, array('email' => $email)));

        //Handle results
        switch ($result) {
            case '1':
                return array(
                    'status'  => true,
                    'message' => 'Unsubscribed'
                );
                break;

            default:
                return array(
                    'status'  => false,
                    'message' => $result
                );
                break;
        }
    }

    public function substatus($email)
    {
        $type = 'api/subscribers/subscription-status.php';

        //Send the request for status
        $result = $this->buildAndSend($type, array(
            'email'   => $email,
            'api_key' => $this->api_key,
            'list_id' => $this->list_id
        ));

        //Handle the results
        switch ($result) {
            case 'Subscribed':
            case 'Unsubscribed':
            case 'Unconfirmed':
            case 'Bounced':
            case 'Soft bounced':
            case 'Complained':
                return array(
                    'status'  => true,
                    'message' => $result
                );
                break;

            default:
                return array(
                    'status'  => false,
                    'message' => $result
                );
                break;
        }
    }

    public function subcount($list = "")
    {
        $type = 'api/subscribers/active-subscriber-count.php';

        //if a list is passed in use it, otherwise use $this->list_id
        if (empty($list)) {
            $list = $this->list_id;
        }

        //handle exceptions
        if (empty($list)) {
            throw new \Exception("method [subcount] requires parameter [list] or [$this->list_id] to be set.", 1);
        }


        //Send request for subcount
        $result = $this->buildAndSend($type, array(
            'api_key' => $this->api_key,
            'list_id' => $list
        ));

        //Handle the results
        if (is_numeric($result) || empty($result)) {
            return array(
                'status'  => true,
                'message' => (int)$result
            );
        }

        //Error
        return array(
            'status'  => false,
            'message' => $result
        );
    }

    public function createCampaign(array $values)
    {
        $type = 'api/campaigns/create.php';

        //Global options
        $global_options = array(
            'api_key' => $this->api_key
        );

        //Merge the passed in values with the global options
        $values = array_merge($global_options, $values);

        //Send request for campaign
        $result = $this->buildAndSend($type, $values);

        //Handle the results
        switch ($result) {
            case 'Campaign created':
            case 'Campaign created and now sending':
                return array(
                    'status'  => true,
                    'message' => $result
                );
                break;

            default:
                return array(
                    'status'  => false,
                    'message' => $result
                );
                break;
        }
    }

    private function buildAndSend($type, array $values)
    {
        //error checking
        if (empty($type)) {
            throw new \Exception("Required config parameter [type] is not set or empty", 1);
        }

        if (empty($values)) {
            throw new \Exception("Required config parameter [values] is not set or empty", 1);
        }

        //Global options for return
        $return_options = array(
            'list'    => $this->list_id,
            'boolean' => 'true'
        );

        //Merge the passed in values with the options for return
        $content = array_merge($values, $return_options);

        $result = wp_remote_post(
            $this->installation_url . '/' . $type,
            ['timeout' => 15, 'body' => $content, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']]
        );

        if (is_wp_error($result)) {
            return $result->get_error_message();
        }

        $code = (int)wp_remote_retrieve_response_code($result);
        if ($code == 404) {
            return "Installation URL could not be reached";
        }

        return wp_remote_retrieve_body($result);
    }
}
