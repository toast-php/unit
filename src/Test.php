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

declare(ticks=1);

/**
 * The main test class. Normally gets constructed internally, you don't have
 * to make your tests extend anything.
 */
class Test
{
    use OutputHelper {
        out as unformattedOut;
    }

    /** @var string[] */
    public $matchesFilter = [];

    /** @var string[] */
    public $subMatchesFilter = [];

    /** @var bool */
    private $test = false;

    /** @var string */
    private $description;

    /** @var callable[] */
    private $befores = [];

    /** @var callable[] */
    private $afters = [];

    /** @var int */
    private $level;

    /** @var string|null */
    private $filter;

    /** @var string */
    private $file;

    /** @var Toast\Unit\Test|null */
    private $spawn;

    /**
     * Constructor
     *
     * @param int $level The nesting level, for indenting the output.
     * @param string|null $filter
     * @param array $befores Inherited beforeEach statements. Optional.
     * @param array $afters Inherited afterEach statements. Optional.
     */
    public function __construct(int $level = 0, string $filter = null, array $befores = [], array $afters = [])
    {
        $this->level = $level;
        $this->filter = $filter;
        $this->befores =& $befores;
        $this->afters =& $afters;
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
        $description = $this->cleanDocComment($this->test);
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
        $didSpawn = false;
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
        $running = false;
        $tickpos = 0;
        $tock = function () use (&$running, &$tickpos) {
            static $states = ['|', '/', '-', '\\'];
            if ($running) {
                $this->unformattedOut($states[$tickpos]);
                $tickpos++;
                if ($tickpos == 4) {
                    $tickpos = 0;
                }
                $this->backspace(1);
            }
        };
        foreach ($result as $test) {
            $test = new ReflectionFunction($test);
            if ($test->hasReturnType()
                and $returnType = ((float)phpversion() >= 7.4 ? $test->getReturnType()->getName() : $test->getReturnType()->__toString())
                and $returnType == 'Generator'
            ) {
                $didSpawn = true;
                $this->spawn = new Test($this->level + 1, null, $this->befores, $this->afters);
                $this->spawn->setTestFunction($test);
                $this->spawn->run($passed, $failed, $messages);
            } else {
                $tickpos = 0;
                register_tick_function($tock);
                if ($this->befores) {
                    foreach ($this->befores as $step) {
                        call_user_func($step);
                    }
                }
                $comment = trim($this->cleanDocComment($test));
                $this->out("  | $comment");
                $this->backspace(strlen($comment) + 2);
                $running = true;
                $e = null;
                $err = null;
                try {
                    if (!OUTPUT) {
                        ob_start();
                    }
                    $closure = $test->getClosure();
                    $closure->bindTo($this);
                    $closure();
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
                    $out = $this->cleanOutput(ob_get_clean());
                } else {
                    $out = null;
                }
                $running = false;
                unregister_tick_function($tock);
                if (!isset($e)) {
                    $this->isOk(trim($comment), strlen($out) ? 'darkGreen' : 'green');
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
                    $this->isError(trim($comment));
                    $this->out("  <darkRed>[!] $err\n");
                    Log::log($err);
                }
                if ($this->afters) {
                    foreach ($this->afters as $step) {
                        call_user_func($step);
                    }
                }
            }
        }
        if (!$didSpawn) {
            $this->out("\n");
        }
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
        if (isset($this->spawn)) {
            $this->spawn->beforeEach($fn);
        }
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
        if (isset($this->spawn)) {
            $this->spawn->beforeEach($fn);
        }
    }

    /**
     * Simple output helper adding indents.
     *
     * @param string $string
     * @return void
     */
    private function out(string $string) : void
    {
        $this->unformattedOut(str_repeat('  ', $this->level).$string);
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
        $this->backspace(strlen(str_repeat('  ', $this->level)) + 2);
        $this->out("  <$color>\xE2\x9C\x94 $message\n");
    }

    /**
     * Simple output helper for errors.
     *
     * @param string $message
     * @return void
     */
    private function isError(string $message) : void
    {
        $this->backspace(strlen(str_repeat('  ', $this->level)) + 2);
        $this->out("  <red>\xE2\x9D\x8C $message\n");
    }
    
    /**
     * Go back n positions.
     *
     * @param int $length
     * @return void
     */
    private function backspace(int $length) : void
    {
        $this->unformattedOut("\033[{$length}D\033[0m");
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

