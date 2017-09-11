<?php

namespace Toast\Unit;

abstract class Log
{
    private static $messages = [];

    public static function log($msg)
    {
        self::$messages[] = $msg;
    }

    public static function get()
    {
        return self::$messages;
    }
}

