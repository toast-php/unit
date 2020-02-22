<?php

use Toast\Demo;

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

