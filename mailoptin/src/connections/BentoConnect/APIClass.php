<?php

namespace MailOptin\BentoConnect;

use function MailOptin\Core\is_http_code_success;

class APIClass
{
    protected $api_url;
    protected $publishable_key;
    protected $secret_key;
    protected $site_uuid;

    /**
     * @var int
     */
    protected $api_version = 1;

    protected $api_url_base = 'https://app.bentonow.com/api/';

    public function __construct($publishable_key, $secret_key, $site_uuid)
    {
        $this->publishable_key = $publishable_key;
        $this->secret_key      = $secret_key;
        $this->site_uuid       = $site_uuid;
        $this->api_url         = $this->api_url_base . 'v' . $this->api_version;
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
        $url = add_query_arg(['site_uuid' => $this->site_uuid], $this->api_url . '/' . ltrim($endpoint, '/'));

        $wp_args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->publishable_key . ':' . $this->secret_key),
                'User-Agent'    => 'MailOptin; ' . home_url(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ]
        ];

        switch (strtolower($method)) {
            case 'post':
            case 'put':
            case 'delete':
                $wp_args['headers']["Content-Type"] = "application/json";
                $wp_args['body']                    = json_encode($args);
                break;
            case 'get':
                $url = add_query_arg($args, $url);
                break;
        }

        $response = wp_remote_request($url, $wp_args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);

        $response_http_code = wp_remote_retrieve_response_code($response);

        if ( ! is_http_code_success($response_http_code)) {
            throw new \Exception($response_body, $response_http_code);
        }

        return ['status_code' => $response_http_code, 'body' => json_decode($response_body)];
    }
}
