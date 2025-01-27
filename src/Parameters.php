<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use ArrayAccess;
use DateTimeInterface;
use Iterator;
use Stringable;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function is_string;
use function trim;

/**
 * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
 * @implements MemberOrderedMap<string, Value>
 * @phpstan-import-type DataType from Value
 */
final class Parameters implements MemberOrderedMap
{
    /** @var array<string, Value> */
    private readonly array $members;

    /**
     * @param iterable<string, Value|DataType> $members
     */
    private function __construct(iterable $members = [])
    {
        $filteredMembers = [];
        foreach ($members as $key => $member) {
            $filteredMembers[MapKey::from($key)->value] = self::filterMember($member);
        }

        $this->members = $filteredMembers;
    }

    private static function filterMember(StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): Value
    {
        return match (true) {
            $member instanceof Value && $member->parameters()->hasNoMembers() => $member,
            !$member instanceof StructuredField => Item::from($member),
            default => throw new InvalidArgument('Parameters instances can only contain bare items.'),
        };
    }

    /**
     * Returns a new instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns a new instance from an associative iterable construct.
     *
     * its keys represent the dictionary entry key
     * its values represent the dictionary entry value
     *
     * @param iterable<array-key, Value|DataType> $members
     */
    public static function fromAssociative(iterable $members): self
    {
        return new self($members);
    }

    /**
     * Returns a new instance from a pair iterable construct.
     *
     * Each member is composed of an array with two elements
     * the first member represents the instance entry key
     * the second member represents the instance entry value
     *
     * @param MemberOrderedMap<string, Value>|iterable<array{0:string, 1:Value|DataType}> $pairs
     */
    public static function fromPairs(iterable $pairs): self
    {
        if ($pairs instanceof MemberOrderedMap) {
            $pairs = $pairs->toPairs();
        }

        return new self((function (iterable $pairs) {
            foreach ($pairs as [$key, $member]) {
                yield $key => $member;
            }
        })($pairs));
    }

    /**
     * Returns an instance from an HTTP textual representation.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8941.html#section-3.1.2
     *
     * @throws SyntaxError If the string is not a valid
     */
    public static function fromHttpValue(Stringable|string $httpValue): self
    {
        $httpValue = trim((string) $httpValue);
        [$parameters, $offset] = Parser::parseParameters($httpValue);
        if (strlen($httpValue) !== $offset) {
            throw new SyntaxError('The HTTP textual representation "'.$httpValue.'" for Parameters contains invalid characters.');
        }

        return self::fromAssociative($parameters);
    }

    public function toHttpValue(): string
    {
        $formatter = static fn (Value $member, string $offset): string => match (true) {
            true === $member->value() => ';'.$offset,
            default => ';'.$offset.'='.$member->toHttpValue(),
        };

        return implode('', array_map($formatter, $this->members, array_keys($this->members)));
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

    public function getIterator(): Iterator
    {
        yield from $this->members;
    }

    /**
     * @return Iterator<array{0:string, 1:Value}>
     */
    public function toPairs(): Iterator
    {
        foreach ($this->members as $index => $member) {
            yield [$index, $member];
        }
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function has(string|int ...$keys): bool
    {
        foreach ($keys as $offset) {
            if (!is_string($offset) || !array_key_exists($offset, $this->members)) {
                return false;
            }
        }

        return [] !== $keys;
    }

    /**
     * @throws SyntaxError   If the key is invalid
     * @throws InvalidOffset If the key is not found
     */
    public function get(string|int $key): Value
    {
        if (!$this->has($key)) {
            throw InvalidOffset::dueToKeyNotFound($key);
        }

        return $this->members[$key];
    }

    public function hasPair(int ...$indexes): bool
    {
        foreach ($indexes as $index) {
            try {
                $this->filterIndex($index);
            } catch (InvalidOffset) {
                return false;
            }
        }

        return [] !== $indexes;
    }

    /**
     * Filters and format instance index.
     */
    private function filterIndex(int $index): int
    {
        $max = count($this->members);

        return match (true) {
            [] === $this->members, 0 > $max + $index, 0 > $max - $index - 1 => throw InvalidOffset::dueToIndexNotFound($index),
            0 > $index => $max + $index,
            default => $index,
        };
    }

    /**
     * @throws InvalidOffset if the index is not found
     *
     * @return array{0:string, 1:Value}
     */
    public function pair(int $index): array
    {
        return [...$this->toPairs()][$this->filterIndex($index)];
    }

    public function add(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        $members = $this->members;
        $members[$key] = $member;

        return new self($members);
    }

    public function remove(string|int ...$keys): static
    {
        $members = $this->members;
        foreach (array_filter($keys, static fn (string|int $key): bool => is_string($key)) as $key) {
            unset($members[$key]);
        }

        if ($members === $this->members) {
            return $this;
        }

        return new self($members);
    }

    public function append(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);
        $members[$key] = $member;

        return new self($members);
    }

    public function prepend(string $key, StructuredField|Token|ByteSequence|DateTimeInterface|Stringable|string|int|float|bool $member): static
    {
        $members = $this->members;
        unset($members[$key]);

        return new self([$key => $member, ...$members]);
    }

    /**
     * @param iterable<string, Value|DataType> ...$others
     */
    public function mergeAssociative(iterable ...$others): static
    {
        $members = $this->members;
        foreach ($others as $other) {
            $members = [...$members, ...self::fromAssociative($other)->members];
        }

        return new self($members);
    }

    /**
     * @param MemberOrderedMap<string, Value>|iterable<array{0:string, 1:Value|DataType}> ...$others
     */
    public function mergePairs(MemberOrderedMap|iterable ...$others): static
    {
        $members = $this->members;
        foreach ($others as $other) {
            $members = [...$members, ...self::fromPairs($other)->members];
        }

        return new self($members);
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param string $offset
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
