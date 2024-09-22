<?php

namespace MailOptin\ForminatorFormConnect;

class Connect
{
    public function __construct()
    {
        add_action('forminator_addons_loaded', [$this, 'is_forminator_form_installed']);
    }

    public function is_forminator_form_installed()
    {
        if ( ! class_exists('\Forminator_Integration_Loader')) return;

        if ( ! class_exists('Forminator_Mailoptin_Form_Hooks')) {
            require dirname(__FILE__) . '/Forminator_Mailoptin_Form_Hooks.php';
        }

        if ( ! class_exists('Forminator_Mailoptin_Quiz_Hooks')) {
            require dirname(__FILE__) . '/Forminator_Mailoptin_Quiz_Hooks.php';
        }

        $instance = \Forminator_Integration_Loader::get_instance();

        $instance->register(FFMailOptin::get_instance());

        if ( ! $instance->addon_is_active('mailoptin')) {
            $instance->activate_addon('mailoptin');
        }
    }

    /**
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