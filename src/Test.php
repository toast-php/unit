<?php

namespace Toast\Unit;

use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionException;
use zpt\anno\Annotations;
use Throwable;
use Error;
use ErrorException;
use Closure;
use Generator;
use SplFileInfo;
use AssertionError;

/**
 * The main test class. Normally gets constructed internally, you don't have
 * to make your tests extend anything.
 */
class Test
{
    private $test;
    private $description;
    private $befores = [];
    private $afters = [];
    private $level;
    private $filter;

    /**
     * Constructor
     *
     * @param int $level The nesting level, for indenting the output.
     */
    public function __construct(int $level = 0)
    {
        $this->level = $level;
        $this->filter = getenv("TOAST_FILTER");
    }

    /**
     * Set the function to test.
     *
     * @param ReflectionFunctionAbstract $function Reflected function
     *  representing this particular scenario.
     */
    public function setTestFunction(ReflectionFunctionAbstract $function)
    {
        $this->test = $function;
        $description = cleanDocComment($this->test);
        $description = preg_replace("@\s{1,}@m", ' ', $description);
        $this->description = $description;
    }

    public function loadFromFile(string $file) : bool
    {
        $fn = include $file;
        if (is_callable($fn)) {
            $reflection = new ReflectionFunction($fn);
            $this->setTestFunction($reflection);
            return true;
        } else {
            $this->out("<darkRed>No tests found in $file, skipping...\n");
            return false;
        }
    }

    /**
     * Runs this scenario. Normally called internally by the Toast executable.
     *
     * @param int &$passed Global number of tests passed so far.
     * @param int &$failed Global number of tests failed so far.
     * @param array &$messages Array of messages so far (for verbose mode).
     * @return array An array of the arguments used when testing.
     */
    public function run(&$passed, &$failed, array &$messages)
    {
        if ($this->test->getDocComment()) {
            $description = cleanDocComment($this->test);
        } else {
            $description = null;
        }
        $expected = [
            'result' => null,
            'thrown' => null,
            'out' => '',
        ];
        $result = $this->test->invoke($this);
        $this->matchesFilter = [$description];
        foreach ($result as $test) {
            $test = new ReflectionFunction($test);
            if (!($test->hasReturnType()
                and $returnType = $test->getReturnType()->__toString()
                and $returnType == 'Generator'
            )) {
                $this->matchesFilter[] = cleanDocComment($test);
            }
        }
        if ($this->filter) {
            $match = false;
            foreach ($this->matchesFilter as $filter) {
                if (preg_match("@{$this->filter}@i", $filter)) {
                    $match = true;
                }
            }
        } else {
            $match = true;
        }
        if ($match) {
            $this->out("<darkBlue>$description\n");
        } else {
            return;
        }
        $result = $this->test->invoke($this);
        foreach ($result as $test) {
            $test = new ReflectionFunction($test);
            if ($this->befores) {
                foreach ($this->befores as $step) {
                    call_user_func($step);
                }
            }
            if ($test->hasReturnType()
                and $returnType = $test->getReturnType()->__toString()
                and $returnType == 'Generator'
            ) {
                $spawn = new Test($this->level + 1);
                $spawn->setTestFunction($test);
                $spawn->run($passed, $failed, $messages);
            } else {
                $comment = '  '.cleanDocComment($test);
                if ($this->filter && !preg_match("@{$this->filter}@i", $comment)) {
                    continue;
                }
                $this->out($comment);
                $e = null;
                $err = null;
                try {
                    ob_start();
                    $reflected->invoke($this);
                    $passed++;
                } catch (AssertionError $e) {
                    $err = sprintf(
                        '<darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s',
                        substr($e->getMessage(), 7, -1),
                        basename($e->getFile()),
                        $e->getLine()
                    );
                    $failed++;
                } catch (Error $e) {
                    $err = sprintf(
                        '<gray>Error <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s',
                        get_class($e),
                        $e->getMessage(),
                        basename($e->getFile()),
                        $e->getLine()
                    );
                    $failed++;
                } catch (Throwable $e) {
                    $err = sprintf(
                        '<gray>Caught exception <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s',
                        get_class($e),
                        $e->getMessage(),
                        basename($e->getFile()),
                        $e->getLine()
                    );
                    $failed++;
                }
                $out = cleanOutput(ob_get_clean());
                if (!isset($e)) {
                    $this->isOk($comment, strlen($out) ? 'darkGreen' : 'green');
                } else {
                    $this->isError($comment);
                    $this->out("  <darkRed>[!] $err\n");
                }
            }
            if ($this->afters) {
                foreach ($this->afters as $step) {
                    call_user_func($step);
                }
            }
        }
        $this->out("\n");
    }

    /**
     * Add a `beforeEach` function for all tests in this group.
     *
     * @param callable $fn Any callable.
     */
    public function beforeEach(callable $fn)
    {
        $this->befores[] = $fn;
    }

    /**
     * Add an `afterEach` function for all tests in this group.
     *
     * @param callable $fn Any callable.
     */
    public function afterEach(callable $fn)
    {
        $this->afters[] = $fn;
    }

    /**
     * Simple output helper adding indents.
     *
     * @param string $string
     */
    private function out(string $string)
    {
        out(str_repeat('  ', $this->level).$string);
    }

    /**
     * Simple output helper for success.
     *
     * @param string $message
     * @param string $color Colour (defaults to green)
     */
    private function isOk(string $message, string $color = 'green')
    {
        $length = strlen(str_repeat('  ', $this->level).$message);
        out("\033[{$length}D\033[0m");
        $this->out("<$color>$message\n");
    }

    /**
     * Simple output helper for errors.
     *
     * @param string $message
     */
    private function isError(string $message)
    {
        $length = strlen(str_repeat('  ', $this->level).$message);
        out("\033[{$length}D\033[0m");
        $this->out("<red>$message\n");
    }
}

