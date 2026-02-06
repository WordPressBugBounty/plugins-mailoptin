<?php

namespace MailOptin\MailercloudConnect;

class APIClass
{
    protected $api_key;

    protected $api_base_url = 'https://cloudapi.mailercloud.com/v1';

    public function __construct($api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Make HTTP request to Mailercloud API
     *
     * @param string $endpoint
     * @param array $args
     * @param string $method
     *
     * @return array
     */
    public function make_request($endpoint, $args = [], $method = 'get')
    {
        $url = $this->api_base_url . '/' . ltrim($endpoint, '/');

        $wp_args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => $this->api_key,
                'Content-Type'  => 'application/json',
            ]
        ];

        if (in_array($method, ['post', 'put', 'patch']) && ! empty($args)) {
            $wp_args['body'] = json_encode($args);
        }

        if ($method == 'get' && ! empty($args)) {
            $url = add_query_arg($args, $url);
        }

        $response = wp_remote_request($url, $wp_args);

        if (is_wp_error($response)) {
            return [
                'status_code' => 0,
                'body'        => (object)['message' => $response->get_error_message()]
            ];
        }

        $response_body      = json_decode(wp_remote_retrieve_body($response));
        $response_http_code = wp_remote_retrieve_response_code($response);

        return [
            'status_code' => $response_http_code,
            'body'        => $response_body
        ];
    }

    /**
     * @param $endpoint
     * @param array $args
     *
     * @return array
     */
    public function post($endpoint, $args = [])
    {
        return $this->make_request($endpoint, $args, 'post');
    }

    /**
     * @param $endpoint
     * @param array $args
     *
     * @return array
     */
    public function get($endpoint, $args = [])
    {
        return $this->make_request($endpoint, $args, 'get');
    }

    /**
     * @param $endpoint
     * @param array $args
     *
     * @return array
     */
    public function put($endpoint, $args = [])
    {
        return $this->make_request($endpoint, $args, 'put');
    }

    /**
     * @param $endpoint
     * @param array $args
     *
     * @return array
     */
    public function delete($endpoint, $args = [])
    {
        return $this->make_request($endpoint, $args, 'delete');
    }
}
