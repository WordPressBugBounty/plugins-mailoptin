<?php

namespace MailOptin\GoogleSheetConnect;

use MailOptin\Core\Repositories\OptinCampaignsRepository as OCR;

class Subscription extends AbstractGoogleSheetConnect
{
    public $email;
    public $name;
    public $list_id;
    public $extras;
    /** @var Connect */
    public $connectInstance;

    public function __construct($email, $name, $list_id, $extras, $connectInstance)
    {
        $this->email           = $email;
        $this->name            = $name;
        $this->list_id         = $list_id;
        $this->extras          = $extras;
        $this->connectInstance = $connectInstance;

        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function subscribe()
    {
        try {

            $name_split = self::get_first_last_names($this->name);

            $sheet_name = apply_filters(
                'mo_connections_google_sheet_file_sheet_name',
                $this->get_integration_data('GoogleSheetConnect_file_sheets'),
                $this->list_id,
                $this->extras['optin_campaign_id'],
                $this,
            );

            if (empty($sheet_name)) {
                $sheets = $this->connectInstance->get_spreadsheet_sheets($this->list_id);

                if (is_array($sheets)) {
                    $sheet_name = array_shift($sheets);
                }
            }

            // get sheet header columns
            $headers = $this->connectInstance->get_sheet_header_columns($this->list_id, $sheet_name);

            $custom_field_mappings = $this->form_custom_field_mappings();

            $valueArray                  = [];
            $lastHeaderColumnAlphabetKey = 'A';

            foreach ($headers as $header) {

                // https://stackoverflow.com/questions/3567180/how-to-increment-letters-like-numbers-in-php
                $lastHeaderColumnAlphabetKey++;

                $data           = '';
                $valueExtrasKey = $custom_field_mappings[$header] ?? '';

                switch ($valueExtrasKey) {
                    case 'mo_core_first_name':
                        $data = $name_split[0];
                        break;
                    case 'mo_core_last_name':
                        $data = $name_split[1];
                        break;
                    case 'mo_core_email':
                        $data = $this->email;
                        break;
                    default:
                        if ( ! empty($valueExtrasKey) && ! empty($this->extras[$valueExtrasKey])) {
                            $data .= $this->extras[$valueExtrasKey];
                        }
                        break;
                }

                if (is_array($data) || is_object($data)) $data = wp_json_encode($data);

                $valueArray[] = $data;
            }

            $payload = apply_filters(
                'mo_connections_google_sheet_optin_payload',
                ['values' => [$valueArray]],
                $this
            );

            $this->gsheetInstance()->apiRequest(
                sprintf('%s/values/%s!A:%s:append?valueInputOption=USER_ENTERED', $this->list_id, $sheet_name, $lastHeaderColumnAlphabetKey),
                'POST',
                $payload,
                ['Content-Type' => 'application/json']
            );

            return parent::ajax_success();

        } catch (\Exception $e) {

            self::save_optin_error_log($e->getCode() . ': ' . $e->getMessage(), 'googlesheet', $this->extras['optin_campaign_id'], $this->extras['optin_campaign_type']);

            return parent::ajax_failure();
        }
    }
}