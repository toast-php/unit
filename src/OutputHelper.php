<?php

namespace Toast\Unit;

use Ansi;
use Reflector;

trait OutputHelper
{
    /**
     * Output $text to the specified $out, with added ANSI colours.
     */
    private function out(string $text, $out = STDOUT) : void
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

    private function cleanOutput(string $string) : string
    {
        return preg_replace('@\\033\[[\d;]*m@m', '', rtrim($string));
    }

    private function cleanDocComment(Reflector $reflection, bool $strip_annotations = true) : string
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
}

