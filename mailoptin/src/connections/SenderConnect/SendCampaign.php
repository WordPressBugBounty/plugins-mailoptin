<?php

namespace MailOptin\Connections\SenderConnect;

use MailOptin\Core\PluginSettings\Settings;
use MailOptin\SenderConnect\AbstractSenderConnect;

class SendCampaign extends AbstractSenderConnect
{
    /** @var int ID of email campaign */
    public $email_campaign_id;

    /** @var int ID of campaign log */
    public $campaign_log_id;

    /** @var string campaign subject */
    public $campaign_subject;

    /** @var string campaign email in HTML */
    public $content_html;

    /** @var string campaign email in plain text */
    public $content_text;

    protected $campaign_title;

    /**
     * Constructor.
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
     * Create campaign via Sender.net API
     *
     * @return string
     *
     * @throws \Exception
     */
    public function create_campaign()
    {
        $list_id = $this->get_email_campaign_list_id($this->email_campaign_id);

        $payload = [
            'title'        => $this->campaign_title,
            'subject'      => $this->campaign_subject,
            'from'         => Settings::instance()->from_name(),
            'reply_to'     => Settings::instance()->from_email(),
            'content_type' => 'html',
            'content'      => $this->content_html,
            'groups'       => [$list_id],
        ];

        $payload = apply_filters('mailoptin_sender_campaign_settings', $payload, $this->email_campaign_id);

        $response = $this->sender_instance()->make_request('campaigns', $payload, 'post');

        if (isset($response['body']->data->id)) {
            return $response['body']->data->id;
        }

        throw new \Exception(wp_json_encode($response['body']), $response['status_code']);
    }

    /**
     * Send campaign via Sender.net API
     *
     * @return array
     */
    public function send()
    {
        try {

            $campaign_id = $this->create_campaign();

            $response = $this->sender_instance()->make_request(
                'campaigns/' . $campaign_id . '/send',
                [],
                'post'
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
