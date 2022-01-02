<?php

namespace Toast\Unit;

use Toast\Cache;
use Ansi;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionFunction;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use Closure;
use zpt\anno\Annotations;
use Monomelodies\Kingconf;
use Exception;
use ErrorException;
use Monolyth\Cliff;

class Command extends Cliff\Command
{
    use OutputHelper;

    const TOAST_VERSION = '2.1.7';

    /** @var bool */
    public $output = false;

    /** @var bool */
    public $verbose = false;

    /** @var string */
    public $filter;

    public function __invoke() : void
    {
        $start = microtime(true);

        if (!ini_get('date.timezone')) {
            ini_set('date.timezone', 'UTC');
        }

        error_reporting(E_ALL);
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if ($errno == E_USER_DEPRECATED) {
                return;
            }
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        // Indicates we're running as Toast, hence environment should deal with tests
        // and associated fixtures instead of a "normal" database.
        putenv("TOAST=1");

        // A pseudo-random client id for this run of tests. Used mainly to associate a
        // cache pool with a single client in a cross-script manner (e.g. when running
        // acceptance tests). It's also passed as the default session id to the Browser
        // headless client (you can override it if your application uses specific checks
        // on session id validity).
        $client = substr(md5(microtime(true)), 0, 6);
        putenv("TOAST_CLIENT=$client");

        $this->out("\n<magenta>Toast ".self::TOAST_VERSION." by Marijn Ophorst\n\n");

        $config = 'Toast.json';
        $verbose = false;
        $output = false;

        $output = $this->output;
        $verbose = $this->verbose;
        if (isset($this->filter)) {
            $toastFilter = $this->filter;
        }
        define('Toast\Unit\VERBOSE', $verbose);
        define('Toast\Unit\OUTPUT', $output);
        try {
            $config = (object)(array)(new Kingconf\Config($config));
        } catch (Kingconf\Exception $e) {
            $this->out("<red>Error: <reset> Config file $config not found or invalid.\n", STDERR);
            die(1);
        }
        if (isset($config->bootstrap)) {
            $bootstrap = is_array($config->bootstrap) ? $config->bootstrap : [$config->bootstrap];
            $this->out("<gray>Bootstrapping...\n");
            foreach ($bootstrap as $file) {
                require $file;
            }
        }

        $passed = 0;
        $failed = 0;
        $messages = [];
        $filesystemHelper = new FileSystem;
        foreach ($filesystemHelper->findTests($config->tests) as $file) {
            $test = new Test(0, $toastFilter ?? null);
            if ($test->loadFromFile($file)) {
                $test->run($passed, $failed, $messages);
            }
        }

        $this->out("\n");

        if ($failed) {
            foreach (Log::get() as $msg) {
                $this->out("$msg\n\n");
            }
        }
        if ($passed) {
            $this->out(sprintf(
                "<green>%d test%s passed.\n",
                $passed,
                $passed == 1 ? '' : 's'
            ));
        }
        if ($failed) {
            $this->out(sprintf(
                "<red>%d test%s failed!\n\n",
                $failed,
                $failed == 1 ? '' : 's'
            ), STDERR);
        }

        try {
            @unlink(sys_get_temp_dir().'/'.getenv("TOAST_CLIENT").'.cache');
        } catch (ErrorException $e) {
        }
        $this->out("\n");
        $this->out(sprintf(
            "\n<magenta>Took %0.2f seconds, memory usage %4.2fMb.\n\n",
            microtime(true) - $start,
            memory_get_peak_usage(true) / 1048576
        ));
        exit($failed);
    }
}

