<?php

namespace MailOptin\Connections\ElasticEmailConnect;

use MailOptin\Core\PluginSettings\Settings;
use MailOptin\ElasticEmailConnect\AbstractElasticEmailConnect;

class SendCampaign extends AbstractElasticEmailConnect
{
    /** @var int ID of email campaign */
    public $email_campaign_id;

    /** @var int ID of campaign log */
    public $campaign_log_id;

    /** @var string campaign subject */
    public $campaign_subject;

    /** @var string campaign email in HTML */
    public $content_text;

    /** @var string campaign email in plain text */
    public $content_html;

    protected $campaign_title;

    /**
     * Constructor poop.
     *
     * @param int $email_campaign_id
     * @param int $campaign_log_id
     * @param string $campaign_subject
     * @param string $content_html
     * @param string $content_text
     */
    public function __construct($email_campaign_id, $campaign_log_id, $campaign_subject, $content_html, $content_text = '')
    {
        parent::__construct();

        $this->email_campaign_id = $email_campaign_id;
        $this->campaign_log_id   = $campaign_log_id;
        $this->campaign_subject  = $campaign_subject;
        $this->content_html      = $content_html;
        $this->content_text      = $content_text;

        $this->campaign_title = $this->get_email_campaign_campaign_title($this->email_campaign_id);
    }

    /**
     * @return string template name
     *
     * @throws \Exception
     */
    public function create_template()
    {
        $response = $this->elasticemail_instance()->post(
            'templates',
            [
                'Name' => sprintf('mailoptinee_%s_%s', sanitize_key($this->campaign_title), current_time('Y-m-d-H-i-s')),
                'Body' => [
                    [
                        'ContentType' => 'HTML',
                        'Content'     => $this->content_html
                    ]
                ],
            ]
        );

        if (isset($response['body']['Name'])) {
            return $response['body']['Name'];
        }

        throw new \Exception(wp_json_encode($response['body']), $response['status_code']);
    }

    public function delete_used_templates()
    {
        try {

            $response = $this->elasticemail_instance()->make_request(
                'templates',
                ['scopeType' => 'Personal', 'templateTypes' => 'RawHTML', 'limit' => 50]
            );

            if (isset($response['body']) && is_array($response['body'])) {
                foreach ($response['body'] as $template) {
                    if (strstr($template['Name'], 'mailoptinee_')) {
                        $this->elasticemail_instance()->make_request("templates/" . $template['Name'], [], 'delete');
                    }
                }
            }

        } catch (\Exception $e) {
        }
    }

    /**
     * Send campaign via Aweber.
     *
     * @return array
     */
    public function send()
    {
        try {

            $this->delete_used_templates();

            $template_name = $this->create_template();

            $list_id = $this->get_email_campaign_list_id($this->email_campaign_id);

            $payload = [
                'Name'       => $this->campaign_title . current_time('mysql'),
                'Recipients' => [
                    'ListNames' => [$list_id]
                ],
                'Content'    => [
                    [
                        'From'         => Settings::instance()->from_email(),
                        'ReplyTo'      => Settings::instance()->reply_to(),
                        'Subject'      => $this->campaign_subject,
                        'TemplateName' => $template_name,

                    ]
                ]
            ];

            $response = $this->elasticemail_instance()->post(
                'campaigns',
                apply_filters('mailoptin_elastic_email_campaign_settings', $payload, $this->email_campaign_id)
            );

            if (self::is_http_code_success($response['status_code'])) {
                return self::ajax_success();
            }

            $err = __('Unexpected error. Please try again', 'mailoptin');
            self::save_campaign_error_log(wp_json_encode($response['body']), $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure($err);

        } catch (\Exception $e) {
            self::save_campaign_error_log($e->getMessage(), $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure($e->getMessage());
        }
    }
}