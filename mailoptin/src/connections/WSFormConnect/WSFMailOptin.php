<?php

namespace MailOptin\WSFormConnect;

use MailOptin\Connections\Init;
use MailOptin\Core\AjaxHandler;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\Connections\ConnectionFactory;
use MailOptin\Core\OptinForms\ConversionDataBuilder;
use MailOptin\Core\Repositories\ConnectionsRepository;
use WS_Form_Common;

define('MAILOPTIN_WSFORM_ASSETS_URL', plugins_url('assets/', __FILE__));

class WSFMailOptin extends \WS_Form_Action
{
    public $id = 'mailoptin';
    public $pro_required = false;
    public $label;
    public $label_action;
    public $events;
    public $multiple = true;
    public $configured = false;
    public $priority = 50;
    public $can_repost = true;
    public $form_add = false;

    // Config
    private $api = false;
    public $integration = false;
    public $list_id = false;
    public $double_optin;
    public $field_mapping;
    public $opt_in_field;
    public $tags;
    public $select_tags;

    public function __construct()
    {
        $this->label = __('MailOptin', 'mailoptin');

        // Set label for actions pull down
        $this->label_action = __('MailOptin', 'mailoptin');

        // Events
        $this->events = ['submit'];

        parent::register($this);

        // Register config filters
        add_filter('wsf_config_meta_keys', [$this, 'config_meta_keys'], 10, 2);
        add_action('rest_api_init', [$this, 'rest_api_init'], 10, 0);
        add_filter('wsf_config_settings_form_admin', [$this, 'config_settings_form_admin'], 10, 2);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
        $screen = get_current_screen();

        if (isset($screen->id) && strpos($screen->id, 'ws-form') !== false) {

            wp_enqueue_script(
                'mo-wsform-js-override',
                MAILOPTIN_WSFORM_ASSETS_URL . 'js/sidebar_condition_process-override.js',
                ['jquery'],
                MAILOPTIN_VERSION_NUMBER,
                true
            );

            $upsell_url = 'https://mailoptin.io/pricing/?utm_source=wp_dashboard&utm_medium=upgrade&utm_campaign=wsform_builder_settings';

            wp_localize_script('mo-wsform-js-override', 'moWSForm', [
                'upsell'     => '<div class="wsf-sidebar-upgrade">' . sprintf(
                        esc_html__('Upgrade to %s to remove the 500 subscribers monthly limit, add support for custom field mapping and assign tags to subscribers.', 'mailoptin'),
                        '<a target="_blank" href="' . $upsell_url . '"><strong>MailOptin premium</strong></a>'
                    ) . '</div>',
                'is_premium' => defined('MAILOPTIN_DETACH_LIBSODIUM') ? 'true' : 'false'
            ]);
        }
    }

    public function config_settings_form_admin($settings)
    {
        // these configs are to cater for lite version
        $settings['icons']['reload']   = \WS_Form_Config_SVG::get_icon_16_svg('reload');
        $settings['icons']['exchange'] = \WS_Form_Config_SVG::get_icon_16_svg('exchange');

        $settings['language']['auto_map']          = __('Auto Map', 'mailoptin');
        $settings['language']['action_api_reload'] = __('Update', 'mailoptin');

        return $settings;
    }

    public function post($form, $submit, $config)
    {
        // reset integration and list for cases where two or more connections are made
        $this->integration = false;
        $this->list_id     = false;

        // Load configuration
        self::load_config($config);

        if ($this->integration === false) {
            return self::error(__('Integration is not set', 'mailoptin'));
        }

        if ($this->list_id === false) {
            return self::error(__('List ID is not set', 'mailoptin'));
        }

        $merge_fields = [];

        foreach ($this->field_mapping as $field_map) {

            $field_id = $field_map['ws_form_field'];

            if ($field_id == '') continue;

            $merge_field = $field_map['action_' . $this->id . '_list_fields'];

            if ($merge_field == '') continue;

            // Get submit value
            $submit_value = parent::get_submit_value($submit, WS_FORM_FIELD_PREFIX . $field_id, false);
            if ($submit_value === false) continue;

            if (is_array($submit_value)) {
                $submit_value = implode(',', $submit_value);
            }

            $merge_fields[$merge_field] = $submit_value;
        }

        // Before proceeding make sure required fields are configured.
        if (empty($merge_fields['moEmail'])) return self::error(__('Email address not mapped', 'mailoptin'));

        $fullname = Init::return_name(
            $merge_fields['moName'] ?? '',
            $merge_fields['moFirstName'] ?? '',
            $merge_fields['moLastName'] ?? ''
        );

        // Get opt in value (False if field not submitted)
        $opt_in_field_value = parent::get_submit_value($submit, WS_FORM_FIELD_PREFIX . $this->opt_in_field, false);

        if (($this->opt_in_field !== false) && ($this->opt_in_field !== '') && ($opt_in_field_value !== false)) {
            // End user did not opt in, exit gracefully
            if (empty($opt_in_field_value)) {

                self::success(__('User did not opt in, no data pushed to action', 'mailoptin'));

                return true;
            }
        }

        $double_optin = false;
        if (in_array($this->integration, Init::double_optin_support_connections(true))) {
            $double_optin = $this->double_optin && $this->double_optin == 'on';
        }

        $optin_data = new ConversionDataBuilder();
        // since it's non mailoptin form, set it to zero.
        $optin_data->optin_campaign_id = 0;
        $optin_data->payload           = [];
        $optin_data->email             = $merge_fields['moEmail'];

        if ( ! empty($fullname)) {
            $optin_data->name = $fullname;
        }

        $optin_data->optin_campaign_type       = 'WS Form';
        $optin_data->connection_service        = $this->integration;
        $optin_data->connection_email_list     = $this->list_id ?? '';
        $optin_data->is_timestamp_check_active = false;
        $optin_data->is_double_optin           = $double_optin;
        $optin_data->user_agent                = esc_html($_SERVER['HTTP_USER_AGENT']);

        if ( ! empty($submit->meta['post_id'])) {
            $optin_data->conversion_page = esc_url_raw(get_permalink($submit->meta['post_id']));
        }

        foreach ($merge_fields as $key => $value) {
            // Don't include Email or Full name fields.
            if (in_array($key, ['moEmail', 'moName', 'moFirstName', 'moLastName'])) continue;

            if (empty($value)) continue;

            // we are populating the payload var because when it is used for lookup to get the value.
            $optin_data->payload[$key] = $value;

            $optin_data->form_custom_field_mappings[$key] = $key;
        }

        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) {

            $tags = [];

            if (in_array($this->integration, Init::select2_tag_connections()) && ! empty($this->select_tags)) {

                foreach ($this->select_tags as $tag_map) {

                    $tag = $tag_map['action_' . $this->id . '_list_tag'];

                    if ($tag == '') continue;

                    $tags[] = trim($tag);
                }

                $optin_data->form_tags = $tags;
            }

            if (in_array($this->integration, Init::text_tag_connections()) && ! empty($this->tags)) {

                foreach ($this->tags as $tag_map) {
                    // Get tag
                    $tag = $tag_map['action_' . $this->id . '_tag'];

                    if ($tag == '') continue;

                    $tags[] = $tag;
                }

                $optin_data->form_tags = implode(',', $tags);
            }
        }

        $response = AjaxHandler::do_optin_conversion($optin_data);

        if ( ! AbstractConnect::is_ajax_success($response)) {
            self::error($response['message']);
        }

        return true;
    }

    public function load_config($config = [])
    {
        if ($this->integration === false) {
            $this->integration = parent::get_config($config, 'action_' . $this->id . '_integration');
        }

        if ($this->list_id === false) {
            $this->list_id = parent::get_config($config, 'action_' . $this->id . '_list_id');
        }

        $this->field_mapping = parent::get_config($config, 'action_' . $this->id . '_field_mapping', []);

        if ( ! is_array($this->field_mapping)) {
            $this->field_mapping = [];
        }

        $this->opt_in_field = parent::get_config($config, 'action_' . $this->id . '_opt_in_field');

        if (defined('MAILOPTIN_DETACH_LIBSODIUM')) {

            $this->double_optin = parent::get_config($config, 'action_' . $this->id . '_double_optin');
            $this->tags         = parent::get_config($config, 'action_' . $this->id . '_tags', []);

            if ( ! is_array($this->tags)) $this->tags = [];

            $this->select_tags = parent::get_config($config, 'action_' . $this->id . '_select_tags', []);

            if ( ! is_array($this->select_tags)) $this->select_tags = [];
        }
    }

    // Get settings
    public function get_action_settings()
    {
        $settings = [
            'meta_keys' => [
                'action_' . $this->id . '_integration',
                'action_' . $this->id . '_list_id',
                'action_' . $this->id . '_field_mapping',
                'action_' . $this->id . '_double_optin',
                'action_' . $this->id . '_opt_in_field',
                'action_' . $this->id . '_tags',
                'action_' . $this->id . '_select_tags'
            ]
        ];

        if ( ! defined('MAILOPTIN_DETACH_LIBSODIUM')) {
            unset($settings['meta_keys'][3]);
            unset($settings['meta_keys'][5]);
            unset($settings['meta_keys'][6]);
        }

        // Wrap settings so they will work with sidebar_html function in admin.js
        $settings = parent::get_settings_wrapper($settings);

        // Add labels
        $settings->label        = $this->label;
        $settings->label_action = $this->label_action;

        // Add multiple
        $settings->multiple = $this->multiple;

        // Add events
        $settings->events = $this->events;

        // Add can_repost
        $settings->can_repost = $this->can_repost;

        // Apply filter
        $settings = apply_filters('wsf_action_' . $this->id . '_settings', $settings);

        return $settings;
    }

    // Meta keys for this action
    public function config_meta_keys($meta_keys = [], $form_id = 0)
    {
        $config_meta_keys = [
            // Integration ID
            'action_' . $this->id . '_integration'   => [
                'label'                       => __('Select Integration', 'mailoptin'),
                'type'                        => 'select',
                'options'                     => 'action_api_populate',
                'options_blank'               => __('Select...', 'mailoptin'),
                'options_action_id_meta_key'  => 'action_id',
                'options_action_api_populate' => 'lists',
                'reload'                      => [
                    'action_id' => $this->id,
                    'method'    => 'lists_fetch'
                ],
            ],
            // List ID
            'action_' . $this->id . '_list_id'       => [
                'label'                       => __('Select List', 'mailoptin'),
                'type'                        => 'select',
                'help'                        => __('Select the list for the integration above.', 'mailoptin'),
                'options'                     => 'action_api_populate',
                'options_blank'               => __('Select...', 'mailoptin'),
                'options_action_id_meta_key'  => 'action_id',
                'options_action_api_populate' => 'list_subs',
                'options_list_id_meta_key'    => 'action_' . $this->id . '_integration',
                'reload'                      => [
                    'action_id'        => $this->id,
                    'method'           => 'list_subs_fetch',
                    'list_id_meta_key' => 'action_' . $this->id . '_integration',
                ],
                'condition'                   => [
                    [
                        'logic'      => '!=',
                        'meta_key'   => 'action_' . $this->id . '_integration',
                        'meta_value' => ''
                    ]
                ]
            ],
            // Double opt in
            'action_' . $this->id . '_double_optin'  => [
                'label'     => __('Double optin', 'mailoptin'),
                'type'      => 'checkbox',
                'help'      => __('Double optin requires users to confirm their email address before they are added or subscribed.', 'mailoptin'),
                'default'   => '',
                'condition' => [
                    [
                        'logic'      => 'belong_to',
                        'meta_key'   => 'action_' . $this->id . '_integration',
                        'meta_value' => Init::double_optin_support_connections(true),
                    ],
                    [
                        'logic'          => '!=',
                        'meta_key'       => 'action_' . $this->id . '_list_id',
                        'meta_value'     => '',
                        'logic_previous' => '&&'
                    ],
                ]
            ],
            // Opt-In field
            'action_' . $this->id . '_opt_in_field'  => [
                'label'              => __('Opt-In Field', 'mailoptin'),
                'type'               => 'select',
                'options'            => 'fields',
                'options_blank'      => __('Select...', 'mailoptin'),
                'fields_filter_type' => ['select', 'checkbox', 'radio'],
                'help'               => __('Checkbox recommended', 'mailoptin'),
                'condition'          => [
                    [
                        'logic'      => '!=',
                        'meta_key'   => 'action_' . $this->id . '_list_id',
                        'meta_value' => ''
                    ]
                ]
            ],
            // Field mapping
            'action_' . $this->id . '_field_mapping' => [
                'label'            => __('List Fields', 'mailoptin'),
                'type'             => 'repeater',
                'meta_keys'        => [
                    'ws_form_field',
                    'action_' . $this->id . '_list_fields'
                ],
                'meta_keys_unique' => [
                    'action_' . $this->id . '_list_fields'
                ],
                'reload'           => [
                    'action_id'            => $this->id,
                    'method'               => 'list_fields_fetch',
                    'list_id_meta_key'     => 'action_' . $this->id . '_integration',
                    'list_sub_id_meta_key' => 'action_' . $this->id . '_list_id',
                ],
                'auto_map'         => true,
                'condition'        => [
                    [
                        'logic'      => '!=',
                        'meta_key'   => 'action_' . $this->id . '_list_id',
                        'meta_value' => ''
                    ]
                ]
            ],
            // Tags
            'action_' . $this->id . '_tags'          => [
                'label'     => __('Tags', 'mailoptin'),
                'type'      => 'repeater',
                'meta_keys' => [
                    'action_' . $this->id . '_tag',
                ],
                'condition' => [
                    [
                        'logic'      => 'belong_to',
                        'meta_key'   => 'action_' . $this->id . '_integration',
                        'meta_value' => Init::text_tag_connections(),
                    ],
                    [
                        'logic'          => '!=',
                        'meta_key'       => 'action_' . $this->id . '_list_id',
                        'meta_value'     => '',
                        'logic_previous' => '&&'
                    ],
                ],
            ],
            // Tags
            'action_' . $this->id . '_select_tags'   => [
                'label'            => __('Tags', 'mailoptin'),
                'type'             => 'repeater',
                'meta_keys'        => [
                    'action_' . $this->id . '_list_tag'
                ],
                'meta_keys_unique' => [
                    'action_' . $this->id . '_list_tag'
                ],
                'reload'           => [
                    'action_id'        => $this->id,
                    'method'           => 'list_fetch',
                    'list_id_meta_key' => 'action_' . $this->id . '_integration',
                ],
                'condition'        => [
                    [
                        'logic'      => 'belong_to',
                        'meta_key'   => 'action_' . $this->id . '_integration',
                        'meta_value' => Init::select2_tag_connections(),
                    ],
                    [
                        'logic'          => '!=',
                        'meta_key'       => 'action_' . $this->id . '_list_id',
                        'meta_value'     => '',
                        'logic_previous' => '&&'
                    ],
                ],
            ],
            // List fields
            'action_' . $this->id . '_list_fields'   => [
                'label'                        => __('Field', 'mailoptin'),
                'type'                         => 'select',
                'options'                      => 'action_api_populate',
                'options_blank'                => __('Select...', 'mailoptin'),
                'options_action_id'            => $this->id,
                'options_list_id_meta_key'     => 'action_' . $this->id . '_integration',
                'options_list_sub_id_meta_key' => 'action_' . $this->id . '_list_id',
                'options_action_api_populate'  => 'list_fields'
            ],
            'action_' . $this->id . '_tag'           => [
                'label' => __('Tag', 'mailoptin'),
                'type'  => 'text'
            ],
            'action_' . $this->id . '_list_tag'      => [
                'label'                       => __('Tags', 'mailoptin'),
                'type'                        => 'select',
                'options'                     => 'action_api_populate',
                'options_blank'               => __('Select a tag', 'wp-fusion'),
                'options_action_id_meta_key'  => 'action_id',
                'options_list_id_meta_key'    => 'action_' . $this->id . '_integration',
                'options_action_api_populate' => 'list',
            ],
        ];

        if ( ! defined('MAILOPTIN_DETACH_LIBSODIUM')) {
            unset($config_meta_keys['action_' . $this->id . '_double_optin']);
            unset($config_meta_keys['action_' . $this->id . '_tags']);
            unset($config_meta_keys['action_' . $this->id . '_tag']);
            unset($config_meta_keys['action_' . $this->id . '_select_tags']);
            unset($config_meta_keys['action_' . $this->id . '_list_tag']);
        }

        return array_merge($meta_keys, $config_meta_keys);
    }

    // Build REST API endpoints
    public function rest_api_init()
    {
        // API routes - get_* (Use cache)
        register_rest_route(WS_FORM_RESTFUL_NAMESPACE, '/action/' . $this->id . '/lists/', [
            'methods'             => 'GET',
            'callback'            => [$this, 'api_get_integrations'],
            'permission_callback' => function () {
                return WS_Form_Common::can_user('create_form');
            }
        ]);

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/subs/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_get_lists'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]);

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_get_tags_list'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]
        );

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/subs/(?P<list_id>[a-zA-Z0-9-_]+)/fields/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_get_list_fields'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]
        );

        // API routes - fetch_* (Pull from API and update cache)
        register_rest_route(WS_FORM_RESTFUL_NAMESPACE, '/action/' . $this->id . '/lists/fetch/', [
            'methods'             => 'GET',
            'callback'            => [$this, 'api_fetch_integrations'],
            'permission_callback' => function () {
                return WS_Form_Common::can_user('create_form');
            }
        ]);

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/fetch/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_fetch_tags_list'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]);

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/subs/fetch/', [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_fetch_lists'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]);

        register_rest_route(WS_FORM_RESTFUL_NAMESPACE,
            '/action/' . $this->id . '/list/(?P<integration_id>[a-zA-Z0-9-_]+)/subs/(?P<list_id>[a-zA-Z0-9-_]+)/fields/fetch/',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'api_fetch_list_fields'],
                'permission_callback' => function () {
                    return WS_Form_Common::can_user('create_form');
                }
            ]);
    }

    // Get integrations
    public function get_integrations($fetch = false)
    {
        // Check to see if integrations are cached
        $integrations = WS_Form_Common::option_get('action_' . $this->id . '_integrations');

        // Retried if fetch is requested or lists are not cached
        if ($fetch || ($integrations === false)) {
            $integrations = [];

            // Load configuration
            self::load_config();

            $connections = ConnectionsRepository::get_connections();

            //escape webhook connection
            unset($connections['WebHookConnect']);

            foreach ($connections as $key => $label) {
                if ( ! $key) {
                    continue;
                }

                $integrations[] = ['id' => $key, 'label' => $label];
            }

            WS_Form_Common::option_set('action_' . $this->id . '_integrations', $integrations);
        }

        return $integrations;
    }

    // API endpoint - Integrations
    public function api_get_integrations()
    {
        $integrations = self::get_integrations();

        // Process response
        self::api_response($integrations);
    }

    // API endpoint - Integrations with fetch
    public function api_fetch_integrations()
    {
        $integrations = self::get_integrations(true);

        // Process response
        self::api_response($integrations);
    }

    // Get lists
    public function get_lists($fetch = false)
    {
        // Check to see if lists are cached
        $lists = WS_Form_Common::option_get('action_' . $this->id . '_' . $this->integration . '_lists');

        // Retried if fetch is requested or lists are not cached
        if ($fetch || ($lists === false)) {
            $lists = [];

            // Load configuration
            self::load_config();

            $this->api = ConnectionFactory::make($this->integration);
            try {
                $connectionList = $this->api->get_email_list();

                if ($connectionList && is_array($connectionList)) {
                    foreach ($connectionList as $key => $label) {
                        $lists[] = ['id' => (string)$key, 'label' => $label];
                    }
                }
            } catch (\Exception $e) {
            }

            WS_Form_Common::option_set('action_' . $this->id . '_' . $this->integration . '_lists', $lists);
        }

        return $lists;
    }

    // API endpoint - Lists
    public function api_get_lists($parameters)
    {
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get lists
        $lists = self::get_lists();

        // Process response
        self::api_response($lists);
    }

    // API endpoint - Lists with fetch
    public function api_fetch_lists($parameters)
    {
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get lists
        $lists = self::get_lists(true);

        // Process response
        self::api_response($lists);
    }

    public function get_tags_list($fetch = false)
    {
        if (in_array($this->integration, Init::select2_tag_connections())) {
            // Check to see if list is cached
            $list = WS_Form_Common::option_get('action_' . $this->id . '_' . $this->integration . '_tag_lists');

            // Retried if fetch is requested or list is not cached
            if ($fetch || ($list === false)) {
                $list = [];

                // Load configuration
                self::load_config();

                $this->api = ConnectionFactory::make($this->integration);
                try {
                    if (is_object($this->api) && method_exists($this->api, 'get_tags')) {

                        $tags = $this->api->get_tags();

                        if ( ! empty($tags)) {
                            foreach ($tags as $key => $label) {
                                $list[] = ['id' => $key, 'label' => $label];
                            }
                        }
                    }
                } catch (\Exception $e) {
                }

                WS_Form_Common::option_set('action_' . $this->id . '_' . $this->integration . '_tag_lists', $list);
            }

            return $list;
        }

        return [];
    }

    // API endpoint - Lists
    public function api_get_tags_list($parameters)
    {
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get lists
        $lists = self::get_tags_list();

        // Process response
        self::api_response($lists);
    }

    // API endpoint - Lists with fetch
    public function api_fetch_tags_list($parameters)
    {
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get lists
        $lists = self::get_tags_list(true);

        // Process response
        self::api_response($lists);
    }

    // Get list fields
    public function get_list_fields($fetch = false)
    {
        $list_fields = WS_Form_Common::option_get('action_' . $this->id . '_list_fields_' . $this->integration . '_' . $this->list_id);

        if ($fetch || ($list_fields === false)) {

            $list_fields = [
                [
                    'id'            => 'moEmail',
                    'label'         => esc_html__('Email', 'mailoptin'),
                    'label_field'   => 'email',
                    'type'          => 'email',
                    'default_value' => '',
                    'required'      => true,
                ],
                [
                    'id'    => 'moName',
                    'label' => esc_html__('Full Name', 'mailoptin'),
                    'type'  => 'text',
                ],
                [
                    'id'    => 'moFirstName',
                    'label' => esc_html__('First Name', 'mailoptin'),
                    'type'  => 'text',
                ],
                [
                    'id'    => 'moLastName',
                    'label' => esc_html__('Last Name', 'mailoptin'),
                    'type'  => 'text',
                ]
            ];

            // Load configuration
            self::load_config();

            if (in_array($this->integration, Init::no_name_mapping_connections())) {
                unset($list_fields[1]); // tag => moName
                unset($list_fields[2]); // tag => moFirstName
                unset($list_fields[3]); // tag => moLastName
            }

            if (defined('MAILOPTIN_DETACH_LIBSODIUM')) {

                try {

                    $api = ConnectionFactory::make($this->integration);

                    if (in_array($api::OPTIN_CUSTOM_FIELD_SUPPORT, $api::features_support())) {

                        $fields = $api->get_optin_fields($this->list_id);

                        if ( ! empty($fields)) {
                            foreach ($fields as $key => $value) {
                                $list_fields[] = [
                                    'id'    => $key,
                                    'label' => $value,
                                    'type'  => 'text',
                                ];
                            }
                        }
                    }

                } catch (\Exception $exception) {
                }
            }

            // Store to options
            WS_Form_Common::option_set('action_' . $this->id . '_list_fields_' . $this->integration . '_' . $this->list_id, $list_fields);
        }

        return $list_fields;
    }

    // API endpoint - List fields with fetch
    public function api_fetch_list_fields($parameters)
    {
        // Get integration
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get list
        $this->list_id = WS_Form_Common::get_query_var('list_id', false, $parameters);
        $list_fields   = self::get_list_fields(true);

        // Process response
        self::api_response($list_fields);
    }

    // API endpoint - List fields
    public function api_get_list_fields($parameters)
    {
        // Get integration
        $this->integration = WS_Form_Common::get_query_var('integration_id', false, $parameters);
        // Get list
        $this->list_id = WS_Form_Common::get_query_var('list_id', false, $parameters);
        $list_fields   = self::get_list_fields();

        // Process response
        self::api_response($list_fields);
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
