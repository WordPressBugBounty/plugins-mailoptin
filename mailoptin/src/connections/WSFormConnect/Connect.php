<?php

namespace MailOptin\WSFormConnect;

class Connect
{
    public function __construct()
    {
        add_action('wsf_loaded', function () {
            WSFMailOptin::get_instance();
        });
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
