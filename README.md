Structured Field Values for PHP
=======================================

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://twitter.com/nyamsprod)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/http-structured-fields/workflows/build/badge.svg)](https://github.com/bakame-php/http-structured-fields/actions?query=workflow%3A%22build%22)

The package provides pragmatic classes to manage [HTTP Structured Fields][1] in PHP.

You will be able to

- parse, serialize HTTP Structured Fields
- create, update HTTP Structured Fields from different type and sources;
- infer fields and data from HTTP Structured Fields;

```php
use Bakame\Http\StructuredField\Dictionary;

$dictionary = Dictionary::fromField("a=?0, b, c; foo=bar");
count($dictionary); // returns 3 members
$dictionary->getByKey('c')->canonical(); // returns "c; foo=bar"
$dictionary->getByIndex(2)->parameters()->getByKey('foo')->value(); // returns "bar"
$dictionary->getByIndex(0)->value(); // returns false
```

System Requirements
-------

- You require **PHP >= 8.1** but the latest stable version of PHP is recommended

Installation
------------

Using composer:

```
composer require bakame/http-structured-fields
```

Documentation
---

## Parsing Structured Fields

There are three top-level types that an HTTP field can be defined as:

- Lists,
- Dictionaries, 
- and Items.

Depending on the field to parse the package provides a specific entry point via a common named constructor `:fromField`.

```php
use Bakame\Http\StructuredField\Dictionary;
use Bakame\Http\StructuredField\Item;
use Bakame\Http\StructuredField\OrderedList;

$dictionary = Dictionary::fromField("a=?0,   b,   c; foo=bar");
echo $dictionary->canonical(); // "a=?0, b, c;foo=bar"

$list = OrderedList::fromField("(\"foo\"; a=1;b=2);lvl=5, (\"bar\" \"baz\");lvl=1");
echo $list->canonical(); // "("foo";a=1;b=2);lvl=5, ("bar" "baz");lvl=1"

$item = Item::fromField("\"foo\";a=1;b=2");
echo $item->canonical(); // "foo";a=1;b=2
```

The `canonical()` method exposed by all the items type returns the string representation suited for HTTP headers.

## Structured Data Types

### Items

Accessing `Item` properties is done via two methods:

- `Item::value()` which returns the field underlying value
- `Item::parameters()` which returns the field associated parameters as a distinct `Parameters` object

```php
use Bakame\Http\StructuredField\Item;

$item = Item::fromField("\"foo\";a=1;b=2");
$item->value(); //returns "foo"
$item->parameters()->getByKey("a")->value(); //returns 1
```

#### Item value

The returned value of `Item::value` depends on its type. They are defined
in the RFC and this package translate them to PHP native type when possible 
and allow initiating the `Item` object based on the then. 

The information is represented in the table below:

| HTTP DataType definition | Package Type representation              | Named constructor        |
|--------------------------|------------------------------------------|--------------------------|
| Integer                  | int                                      | `Item::fromInteger`      |
| Decimal                  | float                                    | `Item::fromDecimal`      |
| String                   | string                                   | `Item::fromString`       |
| Boolean                  | bool                                     | `Item::fromBoolean`      |
| Token                    | Bakame\Http\StructuredField\Token        | `Item::fromToken`        |
| Byte Sequence            | Bakame\Http\StructuredField\ByteSequence | `Item::fromByteSequence` |

Two additional classes `Token` and `ByteSequence` are used to represent non-native types.

#### Item parameters

As explain in the RFC, `Parameters` are containers of `Item` instances. It can be associated to other structure **BUT** 
the items it contains can not themselves contain `Parameters` instance. More on parameters public API will be cover
in subsequent paragraphs.

### Containers

Apart from the `Item`, the RFC defines different items containers with different requirements. As
such `Parameters`, `Dictionary`, `InnerList` and `OrderedList` expose the same basic public API.

```php
namespace Bakame\Http\StructuredField;

interface StructuredFieldContainer extends \Countable, \IteratorAggregate, StructuredField
{
    public function isEmpty(): bool;
    public function getByIndex(int $index): Item|InnerList|null;
    public function hasIndex(int $index): bool
    public function getByKey(string $key): Item|InnerList|null;
    public function hasKey(string $key): bool
}
```

This means that at any given time it is possible to know:

- If the container is empty via the `isEmpty` method;
- The number of elements contained in the container via the `count` method;
- get any element by its string `key` or by its position integer `index` via `getByIndex` and `getByKey` methods;
- if an element is attached to the container using `index` or  `key` via `hasIndex` and `hasKey` methods;
- and to iterate over each contained members via the `IteratorAggregate` interface;

```php
use Bakame\Http\StructuredField\Item;
use Bakame\Http\StructuredField\Parameters;

$parameters = new Parameters();
$parameters->set('a', Item::fromInteger(1));
$parameters->set('b', Item::fromInteger(2));
count($parameters); // return 2
$parameters->getByKey('b'); // return 2
$parameters->getByIndex(1); // return 2
$parameters->hasKey(42);     // return false because the key does not exist.
$parameters->canonical();    // return ";a=1;b=2"
```

#### Ordered Maps

The `Parameters` and the `Dictionary` classes allow associating a string key to its members as such they expose

- the `set` method expect a key and a structured type;
- the `unset` method expect a list of keys to remove it and its associated type;

```php
use Bakame\Http\StructuredField\Dictionary;
use Bakame\Http\StructuredField\Item;
use Bakame\Http\StructuredField\Parameters;

$dictionary = new Dictionary();
$dictionary->set('a', Item::fromBoolean(false));
$dictionary->set('b', Item::fromBoolean(true));

$parameters = new Parameters(['foo' => Item::fromToken(new Token('bar'))]);
$dictionary->set('c', Item::fromBoolean(true, $parameters));

$dictionary->canonical();   //returns "a=?0, b, c;foo=bar"

$dictionary->hasKey('a');   //return true
$dictionary->hasKey('foo'); //return false
$dictionary->getByIndex(1); //return Item::fromBoolean(true)
$dictionary->set('z', Item::fromDecimal(42));
$dictionary->unset('b', 'c');
echo $dictionary->canonical(); //returns "a=?0, z=42.0"
```

`Parameters` can only contains `Item` instances whereas `Dictionary` instance can also contain lists.

#### Lists

The `OrderedList` and the `InnerList` classes are list of members that act as containers and also expose 
the following methods `push`, `unshift`, `insert`, `replace`, `remove` methods to enable manipulation their content.

**EVERY CHANGE IN THE LIST WILL RE-INDEX THE LIST AS TO NOT EXPOSE MISSING INDEXES**

```php
use Bakame\Http\StructuredField\OrderedList;

$list = OrderedList::fromField("(\"foo\" \"bar\"), (\"baz\"), (\"bat\" \"one\"), ()");
$list->hasIndex(2); //return true
$list->hasIndex(42); //return false
$list->push(Item::fromDecimal(42));
$list->remove(0, 2);
echo $list->canonical(); //returns "("baz"), (), 42.0"
```

The distinction between `InnerList` and `OrderedList` is well explained in the RFC but the main ones are:

- `InnerList` members can be `Items` or `null`;
- `OrderedList` members can be `InnerList`, `Items`;
- `InnerList` can have a `Parameters` instance attached to it, not `OrderedList`;

Contributing
-------

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CODE OF CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

Testing
-------

The library has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](https://cs.sensiolabs.org/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

``` bash
$ composer test
```

Security
-------

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

Credits
-------

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/uri/contributors)

Attribution
-------

This package is heavily inspired by previous work done by [Gapple](https://twitter.com/gappleca) on [Structured Field Values for PHP](https://github.com/gapple/structured-fields/).

License
-------

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[1]: https://www.rfc-editor.org/rfc/rfc8941.html
