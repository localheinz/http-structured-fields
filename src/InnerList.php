<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use DateTimeInterface;
use Iterator;
use Stringable;
use function array_filter;
use function array_map;
use function array_splice;
use function array_values;
use function count;
use function implode;
use function is_int;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.1
 * @implements MemberList<int, Value>
 * @phpstan-import-type DataType from Value
 */
final class InnerList implements MemberList, ParameterAccess
{
    /** @var list<Value> */
    private readonly array $members;

    /**
     * @param iterable<Value|DataType> $members
     */
    private function __construct(private readonly Parameters $parameters, iterable $members)
    {
        $this->members = array_map(self::filterMember(...), array_values([...$members]));
    }

    private static function filterMember(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): Value
    {
        return match (true) {
            $member instanceof Value => $member,
            $member instanceof StructuredField => throw new InvalidArgument('Expecting a "'.Value::class.'" instance; received a "'.$member::class.'" instead.'),
            default => Item::from($member),
        };
    }

    /**
     * Returns a new instance.
     */
    public static function from(Value|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): self
    {
        return new self(Parameters::create(), $members);
    }

    /**
     * @param array{
     *     0:iterable<Value|DataType>,
     *     1?:MemberOrderedMap<string, Value>|iterable<array{0:string, 1:Value|DataType}>
     * } $pair
     */
    public static function fromPair(array $pair): self
    {
        $pair[1] = $pair[1] ?? [];

        return match (true) {
            !array_is_list($pair) => throw new SyntaxError('The pair must be represented by an array as a list.'),  // @phpstan-ignore-line
            2 !== count($pair) => throw new SyntaxError('The pair first value should be the innerlist members and the optional second value the innerlist parameters.'),   // @phpstan-ignore-line
            default => new self(Parameters::fromPairs($pair[1]), $pair[0]),
        };
    }

    /**
     * Returns a new instance with an iter.
     *
     * @param iterable<Value|DataType> $members
     * @param iterable<string,Value|DataType> $parameters
     */
    public static function fromAssociative(iterable $members = [], iterable $parameters = []): self
    {
        return new self(Parameters::fromAssociative($parameters), $members);
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        return self::fromAssociative(...Parser::parseInnerList($httpValue));
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
    }

    public function parameter(string $key): mixed
    {
        if ($this->parameters->has($key)) {
            return $this->parameters->get($key)->value();
        }

        return null;
    }

    public function addParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->add($key, $member));
    }

    public function prependParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->prepend($key, $member));
    }

    public function appendParameter(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        return $this->withParameters($this->parameters()->append($key, $member));
    }

    public function withoutParameter(string ...$keys): static
    {
        return $this->withParameters($this->parameters()->remove(...$keys));
    }

    public function withoutAnyParameter(): static
    {
        return $this->withParameters(Parameters::create());
    }

    public function withParameters(Parameters $parameters): static
    {
        if ($this->parameters->toHttpValue() === $parameters->toHttpValue()) {
            return $this;
        }

        return new static($parameters, $this->members);
    }

    public function toHttpValue(): string
    {
        return '('.implode(' ', array_map(fn (StructuredField $value): string => $value->toHttpValue(), $this->members)).')'.$this->parameters->toHttpValue();
    }

    public function __toString(): string
    {
        return $this->toHttpValue();
    }

    public function count(): int
    {
        return count($this->members);
    }

    public function hasNoMembers(): bool
    {
        return !$this->hasMembers();
    }

    public function hasMembers(): bool
    {
        return [] !== $this->members;
    }

    /**
     * @return array<int>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $offset) {
            if (null === $this->filterIndex($offset)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    private function filterIndex(string|int $index): int|null
    {
        if (!is_int($index)) {
            return null;
        }

        $max = count($this->members);

        return match (true) {
            [] === $this->members,
            0 > $max + $index,
            0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function get(string|int $key): StructuredField
    {
        $index = $this->filterIndex($key);
        if (null === $index) {
            throw InvalidOffset::dueToIndexNotFound($key);
        }

        return $this->members[$index];
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param int $offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ForbiddenOperation(self::class.' instance can not be updated using '.ArrayAccess::class.' methods.');
    }

    /**
     * Inserts members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return $this->newInstance([...array_values($members), ...$this->members]);
    }

    /**
     * @param iterable<Value|DataType> $members
     */
    private function newInstance(iterable $members): self
    {
        return new self($this->parameters, $members);
    }

    /**
     * Inserts members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return $this->newInstance([...$this->members, ...array_values($members)]);
    }

    /**
     * Inserts members starting at the given index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        $offset = $this->filterIndex($key);

        return match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($key),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            [] === $members => $this,
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, $members);

                return $this->newInstance($newMembers);
            })($this->members),
        };
    }

    public function replace(int $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        if (null === ($offset = $this->filterIndex($key))) {
            throw InvalidOffset::dueToIndexNotFound($key);
        }

        $members = $this->members;
        $members[$offset] = $member;

        return $this->newInstance($members);
    }

    /**
     * Deletes members associated with the list of instance indexes.
     */
    public function remove(string|int ...$keys): static
    {
        $offsets = array_filter(
            array_map(
                fn (int $index): int|null => $this->filterIndex($index),
                array_filter($keys, static fn (string|int $key): bool => is_int($key))
            ),
            fn (int|null $index): bool => null !== $index
        );

        if ([] === $offsets) {
            return $this;
        }

        $members = $this->members;
        foreach ($offsets as $offset) {
            unset($members[$offset]);
        }

        return $this->newInstance($members);
    }
}
