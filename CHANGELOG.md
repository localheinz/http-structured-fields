# Changelog

All Notable changes to `bakame/http-strucured-fields` will be documented in this file.

## [Next] - TBD

### Added

- `InnerList::fromPair` to improve InnerList public API;
- `InnerList::fromAssociative` to improve InnerList public API;
- `Tupe::from` and `Tupe::tryFrom`  to improve Type public API;

### Fixed

- None

### Deprecated

- None

### Removed

- **[BC Break]** `InnerList::fromPairParameters` use `InnerList::fromPair` instead.
- **[BC Break]** `InnerList::fromAssociativeParameters` use `InnerList::fromAssociative` instead.
- **[BC Break]** `Type::fromValue` use `Type::from` instead.

## [0.8.0] - 2023-03-12

### Added

- `Item::fromTimestamp`, `Item::fromDateFormat`, `Item::fromDateString` to improve item instantiation with dates.
- `ParameterAccess::parameter` to ease parameter members value access.
- `InnerList::fromAssociativeParameters`, `InnerList::fromPairParameters`  to improve item instantiation with parameters.
- **[BC Break]** `ParameterAccess::withoutAllParameters` is renamed `ParameterAccess::withoutAnyParameter`.
- **[BC Break]** `OrderedList` is renamed `OuterList`.
- **[BC Break]** `MemberContainer::remove` methods get added to the interface.
- **[BC Break]** `MemberContainer::keys` method added to the interface.

### Fixed

- Test suite migrated to PHPUnit 10
- Adding Benchmark test with PHPBench
- Improve Collection immutability with method changes
- **[BC Break]** `ParameterAccess` interface signature updated to use the `Value` interface instead of the `Item` implementation.
- **[BC Break]** `MemberList::remove`, `MemberOrderedMap::remove` and `MemberOrderedMap::keys` methods are moved to their parent interface.
- **[BC Break]** Renamed arguments for indexation for normalization 
- **[BC Break]** `MemberContainer::has` and `MemberOrderedMap::hasPair` methods accept a variadic argument. All submitted indexes/keys should be present for the method to return `true`

### Deprecated

- None

### Removed

- **[BC Break]** `OrderedList` is removed, use `OuterList` instead.
- **[BC Break]**  `ParameterAccess::withoutAllParameters` is removed, use `ParameterAccess::withoutAnyParameters` instead.
- **[BC Break]**  remove the `$parameters` argument from all `Item` named constuctors except from `Item::from`.
- **[BC Break]**  remove `InnerList::fromList`, use `InnerList::fromAssociativeParameters` or `InnerList::fromPairParameters` instead.
- **[BC Break]**  remove `OuterList::fromList`, use `OuterList::from` instead.
- 
## [0.7.0] - 2023-02-06

### Added

- Support for `Stringable` instances added to `Item::from`, the instances will be converted to the string data type.
- Support for the upcoming `Date` data type in `Item` represented as a `DateTimeImmutable` object. (see https://httpwg.org/http-extensions/draft-ietf-httpbis-sfbis.html)
- `ParameterAccess` interface with new methods to ease parameter members modification.
- `Parameter::create` named constructor to create a new instance without any parameter.
- `Dictionnary::create` named constructor to create a new instance without any parameter.
- `Type` Enum of all supported datatype.
- `Value` Interface is introduced with `Item` being the only available implementation.
- `MemberOrderedMap::add` and `MemberOrderedMap::remove` methods
- `ByteSequence::equals` and `Token::equals` to easily compare type instances.
- `StructuredField` extends the `Stringable` interface
- `ForbiddenOperation` exception to reports invalid operation on immutable value objects.

### Fixed

- `Item::fromHttpValue` now internally uses the `Parser` previously it was using its own parsing rules.
- `Parameters::fromHttpValue` now internally uses the `Parser` previously it was using its own parsing rules.
- **[BC Break]** `::fromAssociative`, `::fromList`, `::fromPairs` methods require iterable arguments without default value.
- **[BC Break]** `Item::value` method returns the Item (returns value can be `float|int|string|bool|ByteSequence|DateTimeImmutable|Token`).
- **[BC Break]** `InnerList::parameters` is no longer accessible as a public readonly property.
- **[BC Break]** Modifying container instances with `ArrayAccess` modifying methods is forbidden and will trigger a `ForbiddenOperation` exception.

### Deprecated

- None

### Removed

- **[BC Break]** `ForbiddenStateError` exception is removed; the `InvalidArgument` exception is used instead.
- **[BC Break]** `Item::is*` methods are removed; the enum `Type` is used instead.
- **[BC Break]** `MemberContainer::clear` method is removed without replacement.
- **[BC Break]** `MemberOrderedMap::set` and `MemberOrderedMap::delete` methods remonved; use `MemberOrderedMap::add` and `MemberOrderedMap::remove` instead

## [0.6.0] - 2022-11-12

### Added

- The `Container`, `MemberList`, `MemberOrderedMap`, `ParameterAccess` interfaces.
- `OrderedList` and `InnerList` implement the `MemberList` interface.
- `Parameters` and `Dictionnary` implement the `MemberOrderedMap` interface.
- The `InvalidArgument` exception.
- `Token::value` is a readonly property.
- `Item::value` method returns the decoded value of an Item (returns value can be `float|int|string|bool`).
- `Item::fromToken`, `Item::fromDecodedByteSequence` , `Item::fromEncodedByteSequence` to ease `Item` creation. 
- `Item::withValue` to ease `Item` value update.
- `Parser` methods also accepts `Stringable` objects.

### Fixed

- None.

### Deprecated

- None

### Removed

- **[BC Break]** `__set_state` implementation in all objects.
- **[BC Break]** `Token` no longer implements the `StructuredField` interface.
- **[BC Break]** `Token::toHttpValue` is removed; use the `Item` class to serialize a `Token`.
- **[BC Break]** `Token::toString` is removed use its readonly property instead `Token::value`.
- **[BC Break]** `ByteSequence` no longer implements the `StructuredField` interface.
- **[BC Break]** `ByteSequence::toHttpValue` is removed; use the `Item` class to serialize a `ByteSequence`.
- **[BC Break]** `::sanitize` method is removed use `Parameters::clear` method instead if needed.
- **[BC Break]** `isEmpty` method is removed use `hasMembers` method instead.
- **[BC Break]** `Parameters::value` use `Item::value` method instead.
- **[BC Break]** `Parameters::values` use `Parameters::getIterator` instead.
- **[BC Break]** `Item::value` public readonly property use `Item::value` method instead.
- **[BC Break]** `Item::parameters` public readonly property use `Item::parameters` method instead.
- **[BC Break]** `InnerList::parameters` public readonly property use `InnerList::parameters` method instead.

## [0.5.0] - 2022-05-13

### Added

- `Item::fromPair` named constructor to create a new instance from a pair expressed as an array list with two values.
- `Parameters::sanitize` ensure the container always contains only Bare Items.
- `InnerList::sanitize` ensure the list is without gaps and calls `Parameters::sanitize`.
- `OrderedList::sanitize` ensure the list is without gaps and calls `Parameters::sanitize`.
- `Dictionnary::sanitize` ensure the list is without gaps and calls `Parameters::sanitize`.
- `Item::sanitize` calls `Parameters::sanitize`.
- `autoload.php` script to allow non composer application to load the package
- `OrderedList` and `InnerList` now implements the PHP `ArrayAccess` interface.

### Fixed

- `InnerList::fromHttpValue` accepts Optional White Spaces at the start of its textual representation.
- `Parameters::fromHttpValue` accepts Optional White Spaces at the start of its textual representation.
- `Item::fromHttpValue` bugfix parsing Token data type.

### Deprecated

- None

### Removed

- **[BC Break]** `InnerList` no longer re-index its content replaced by `InnerList::sanitize` to force re-indexation.
- **[BC Break]** `OrdererList` no longer re-index its content replaced by `InnerList::sanitize` to force re-indexation.

## [0.4.0] - 2022-03-27

### Added

- `Dictionary::mergeAssociative` and `Dictionary::mergePairs` to allow merging with associative structures
- `Parameters::mergeAssociative` and `Parameters::mergePairs` to allow merging with key-value pairs structures

### Fixed

- All containers `Dictionary`, `InnerList`, `OrderedList`, `Parameters` modifying methods are made chainable.
- `Parser` only returns `array`'s of items or bare items value.
- All `Parameters` getters checks for bare items validity.
- `ForbiddenStateError` extends SPL `LogicException` instead of `UnexpectedValueException`

### Deprecated

- None

### Removed

- **[BC Break]** `Dictionary::merge` replaced by `Dictionary::mergeAssociative`
- **[BC Break]** `Parameters::merge` replaced by `Parameters::mergeAssociative`

## [0.3.0] - 2022-03-21

### Added

- `InnerList::fromHttpValue` named constructor to make the public API consistent for all VOs

### Fixed

- None

### Deprecated

- None

### Removed

- None

## [0.2.0] - 2022-03-20

### Added

- `Item::value` is a public readonly property that gives access to the item value
- `Item::parameters` is a public readonly property that gives access to the item parameters
- `InnerList::parameters` is a public readonly property that gives access to the list parameters
- `OrderedList::from` named constructor which accepts a variadic list of members items
- `Token::fromString` named constructor which accepts `string` and `Stringable` object
- `Parameter::values` returns an array of all the values contained inside the `Parameters` instance
- **[BC Break]** `ForbiddenStateError` to replace `SerializationError` 
- **[BC Break]** `InnerList::fromList` to replace `InnerList::fromMembers`
- **[BC Break]** `OrderedList::fromList` to replace `OrderedList::fromMembers`
- **[BC Break]** `Parameter::value` to replace `InnerList::parameter` and `Item::parameter`

### Fixed

- `ByteSequence::fromDecoded` named constructor also accepts a `Stringable` object
- `ByteSequence::fromEncoded` named constructor also accepts a `Stringable` object
- `Dictionary::merge` accepts any iterable that can be accepted by `Dictionary::fromAssociative` as variadic parameter
- `Parameter::merge` accepts any iterable that can be accepted by `Parameter::fromAssociative` as variadic parameter
- **[BC Break]** `OrderedList::__construct` is made private use `OrderedList::from` instead
- **[BC Break]** `InnerList::__construct` is made private use `InnerList::fromList` instead
- **[BC Break]** `Token::__construct` is made private use `Token::fromString` instead
- **[BC Break]** `Parameter::get`, `Parameter::value`, `Parameter::pair` will throw `ForbiddenStateError` if the BareItem is in invalid state.

### Deprecated

- None

### Removed

- **[BC Break]** `InnerList::fromMembers` replaced by `InnerList::fromList`
- **[BC Break]** `OrderedList::fromMembers` replaced by `OrderedList::fromList`
- **[BC Break]** `Item::parameter` replaced by `Parameter::value`
- **[BC Break]** `InnerList::parameter` replaced by `Parameter::value`
- **[BC Break]** `SupportsParameters` interface is removed without replacement
- **[BC Break]** `Item::value()` replaced by `Item::value` public readonly property
- **[BC Break]** `Item::parameters()` replaced by `Item::parameters` public readonly property
- **[BC Break]** `InnerList::parameters()` replaced by `InnerList::parameters` public readonly property
- **[BC Break]** `InnerList::merge()` use `InnerList::push()` or `InnerList::unshift()` instead
- **[BC Break]** `OrderedList::merge()` use `OrderedList::push()` or `OrderedList::unshift()` instead
- **[BC Break]** `SerializationError` use `ForbiddenStateError` instead

## [0.1.0] - 2022-03-18

**Initial release!**

[Next]: https://github.com/bakame-php/http-structured-fields/compare/0.8.0...master
[0.8.0]: https://github.com/bakame-php/http-structured-fields/compare/0.7.0...0.8.0
[0.7.0]: https://github.com/bakame-php/http-structured-fields/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/bakame-php/http-structured-fields/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/bakame-php/http-structured-fields/compare/0.4.0...0.5.0
[0.4.0]: https://github.com/bakame-php/http-structured-fields/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/bakame-php/http-structured-fields/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/bakame-php/http-structured-fields/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/bakame-php/http-structured-fields/releases/tag/0.1.0
