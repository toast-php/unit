<?php

namespace Toast\Tests;

use Toast\Demo;
use stdClass;
use ReflectionFunction;
use Generator;

/**
 * Basic test running
 *
class Basic
{
    /**
     * Test::run should successfully run a test, pipe the result and catch
     * trimmed output
    public function testClass(Toast\Test $test)
    {
        $target = new stdClass;
        $target->test = true;
        $reflection = new ReflectionFunction(
            /**
             * Test should be true
             /
            function (stdClass &$test = null) use ($target) {
                $test = $target;
                yield assert($test->test);
            }
        );
        $test->__gentryConstruct($test, $reflection);
        echo "       ";
        $passed = 0;
        $failed = 0;
        $messages = [];
        yield assert(is_array($test->run($passed, $failed, $messages)));
    }
     *

    /**
     * 'test' should return true {?}, and 'foo' should contain "bar"
     *
    public function testMultiple()
    {
        $test = new Demo\Test;
        yield assert($test->test());
        yield assert($test->foo == 'bar');
    }
    
    /**
     * aStaticMethod should be tested statically
     *
    public function statically()
    {
        yield assert(Demo\Test::aStaticMethod());
    }
}
*/

/** Basic test running */
return function () : Generator {
    $test = new Demo\Test;
    yield /** 'test' should return true */ function () use ($test) {
        assert($test->test());
    };
    /** 'foo' should contain "bar" */
    yield function () use ($test) {
        assert($test->foo == 'bar');
    };
};

