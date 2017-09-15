# Toast\Unit
Toast is a super-simple testing framework for PHP, inspired by Javascript
testing frameworks like Karma, Jasmine etc.

Toast was born out of frustration with existing testing frameworks which (in our
view) are needlessly bulky, complex and difficult to setup. Some are also
excruciatingly slow (looking at you, PHPUnit).

## Features
- *Very* fast
- Assertions using native PHP `assert`
- Feature descriptions using DocComments
- Grouping of related tests

## Installation
```sh
$ composer require --dev toast/unit
```

Create a `Toast.json` config file in the root of your project. It should contain
at least a `"tests"` key pointing to the directory where you've placed your
tests. Tests may be placed in (sub)directories; Toast will recurse.

Optionally the config can also contain a `"bootstrap"` key with an array of
files to include prior to running. These can contain your project's setup (e.g.
dependency injection logic).

Turn on assertions and configure them to throw `AssertionError` on failure.
See [this section in the
manual](http://php.net/manual/en/function.assert.php); both values should be
set to `1`.

## Usage
```sh
$ vendor/bin/toast
```

That's it :) You may optionally speficy a `--filter=` parameter containing a
regular expression. In that case only tests with matching file names will be
run. Filters are case-insensitive and `"@"` is used as a delimiter.

## Writing tests
This couldn't be simpler! Each test(group) is a _callable_. Toast assumes any
callable returning a `Generator` is a group; other callables are the actual
tests. For example:

```php
<?php

/** Description of this group */
return function () : Generator {
    /** Test if true == true */
    yield function () {
        assert(true);
    };
};
```

You can have as many assertions per test as you like, but typically it is best
practice to limit yourself to as little as possible (preferably one) and group
related tests using a generator:

```php
<?php

return function () : Generator {
    $obj = new My\Thing\Under\Test;
    /** Test method foo */
    yield function () use ($obj) {
        assert($obj->foo());
    };
    /** Test method bar */
    yield function () use ($obj) {
        assert($obj->bar());
    };
};
```

Nesting can go as deep as makes sense for your project.

## Setup
The test callables actually get called with a single argument: the instance of
`Toast\Unit\Test` being used. This object has a `beforeEach` method accepting
a callable to be called before each test _in that group_ (tests in a nested
group do not inherit them). Multiple calls to `beforeEach` can be made.

```php
<?php

return function ($test) : Generator {
    $test->beforeEach(function () {
        echo "1\n";
    });
    yield function ($test) : Generator {
        $test->beforeEach(function () {
            echo "2\n";
        });
        yield function () { assert(true); };
    };
    yield function () {
        assert(true);
    };
};
```

In the above example, `"1"` will get output twice since the `beforeEach` is
valid for the two top-level `yield`s. `"2"` will be ouptut once since it only
applies to the nested `yield`, which in turn knows nothing about its parent.

You can use `beforeEach` to e.g. load a database fixture. How you do that is up
to you.

## Teardown
Similarly, if you need to teardown there is an `afterEach` method that works the
same way.

> Cap'n obvious here, but both `beforeEach` as well as `afterEach` will only
> apply to tests getting `yield`ed _after_ the respective calls. This is
> because PHP halts execution internally inside a generator. For complex tests
> it _might_ make sense to add before/after callables mid-group, but usually
> you should just place them at the beginning. If you find yourself needing to
> add them mid-group, it's usually a sign you should break up your test group
> into smaller units.

## Testing other things than simple assertions
Toast assumes a succesfull test is a passed assertion. But what it you need to
test if something throws an exception? Simple:

```php
<?php

//...
yield function () {
    $foo = new Foo;
    $e = null;
    try {
        $foo->bar();
    } catch (FooException $e) {
    }
    assert($e instanceof FooException);
};
```

Toast also assumes a test does not yield output. So to test if a function
actually _does_ yield output, use output buffering:

```php
<?php

//....
yield function () {
    ob_start();
    thisFunctionPrintsSomething();
    assert(ob_end_clean() == "Hello world!");
};
```

You can optionally specify the `-o` parameter when invoking Toast to turn this
feature off. This eases development since you can more easily `var_dump` stuff
until you get a working test.

## Detecting a Toast run
Toast sets an environment variable `TOAST` your code can check for, e.g. to know
which database you want to use (development or test):

```php
<?php

if (getenv("TOAST")) {
    $db = new PDO('mysql:test');
} else {
    $db = new PDO('mysql:dev');
}
```

There is also a (unique) `TOAST_CLIENT` set you can use to identify a particular
run of Toast. This would be useful if e.g. your tested feature stores something
somewhere you would need to be able to identify.

