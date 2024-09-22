<?php

namespace MailOptin\ForminatorFormConnect;

use MailOptin\Core\Repositories\ConnectionsRepository;

class FFMailOptin extends \Forminator_Integration
{
    protected $_slug = 'mailoptin';

    protected $_version = MAILOPTIN_VERSION_NUMBER;

    protected $_min_forminator_version = '1.30.0';

    protected $_short_title = 'MailOptin';

    protected $_title = 'MailOptin';

    protected $_url = 'https://mailoptin.io/pricing/';

    /**
     * Hold account information that currently connected
     * Will be saved to @see FFMailOptin::save_settings_values()
     *
     * @var array
     */
    private $_connected_account = array();

    protected $_position = 8;

    /**
     * FFMailOptin constructor.
     * - Set dynamic translatable text(s) that will be displayed to end-user
     * - Set dynamic icons and images
     *
     * MailOptin Addon
     */
    public function __construct()
    {
        // late init to allow translation
        $this->_description                = __('Get awesome by your form', 'mailoptin');

        add_filter('forminator_addon_mailoptin_form_settings_class_name', [$this, 'register_form_settings_class']);
        add_filter('forminator_addon_mailoptin_quiz_settings_class_name', [$this, 'register_quiz_settings_class']);
    }

    public function register_form_settings_class()
    {
        return 'MailOptin\ForminatorFormConnect\ConnectionFormSettingsPage';
    }

    public function register_quiz_settings_class()
    {
        return 'MailOptin\ForminatorFormConnect\ConnectionQuizSettingsPage';
    }

    public function get_icon()
    {
        return MAILOPTIN_ASSETS_URL . 'images/forminator-addon-icon.png';
    }

    public function get_icon_x2()
    {
        return MAILOPTIN_ASSETS_URL . 'images/forminator-addon-icon@2x.png';
    }

    public function get_image()
    {
        return MAILOPTIN_ASSETS_URL . 'images/forminator-addon-icon.png';
    }

    public function get_image_x2()
    {
        return MAILOPTIN_ASSETS_URL . 'images/forminator-addon-icon@2x.png';
    }

    /**
     * Hook before save settings values
     *
     * for future reference
     *
     * MailOptin Addon
     *
     * @param array $values
     *
     * @return array
     */
    public function before_save_settings_values($values)
    {
        forminator_addon_maybe_log(__METHOD__, $values);

        if ( ! empty($this->_connected_account)) {
            $values['connected_account'] = $this->_connected_account;
        }

        return $values;
    }

    /**
     * Flag for check whether mailoptin addon is connected globally
     *
     * MailOptin Addon
     * @return bool
     */
    public function is_authorized()
    {
        return true;
    }

    /**
     * Settings wizard
     *
     * mailOptin Addon
     * @return array
     */
    public function settings_wizards()
    {
        return [
            [
                'callback'     => [$this, 'connect_mailoptin'],
                'is_completed' => [$this, 'is_authorized'],
            ]
        ];
    }

    /**
     * Wizard of connect_mailoptin
     *
     * MailOptin Addon
     *
     * @param     $submitted_data
     * @param int $form_id
     *
     * @return array
     */
    public function connect_mailoptin($submitted_data, $form_id = 0)
    {
        $link = '';
        if ( ! $this->is_connected()) {
            $link = '<a href="' . MAILOPTIN_CONNECTIONS_SETTINGS_PAGE . '" class="button button-secondary">' . __('Connect Now', 'mailoptin') . '</a>';

            $html = __('No Integration Connected to MailOptin', 'mailoptin');
        } else {
            $html = __('Connected', 'mailoptin');
        }

        return [
            'html' => '<div class="integration-header">
					<h3 class="sui-box-title" id="dialogTitle2">' . sprintf(__('%1$s with Forminator', 'mailoptin'), 'MailOptin') . '</h3> 
				</div>
				<div class="sui-form-field" style="text-align: center">
				    <h3>' . $html . '</h3>
				    <div>' . $link . '</div>
                </div>
				',
        ];
    }


    public function email_service_providers()
    {
        $connections = ConnectionsRepository::get_connections();

        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) {
            $connections['leadbank'] = __('MailOptin Leads', 'mailoptin');
        }

        //escape webhook connection
        unset($connections['WebHookConnect']);

        return $connections;
    }

    public function email_providers_and_lists()
    {
        $data = [];

        foreach ($this->email_service_providers() as $key => $value) {

            if ($key == 'leadbank') continue;

            $data[$value] = ConnectionsRepository::connection_email_list($key);
        }

        return $data;
    }


    /**
     * Get addon instance
     *
     * MailOptin Addon
     * @return self|null
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