<?php

namespace MailOptin\GoogleSheetConnect;

use Authifly\Provider\Google;
use Authifly\Storage\OAuthCredentialStorage;
use Exception;
use MailOptin\Core\Connections\ConnectionInterface;
use MailOptin\Core\PluginSettings\Connections;

use function MailOptin\Core\current_user_has_privilege;
use function MailOptin\Core\moVar;
use function MailOptin\Core\moVarPOST;

define('MAILOPTIN_GOOGLE_SHEET_CONNECT_ASSETS_URL', plugins_url('assets/', __FILE__));

class Connect extends AbstractGoogleSheetConnect implements ConnectionInterface
{
    /**
     * @var string key of connection service. its important all connection name ends with "Connect"
     */
    public static $connectionName = 'GoogleSheetConnect';

    public function __construct()
    {
        ConnectSettingsPage::get_instance();

        add_filter('mailoptin_registered_connections', array($this, 'register_connection'));

        add_filter('mo_optin_form_integrations_default', array($this, 'integration_customizer_settings'));
        add_action('mo_optin_integrations_controls_after', array($this, 'integration_customizer_controls'), 10, 4);

        add_action('admin_init', [$this, 'authorize_integration']);

        add_action(
            'wp_ajax_mailoptin_customizer_fetch_gsheetfile_sheets',
            [$this, 'customizer_fetch_gsheetfile_sheets']
        );

        add_action('mo_optin_integration_control_enqueue', function () {
            wp_enqueue_script(
                'mo-google-sheet-field-control',
                MAILOPTIN_GOOGLE_SHEET_CONNECT_ASSETS_URL . 'gsheet.js',
                array('jquery', 'customize-controls'),
                MAILOPTIN_VERSION_NUMBER
            );
        });

        parent::__construct();
    }

    public static function features_support()
    {
        return [
            self::OPTIN_CAMPAIGN_SUPPORT,
            self::OPTIN_CUSTOM_FIELD_SUPPORT,
            self::FULL_FIELDS_MAPPING_SUPPORT
        ];
    }

    /**
     * Register Google Sheets Connection.
     *
     * @param array $connections
     *
     * @return array
     */
    public function register_connection($connections)
    {
        $connections[self::$connectionName] = __('Google Sheets', 'mailoptin');

        return $connections;
    }

    public function authorize_integration()
    {
        if ( ! current_user_has_privilege()) return;

        if ( ! isset($_GET['moauth']) || ($_GET['moauth'] != 'gsheet')) return;

        $connections_settings = Connections::instance(true);
        $gsheet_client_id     = $connections_settings->gsheet_client_id();
        $gsheet_client_secret = $connections_settings->gsheet_client_secret();

        $callback_url = self::callback_url();
        if (defined('W3GUY_LOCAL')) {
            $callback_url = str_replace(home_url(), 'https://w3guy.dev', $callback_url);
        }

        $config = [
            'callback' => $callback_url,
            'keys'     => ['id' => $gsheet_client_id, 'secret' => $gsheet_client_secret],
            'scope'    => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/drive'
        ];

        $instance = new Google($config, null, new OAuthCredentialStorage());

        try {

            $instance->authenticate();

            $access_token = $instance->getAccessToken();

            $old_data = get_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME, []);

            $new_data = [
                'gsheet_access_token'  => moVar($access_token, 'access_token'),
                'gsheet_refresh_token' => moVar($access_token, 'refresh_token'),
                'gsheet_expires_at'    => $this->oauth_expires_at_transform(moVar($access_token, 'expires_at'))
            ];

            $new_data = array_filter($new_data, [$this, 'data_filter']);

            update_option(MAILOPTIN_CONNECTIONS_DB_OPTION_NAME, array_merge($old_data, $new_data));

            $connection = self::$connectionName;

            // delete connection cache
            delete_transient("_mo_connection_cache_$connection");

        } catch (Exception $e) {

            self::save_optin_error_log($e->getMessage(), 'googlesheet');
        }

        $instance->disconnect();

        wp_redirect(MAILOPTIN_CONNECTIONS_SETTINGS_PAGE);
        exit;
    }

    public function customizer_fetch_gsheetfile_sheets()
    {
        check_ajax_referer('customizer-fetch-email-list', 'security');

        \MailOptin\Core\current_user_has_privilege() || exit;

        $list_id = sanitize_text_field($_REQUEST['list_id']);

        wp_send_json_success($this->get_spreadsheet_sheets($list_id));
    }

    /**
     * Fulfill interface contract.
     *
     * {@inheritdoc}
     */
    public function replace_placeholder_tags($content, $type = 'html')
    {
        return $this->replace_footer_placeholder_tags($content);
    }

    /**
     * {@inherit_doc}
     *
     * Return array of email list
     *
     * @return mixed
     */
    public function get_email_list()
    {
        try {

            $bucket = get_transient('mo_connections_google_sheet_files');

            // Check if cached data exists
            if (empty($bucket)) {

                $bucket = [];

                $response = $this->gsheetInstance('drive')->apiRequest(
                    'drive/v3/files?pageSize=1000&q=' . rawurlencode('mimeType="application/vnd.google-apps.spreadsheet" and trashed=false')
                );

                // Validate API response
                if (is_object($response) && isset($response->kind, $response->files) && $response->kind === 'drive#fileList') {
                    foreach ($response->files as $file) {
                        if (isset($file->id, $file->name)) {
                            $bucket[$file->id] = $file->name;
                        }
                    }

                    set_transient('mo_connections_google_sheet_files', $bucket, 3 * DAY_IN_SECONDS);
                } else {
                    self::save_optin_error_log('Invalid API response format', 'googlesheet');

                    return [];
                }
            }

            // Return cached data
            return $bucket;

        } catch (Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'googlesheet');
            // Optionally, delete the transient to avoid serving stale data
            delete_transient('mo_connections_google_sheet_files');

            return [];
        }
    }

    /**
     * @param $spreadsheet_id
     *
     * @return array
     */
    public function get_spreadsheet_sheets($spreadsheet_id)
    {
        if (empty($spreadsheet_id)) return [];

        try {

            $bucket = get_transient('mo_connections_google_sheet_files_sheets');

            if ( ! is_array($bucket)) $bucket = [];

            // Check if cached data exists for the current $spreadsheet_id
            if (empty($bucket[$spreadsheet_id])) {

                $response = $this->gsheetInstance()->apiRequest($spreadsheet_id . '?includeGridData=false');

                if (is_object($response) && isset($response->sheets) && is_array($response->sheets)) {

                    $bucket[$spreadsheet_id] = [];

                    // Parse API response and populate the cache
                    foreach ($response->sheets as $sheet) {
                        if (isset($sheet->properties->title)) {
                            $bucket[$spreadsheet_id][$sheet->properties->title] = $sheet->properties->title;
                        }
                    }

                    set_transient('mo_connections_google_sheet_files_sheets', $bucket, 3 * DAY_IN_SECONDS);

                } else {
                    self::save_optin_error_log('Invalid API response format', 'googlesheet');

                    return [];
                }
            }

            // Return cached data
            return $bucket[$spreadsheet_id];

        } catch (Exception $e) {

            self::save_optin_error_log($e->getMessage(), 'googlesheet');

            // Optionally, delete the transient for this $spreadsheet_id to avoid serving stale data
            if (isset($bucket[$spreadsheet_id])) {
                unset($bucket[$spreadsheet_id]);
                set_transient('mo_connections_google_sheet_files_sheets', $bucket, 3 * DAY_IN_SECONDS);
            }
        }

        // Return an empty array in case of errors
        return [];
    }

    public function get_optin_fields($list_id = '')
    {
        $sheet_name = moVarPOST('GoogleSheetConnect_file_sheets', '');

        return $this->get_sheet_header_columns($list_id, $sheet_name);
    }

    public function get_sheet_header_columns($sheet_file, $sheet_name)
    {
        if (empty($sheet_file)) return [];

        try {

            if (empty($sheet_name)) {
                $sheets = $this->get_spreadsheet_sheets($sheet_file);
                if (is_array($sheets) && ! empty($sheets)) {
                    $sheet_name = array_shift($sheets);
                }
            }

            if (empty($sheet_name)) return [];

            $cache_key = sprintf('%s_%s', $sheet_file, $sheet_name);

            $bucket = get_transient('mo_connections_google_sheets_columns');

            if ( ! is_array($bucket)) $bucket = [];

            if (empty($bucket[$cache_key])) {

                $response = $this->gsheetInstance()->apiRequest(sprintf('%s/values/%s!1:1', $sheet_file, rawurlencode($sheet_name)));

                if (is_object($response) && isset($response->values[0]) && is_array($response->values[0])) {

                    $bucket[$cache_key] = [];

                    foreach ($response->values[0] as $field) {
                        $bucket[$cache_key][$field] = $field;
                    }

                    set_transient('mo_connections_google_sheets_columns', $bucket, 3 * DAY_IN_SECONDS);

                } else {
                    self::save_optin_error_log('Invalid API response format', 'googlesheet');

                    return [];
                }
            }

            return $bucket[$cache_key];

        } catch (Exception $e) {
            self::save_optin_error_log($e->getMessage(), 'googlesheet');

            if (isset($bucket[$cache_key])) {
                unset($bucket[$cache_key]);
                set_transient('mo_connections_google_sheets_columns', $bucket, 3 * DAY_IN_SECONDS);
            }
        }

        // Return an empty array in case of errors
        return [];
    }


    /**
     * @param $controls
     * @param $optin_campaign_id
     * @param $index
     * @param $saved_values
     *
     * @return array
     */
    public function integration_customizer_controls($controls, $optin_campaign_id, $index, $saved_values)
    {
        $sheets = [];

        if (isset($index)) {
            $list_id = $saved_values[$index]['connection_email_list'] ?? '';
            $sheets  = $this->get_spreadsheet_sheets($list_id);
        }

        $controls[] = [
            'field'   => 'select',
            'label'   => __('Select Sheet', 'mailoptin'),
            'name'    => 'GoogleSheetConnect_file_sheets',
            'choices' => ['' => '&mdash;&mdash;&mdash;'] + $sheets,
            'class'   => 'gsheet-group-block'
        ];

        return $controls;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public function integration_customizer_settings($settings)
    {
        $settings['GoogleSheetConnect_file_sheets'] = [];

        return $settings;
    }

    /**
     * @param int $email_campaign_id
     * @param int $campaign_log_id
     * @param string $subject
     * @param string $content_html
     * @param string $content_text
     *
     * @return array
     * @throws Exception
     *
     */
    public function send_newsletter($email_campaign_id, $campaign_log_id, $subject, $content_html, $content_text)
    {
        return [];
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $list_id ID of email list to add subscriber to
     * @param mixed|null $extras
     *
     * @return mixed|false
     */
    public function subscribe($email, $name, $list_id, $extras = null)
    {
        if ( ! defined('MAILOPTIN_DETACH_LIBSODIUM')) return false;

        return (new Subscription($email, $name, $list_id, $extras, $this))->subscribe();
    }

    /**
     *
     * @return Connect|null
     */
    public static function get_instance()
    {
        static $instance = null;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
}