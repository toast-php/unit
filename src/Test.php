<?php

namespace Toast\Unit;

use ReflectionClass;
use ReflectionFunction;
use Throwable;
use Error;
use ErrorException;
use Closure;
use Generator;
use AssertionError;

/**
 * The main test class. Normally gets constructed internally, you don't have
 * to make your tests extend anything.
 */
class Test
{
    private $test = false;
    private $description;
    private $befores = [];
    private $afters = [];
    private $level;
    private $filter;
    private $file;
    public $matchesFilter = [];
    public $subMatchesFilter = [];

    /**
     * Constructor
     *
     * @param int $level The nesting level, for indenting the output.
     * @param string|null $filter
     */
    public function __construct(int $level = 0, string $filter = null)
    {
        $this->level = $level;
        $this->filter = $filter;
    }

    /**
     * Set the function to test.
     *
     * @param ReflectionFunction $function Reflected function representing this
     *  particular scenario.
     * @return void
     */
    public function setTestFunction(ReflectionFunction $function) : void
    {
        $this->file = preg_replace('@^'.getcwd().'@', '', $function->getFileName());
        if (isset($this->filter) && !preg_match("@{$this->filter}@i", $this->file)) {
            return;
        }
        $this->test = $function;
        $description = cleanDocComment($this->test);
        $description = preg_replace("@\s{1,}@m", ' ', $description);
        $this->description = $description;
    }

    public function loadFromFile(string $file) : bool
    {
        $fn = include $file;
        if (is_callable($fn)) {
            $this->setTestFunction(new ReflectionFunction($fn));
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
     * @return void
     */
    public function run(int &$passed, int &$failed, array &$messages) : void
    {
        if (!$this->test) {
            return;
        }
        $expected = [
            'result' => null,
            'thrown' => null,
            'out' => '',
        ];
        $this->out("<darkBlue>{$this->description}\n");
        $closure = $this->test->getClosure();
        $closure->bindTo($this);
        $result = $closure();
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
                $filter = null;
                $spawn = new Test($this->level + 1);
                $spawn->setTestFunction($test);
                $spawn->run($passed, $failed, $messages);
            } else {
                $comment = '  '.cleanDocComment($test);
                $this->out($comment);
                $e = null;
                $err = null;
                try {
                    if (!OUTPUT) {
                        ob_start();
                    }
                    $test->invoke($this);
                    $passed++;
                } catch (AssertionError $e) {
                    $err = sprintf(
                        '<gray>Assertion failed: <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s <gray> in test <darkGray>%s',
                        substr($e->getMessage(), 7, -1),
                        $this->getBasename($e->getFile()),
                        $e->getLine(),
                        $this->file
                    );
                    $failed++;
                } catch (Error $e) {
                    $trace = $e->getTrace();
                    foreach ($trace as $step) {
                        if (!isset($step['file']) || strpos($step['file'], '/vendor/')) {
                            continue;
                        }
                        $err = sprintf(
                            '<gray>Error <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s <gray> in test <darkGray>%s',
                            get_class($e),
                            $e->getMessage(),
                            $this->getBasename($step['file']),
                            $step['line'],
                            $this->file
                        );
                        $failed++;
                        break;
                    }
                } catch (Throwable $e) {
                    $trace = $e->getTrace();
                    foreach ($trace as $step) {
                        if (!isset($step['file']) || strpos($step['file'], '/vendor/')) {
                            continue;
                        }
                        $err = sprintf(
                            '<gray>Caught exception <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s <gray>in test <darkGray>%s',
                            get_class($e),
                            $e->getMessage(),
                            $this->getBasename($step['file']),
                            $step['line'],
                            $this->file
                        );
                        $failed++;
                        break;
                    }
                }
                if (!OUTPUT) {
                    $out = cleanOutput(ob_get_clean());
                } else {
                    $out = null;
                }
                if (!isset($e)) {
                    $this->isOk($comment, strlen($out) ? 'darkGreen' : 'green');
                } else {
                    if (!isset($err)) {
                        $err = sprintf(
                            '<gray>Caught exception <darkGray>%s <gray> with message <darkGray>%s <gray>in <darkGray>%s <gray>on line <darkGray>%s <gray>in test <darkGray>%s',
                            get_class($e),
                            $e->getMessage(),
                            $this->getBasename($e->getFile()),
                            $e->getLine(),
                            $this->file
                        );
                    }
                    $this->isError($comment);
                    $this->out("  <darkRed>[!] $err\n");
                    Log::log($err);
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
     * @return void
     */
    public function beforeEach(callable $fn) : void
    {
        $this->befores[] = $fn;
    }

    /**
     * Add an `afterEach` function for all tests in this group.
     *
     * @param callable $fn Any callable.
     * @return void
     */
    public function afterEach(callable $fn)
    {
        $this->afters[] = $fn;
    }

    /**
     * Simple output helper adding indents.
     *
     * @param string $string
     * @return void
     */
    private function out(string $string) : void
    {
        out(str_repeat('  ', $this->level).$string);
    }

    /**
     * Simple output helper for success.
     *
     * @param string $message
     * @param string $color Colour (defaults to green)
     * @return void
     */
    private function isOk(string $message, string $color = 'green') : void
    {
        $length = strlen(str_repeat('  ', $this->level).$message);
        out("\033[{$length}D\033[0m");
        $this->out("<$color>$message\n");
    }

    /**
     * Simple output helper for errors.
     *
     * @param string $message
     * @return void
     */
    private function isError(string $message) : void
    {
        $length = strlen(str_repeat('  ', $this->level).$message);
        out("\033[{$length}D\033[0m");
        $this->out("<red>$message\n");
    }

    /**
     * Get the relevant part of the filename.
     *
     * @param string $file Full path
     * @return string Shortened path
     */
    private function getBasename(string $file) : string
    {
        return preg_replace("@^".getcwd()."/@", '', $file);
    }
}

