<?php

/*
Plugin Name: MailOptin - Lite
Plugin URI: https://mailoptin.io
Description: Best lead generation, email automation & newsletter plugin.
Version: 1.2.75.2
Author: MailOptin Popup Builder Team
Contributors: collizo4sky
Author URI: https://mailoptin.io
Text Domain: mailoptin
Domain Path: /languages
License: GPL2
*/

require __DIR__ . '/vendor/autoload.php';

define('MAILOPTIN_SYSTEM_FILE_PATH', __FILE__);
define('MAILOPTIN_VERSION_NUMBER', '1.2.75.2');

MailOptin\Core\Core::init();
MailOptin\Connections\Init::init();