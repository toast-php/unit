<?php

namespace Toast\Unit;

abstract class Log
{
    private static $messages = [];

    /**
     * Log a message.
     *
     * @param string $msg
     * @return void
     */
    public static function log(string $msg) : void
    {
        self::$messages[] = $msg;
    }

    /**
     * Get all messages so far.
     *
     * @return array
     */
    public static function get() : array
    {
        return self::$messages;
    }
}

