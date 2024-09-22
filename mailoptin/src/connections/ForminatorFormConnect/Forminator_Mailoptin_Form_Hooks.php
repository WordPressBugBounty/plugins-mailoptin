<?php

use MailOptin\Connections\Init;
use MailOptin\Core\Connections\AbstractConnect;
use MailOptin\Core\OptinForms\ConversionDataBuilder;
use MailOptin\Core\AjaxHandler;

use function MailOptin\Core\moVar;

class Forminator_Mailoptin_Form_Hooks extends Forminator_Integration_Form_Hooks
{
    protected function custom_entry_fields(array $submitted_data, array $current_entry_fields): array
    {
        $module_id            = $this->module_id;
        $settings_instance    = $this->settings_instance;
        $submitted_data       = $this->prepare_submitted_data($submitted_data, $module_id, $current_entry_fields);
        $addon_setting_values = $settings_instance->get_settings_values();

        $settings_values = $this->addon->get_settings_values();
        $identifier      = $settings_values['identifier'] ?? '';
        $entry_name      = 'status';

        $postdata = $this->get_field_values_for_mailoptin($current_entry_fields, $addon_setting_values);

        $name       = moVar($postdata, 'moName');
        $first_name = moVar($postdata, 'moFirstName');
        $last_name  = moVar($postdata, 'moLastName');

        $connected_service = $addon_setting_values['connected_email_providers'];

        $double_optin = false;
        if (in_array($connected_service, Init::double_optin_support_connections(true))) {
            $double_optin = isset($addon_setting_values[$connected_service]['double_optin']) && $addon_setting_values[$connected_service]['double_optin'] === "true";
        }

        $optin_data = new ConversionDataBuilder();

        // since it's non mailoptin form, set it to zero.
        $optin_data->optin_campaign_id         = 0;
        $optin_data->payload                   = $postdata;
        $optin_data->name                      = Init::return_name($name, $first_name, $last_name);
        $optin_data->email                     = $postdata['moEmail'];
        $optin_data->optin_campaign_type       = esc_html__('Forminator Form', 'mailoptin');
        $optin_data->connection_service        = $connected_service;
        $optin_data->connection_email_list     = $addon_setting_values[$connected_service]['lists'];
        $optin_data->user_agent                = esc_html($_SERVER['HTTP_USER_AGENT']);
        $optin_data->is_timestamp_check_active = false;
        $optin_data->is_double_optin           = $double_optin;

        if ( ! empty($submitted_data['current_url'])) {
            $optin_data->conversion_page = esc_url_raw($submitted_data['current_url']);
        }

        //map tags
        if ( ! empty($addon_setting_values[$connected_service]['tags'])) {
            $optin_data->form_tags = $addon_setting_values[$connected_service]['tags'];
        }

        //custom field mapping
        foreach ($postdata as $key => $value) {
            if (in_array($key, ['moName', 'moEmail', 'moFirstName', 'moLastName'])) continue;

            $field_value = moVar($postdata, $key);

            if ( ! empty($field_value)) {
                $optin_data->form_custom_field_mappings[$key] = $key;
            }
        }

        $response = AjaxHandler::do_optin_conversion($optin_data);

        if (AbstractConnect::is_ajax_success($response)) {

            $entry_fields = [
                [
                    'value' => [
                        'is_sent'     => true,
                        'description' => esc_html__('Successfully subscribe lead via MailOptin', 'mailoptin')
                    ]
                ]
            ];
        } else {

            $entry_fields = [
                [
                    'value' => [
                        'is_sent'     => false,
                        'description' => moVar($response, 'message', '')
                    ]
                ]
            ];
        }

        $entry_fields[0]['name']                     = $entry_name;
        $entry_fields[0]['value']['connection_name'] = $identifier;

        return $entry_fields;
    }

    public function get_field_values_for_mailoptin($entry, $settings)
    {
        $vars = [];

        foreach ($settings['fields_map'] as $field_tag => $field_id) {
            if ( ! empty($field_id)) {
                $vars[$field_tag] = $this->get_entry_or_post_value($entry, $field_id);
            }
        }

        return $vars;
    }

    public function get_entry_or_post_value($entry, $field_id)
    {
        foreach ($entry as $value) {
            if (isset($value['name']) && $value['name'] == $field_id) {
                return $value['value'];
            }
        }

        return '';
    }
}