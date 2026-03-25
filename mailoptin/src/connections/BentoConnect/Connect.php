<?php

namespace MailOptin\BentoConnect;

use MailOptin\Core\Connections\ConnectionInterface;

use function MailOptin\Core\moVar;

class Connect extends AbstractBentoConnect implements ConnectionInterface
{
    public static $connectionName = 'BentoConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_filter('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'));

        parent::__construct();
    }

    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT,
            self::EMAIL_CAMPAIGN_SUPPORT
        ];
    }

    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Bento', 'mailoptin');

        return $connections;
    }

    public function replace_placeholder_tags($content, $type = 'html')
    {
        $search = [
            '{{webversion}}',
            '{{unsubscribe}}'
        ];

        $replace = [
            '{{ browser_url }}',
            '{{ visitor.unsubscribe_url }}',
        ];

        $content = str_replace($search, $replace, $content);

        return $this->replace_footer_placeholder_tags($content);
    }

    public function get_email_list()
    {
        if ('email' == moVar($_POST, 'ui') || isset($_GET['mailoptin_email_campaign_id'])) {

            $tag_array = [];

            try {

                $response = $this->bento_instance()->make_request('fetch/tags');

                if (isset($response['body']->data) && is_array($response['body']->data)) {

                    foreach ($response['body']->data as $tag) {
                        $tag_array[$tag->attributes->name] = $tag->attributes->name;
                    }
                }
            } catch (\Exception $e) {
                self::save_optin_error_log($e->getMessage(), 'bento');
            }

            return $tag_array;
        }

        return ['all' => __('All Contacts', 'mailoptin')];
    }

    /**
     * @param string $list_id *
     *
     * @return array
     */
    public function get_optin_fields($list_id = '')
    {
        $bucket = [];

        try {

            $response = $this->bento_instance()->make_request('fetch/fields');

            if ( ! empty($response['body']->data)) {

                foreach ($response['body']->data as $field) {

                    $field_key  = $field->attributes->key ?? '';
                    $field_name = ! empty($field->attributes->name) ? $field->attributes->name : $field_key;

                    if (empty($field_key) || in_array($field_key, ['first_name', 'last_name'])) continue;

                    $bucket[$field_key] = $field_name;
                }
            }

        } catch (\Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'bento');
        }

        return $bucket;
    }

    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return (new SendCampaign($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text))->send();
    }

    public function subscribe($email, $name, $list_id, $extras = null)
    {
        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function integration_customizer_settings($settings)
    {
        $settings['BentoConnect_lead_tags'] = apply_filters('mailoptin_customizer_optin_campaign_BentoConnect_lead_tags', '');

        return $settings;
    }

    /**
     * @param array $controls
     *
     * @return mixed
     */
    public function integration_customizer_controls($controls)
    {
        if (defined('MAILOPTIN_DETACH_LIBSODIUM') === true) {
            // always prefix with the name of the connect/connection service.

            $controls[] = [
                'field'       => 'text',
                'name'        => 'BentoConnect_lead_tags',
                'label'       => __('Tags', 'mailoptin'),
                'placeholder' => 'tag1, tag2',
                'description' => __('Enter comma-separated list of tags to assign to subscribers who opt-in via this campaign.', 'mailoptin'),
            ];

        } else {

            $content = sprintf(
                __("%sMailOptin Premium%s allows you to apply tags to subscribers.", 'mailoptin'),
                '<a target="_blank" href="https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=bento_connection">',
                '</a>',
                '<strong>',
                '</strong>'
            );

            // always prefix with the name of the connect/connection service.
            $controls[] = [
                'name'    => 'BentoConnect_upgrade_notice',
                'field'   => 'custom_content',
                'content' => $content
            ];
        }

        return $controls;
    }

    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}
