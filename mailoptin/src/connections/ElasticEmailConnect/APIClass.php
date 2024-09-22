<?php

namespace MailOptin\ElasticEmailConnect;

class APIClass
{
    protected string $api_key;

    protected string $api_url = 'https://api.elasticemail.com/v4/';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * @param $endpoint
     * @param array $args
     * @param string $method
     *
     * @return array
     * @throws \Exception
     */
    public function make_request($endpoint, $args = [], $method = 'get')
    {
        $url = $this->api_url . $endpoint;

        $wp_args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            "headers" => ['X-ElasticEmail-ApiKey' => $this->api_key]
        ];

        switch ($method) {
            case 'post':
                $wp_args['headers']["Content-Type"] = "application/json";
                $wp_args['body']                    = json_encode($args);
                break;
            case 'delete':
                if ( ! empty($args)) {
                    $wp_args['body'] = json_encode($args);
                }
                break;
            case 'get':
                $url = add_query_arg($args, $url);
                break;
        }

        $response = wp_remote_request($url, $wp_args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_body      = json_decode(wp_remote_retrieve_body($response), true);
        $response_http_code = wp_remote_retrieve_response_code($response);

        return ['status_code' => $response_http_code, 'body' => $response_body];
    }

    /**
     * @param $endpoint
     * @param array $args
     *
     * @return array
     * @throws \Exception
     */
    public function post($endpoint, $args = [])
    {
        return $this->make_request($endpoint, $args, 'post');
    }
}
