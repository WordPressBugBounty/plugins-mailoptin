<?php

namespace MailOptin\SenderConnect;

use function MailOptin\Core\is_http_code_success;

class APIClass
{
    protected $api_token;
    protected $api_url;
    protected $api_version = 2;
    protected $api_url_base = 'https://api.sender.net/';

    public function __construct($api_token)
    {
        $this->api_token = $api_token;
        $this->api_url = $this->api_url_base . 'v' . $this->api_version . '/';
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
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ]
        ];

        switch (strtolower($method)) {
            case 'post':
            case 'put':
            case 'patch':
            case 'delete':
                $wp_args['body'] = json_encode($args);
                break;
            case 'get':
                if (!empty($args)) {
                    $url = add_query_arg($args, $url);
                }
                break;
        }

        $response = wp_remote_request($url, $wp_args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_http_code = wp_remote_retrieve_response_code($response);

        if (!is_http_code_success($response_http_code)) {
            throw new \Exception($response_body, $response_http_code);
        }

        return ['status_code' => $response_http_code, 'body' => json_decode($response_body)];
    }
}
