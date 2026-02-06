<?php

namespace MailOptin\MailercloudConnect;

use MailOptin\Core\Repositories\EmailCampaignRepository;

class SendCampaign extends AbstractMailercloudConnect
{
    /** @var int */
    protected $email_campaign_id;

    /** @var int */
    protected $campaign_log_id;

    /** @var string */
    protected $subject;

    /** @var string */
    protected $content_html;

    /** @var string */
    protected $content_text;

    /**
     * Constructor.
     *
     * @param int $email_campaign_id
     * @param int $campaign_log_id
     * @param string $subject
     * @param string $content_html
     * @param string $content_text
     */
    public function __construct($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text = '')
    {
        parent::__construct();

        $this->email_campaign_id = $email_campaign_id;
        $this->campaign_log_id   = $campaign_log_id;
        $this->subject           = $subject;
        $this->content_html      = $content_html;
        $this->content_text      = $content_text;
    }

    /**
     * Send campaign via MailerCloud.
     *
     * @return array
     */
    public function send()
    {
        try {

            $list_id = EmailCampaignRepository::get_customizer_value($this->email_campaign_id, 'connection_email_list');

            $campaign_title = EmailCampaignRepository::get_email_campaign_name($this->email_campaign_id);

            $sender_name  = $this->plugin_settings->from_name();
            $sender_email = $this->plugin_settings->from_email();

            $campaign_data = [
                'name'               => $campaign_title,
                'subject'            => $this->subject,
                'sender'             => [
                    'sender_name'  => $sender_name,
                    'sender_email' => $sender_email
                ],
                'html'               => $this->content_html,
                'list_ids'           => [$list_id],
                'viewin_browser'     => false,
                'mailing_preference' => false,
                'scheduled_at'       => wp_date('Y-m-d H:i:s', strtotime('+1 minute')),
            ];

            $campaign_data = apply_filters('mo_connections_mailercloud_campaign_settings', $campaign_data, $this->email_campaign_id);

            $response = $this->mailercloud_instance()->post('campaign', $campaign_data);

            if (self::is_http_code_success($response['status_code'])) {
                return parent::ajax_success();
            }

            $error_message = $response['body']->message ?? json_encode($response['body']);
            self::save_campaign_error_log($error_message, $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure();

        } catch (\Exception $e) {
            self::save_campaign_error_log($e->getMessage(), $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure();
        }
    }
}
