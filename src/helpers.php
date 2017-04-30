<?php

namespace Toast\Runner;

use Ansi;
use Reflector;

/**
 * Output $text to the specified $out, with added ANSI colours.
 */
function out($text, $out = STDOUT)
{
    static $indent = '';
    static $output;
    if (!isset($output)) {
        $output = function ($text) use ($out) {
            fwrite($out, $text);
        };
    }

    $text = Ansi::tagsToColors($text);
    $output($text);
}

function cleanOutput($string)
{
    return preg_replace('@\\033\[[\d;]*m@m', '', rtrim($string));
}

function cleanDocComment(Reflector $reflection, $strip_annotations = true)
{
    $doccomment = $reflection->getDocComment();
    $doccomment = preg_replace("@^/\*\*@", '', $doccomment);
    $doccomment = preg_replace("@\*/$@m", '', $doccomment);
    if ($strip_annotations) {
        $doccomment = preg_replace("/^\s*\*\s*@\w+.*?$/m", '', $doccomment);
    }
    $doccomment = preg_replace("@^\s*\*\s*@m", '', $doccomment);
    $doccomment = str_replace("\n", ' ', $doccomment);
    $doccomment = trim(preg_replace("@\s{2,}@", ' ', $doccomment));
    return $doccomment;
}

function getNormalisedType($type)
{
    if (is_object($type)) {
        return get_class($type);
    }
    $type = gettype($type);
    switch ($type) {
        case 'integer': return 'int';
        case 'boolean': return 'bool';
        default: return $type;
    }
}

