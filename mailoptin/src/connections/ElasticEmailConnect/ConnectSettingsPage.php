<?php

namespace MailOptin\ElasticEmailConnect;

use MailOptin\Core\Connections\AbstractConnect;

class ConnectSettingsPage {
	public function __construct()
	{
		add_filter('mailoptin_connections_settings_page', array($this, 'connection_settings'));

		add_action('wp_cspa_settings_after_title', array($this, 'output_error_log_link'), 10, 2);
	}

	public function connection_settings($arg)
	{
		$connected = AbstractElasticEmailConnect::is_connected(true);
		if (true === $connected) {
			$status = sprintf('<span style="color:#008000">(%s)</span>', __('Connected', 'mailoptin'));
		} else {
			$msg = '';
			if (is_string($connected)) {
				$msg = esc_html(" &mdash; $connected");
			}
			$status = sprintf("<span style='color:#FF0000'>(%s$msg) </span>", __('Not Connected', 'mailoptin'));
		}

		$settingsArg[] = [
			'section_title_without_status' => __('Elastic Email', 'mailoptin'),
			'section_title'                => __('Elastic Email Connection', 'mailoptin') . " $status",
			'type'                         => AbstractConnect::EMAIL_MARKETING_TYPE,
			'elasticemail_api_key'             => [
				'type'          => 'text',
				'obfuscate_val' => true,
				'label'         => __('Enter API Key', 'mailoptin'),
				'description'   => sprintf(
					__('Login to your %1$sElastic Email account%3$s and visit the %2$sAPI keys%3$s page to get an API Key.', 'mailoptin'),
					'<a target="_blank" href="https://app.elasticemail.com/marketing/login">',
					'<a target="_blank" href="https://app.elasticemail.com/marketing/settings/new/manage-api">',
					'</a>'
				),
			]
		];

		return array_merge($arg, $settingsArg);
	}

	public function output_error_log_link($option, $args)
	{
		if (MAILOPTIN_CONNECTIONS_DB_OPTION_NAME !== $option || ! isset($args['elasticemail_api_key'])) {
			return;
		}

		//Output error log link if  there is one
		echo AbstractConnect::get_optin_error_log_link('elasticemail');
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
