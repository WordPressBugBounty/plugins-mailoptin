<?php

namespace MailOptin\BentoConnect;

use MailOptin\Core\PluginSettings\Settings;

class SendCampaign extends AbstractBentoConnect
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
    }

    /**
     * @return array
     */
    public function send()
    {
        try {

            $list_id = $this->get_email_campaign_list_id($this->email_campaign_id);

            $payload = apply_filters(
                'mailoptin_activecampaign_message_settings',
                [
                    'name'           => $this->get_email_campaign_campaign_title($this->email_campaign_id),
                    'subject'        => $this->campaign_subject,
                    'content'        => $this->content_html,
                    'type'           => 'raw',
                    'from'           => [
                        'name'  => Settings::instance()->from_name(),
                        'email' => Settings::instance()->from_email(),
                    ],
                    'inclusive_tags' => $list_id,

                ],
                $this->email_campaign_id
            );

            $response = $this->bento_instance()->make_request('batch/broadcasts', ['broadcasts' => [$payload]], 'post');

            if (self::is_http_code_success($response['status_code'])) return self::ajax_success();

            $err = __('Unexpected error. Please try again', 'mailoptin');
            self::save_campaign_error_log($err, $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure($err);

        } catch (\Exception $e) {
            self::save_campaign_error_log($e->getMessage(), $this->campaign_log_id, $this->email_campaign_id);

            return parent::ajax_failure($e->getMessage());
        }
    }
}