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

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.1
 * @implements MemberList<int, Value>
 * @phpstan-import-type DataType from Item
 */
final class InnerList implements MemberList, ParameterAccess
{
    /** @var list<Value> */
    private array $members;

    /**
     * @param iterable<Value|DataType> $members
     */
    private function __construct(private readonly Parameters $parameters, iterable $members)
    {
        $this->members = array_map(self::filterMember(...), array_values([...$members]));
    }

    /**
     * Returns a new instance.
     */
    public static function from(Value|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        return new self(Parameters::create(), $members);
    }

    /**
     * @param iterable<Value|DataType> $members
     * @param iterable<string, Value|DataType> $parameters
     */
    public static function fromList(iterable $members = [], iterable $parameters = []): self
    {
        return new self(Parameters::fromAssociative($parameters), $members);
    }

    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        return InnerList::fromList(...Parser::parseInnerList($httpValue));
    }

    public function parameters(): Parameters
    {
        return $this->parameters;
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

    public function withoutAllParameters(): static
    {
        return $this->withParameters(Parameters::create());
    }

    public function withParameters(Parameters $parameters): static
    {
        if ($this->parameters->toHttpValue() === $parameters->toHttpValue()) {
            return $this;
        }

        return new self($parameters, $this->members);
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
     * @return Iterator<array-key, Value>
     */
    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    public function has(string|int $offset): bool
    {
        return null !== $this->filterIndex($offset);
    }

    private function filterIndex(string|int $index): int|null
    {
        if (!is_int($index)) {
            return null;
        }

        $max = count($this->members);

        return match (true) {
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => null,
            0 > $index => $max + $index,
            default => $index,
        };
    }

    public function get(string|int $offset): Value
    {
        $index = $this->filterIndex($offset);
        if (null === $index) {
            throw InvalidOffset::dueToIndexNotFound($offset);
        }

        return $this->members[$index];
    }

    /**
     * Insert members at the beginning of the list.
     */
    public function unshift(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return new self($this->parameters, [...array_map(self::filterMember(...), array_values($members)), ...$this->members]);
    }

    /**
     * Insert members at the end of the list.
     */
    public function push(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        if ([] === $members) {
            return $this;
        }

        return new self($this->parameters, [...$this->members, ...array_map(self::filterMember(...), array_values($members))]);
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
     * Replace the member associated with the index.
     *
     * @throws InvalidOffset If the index does not exist
     */
    public function insert(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool ...$members): static
    {
        $offset = $this->filterIndex($index);

        return match (true) {
            null === $offset => throw InvalidOffset::dueToIndexNotFound($index),
            0 === $offset => $this->unshift(...$members),
            count($this->members) === $offset => $this->push(...$members),
            [] === $members => $this,
            default => (function (array $newMembers) use ($offset, $members) {
                array_splice($newMembers, $offset, 0, array_map(self::filterMember(...), $members));

                return new self($this->parameters, $newMembers);
            })($this->members),
        };
    }

    public function replace(int $index, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        if (null === ($offset = $this->filterIndex($index))) {
            throw InvalidOffset::dueToIndexNotFound($index);
        }

        $members = $this->members;
        $members[$offset] = self::filterMember($member);

        return new self($this->parameters, $members);
    }

    /**
     * Delete members associated with the list of instance indexes.
     */
    public function remove(int ...$indexes): static
    {
        $offsets = array_filter(
            array_map(fn (int $index): int|null => $this->filterIndex($index), $indexes),
            fn (int|null $index): bool => null !== $index
        );

        if ([] === $offsets) {
            return $this;
        }

        $members = $this->members;
        foreach ($offsets as $offset) {
            unset($members[$offset]);
        }

        return new self($this->parameters, $members);
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
    public function offsetGet(mixed $offset): Value
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
}
