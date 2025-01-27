<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InnerListTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = InnerList::fromAssociative($arrayParams, ['test' => Item::from(42)]);

        self::assertSame($stringItem, $instance->get(0));
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());
        self::assertTrue($instance->parameters()->hasMembers());
        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $instance = InnerList::from($stringItem, $booleanItem);

        self::assertCount(2, $instance);
        self::assertTrue($instance->has(1));
        self::assertFalse($instance->parameters()->hasMembers());

        $instance = $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance = $instance
            ->push('BarBaz')
            ->insert(0, 'foo');

        $member = $instance->get(1);

        self::assertCount(3, $instance);
        self::assertIsString($member->value());
        self::assertStringContainsString('helloWorld', $member->value());

        $instance = $instance->remove(0, 1, 2);

        self::assertCount(0, $instance);
        self::assertFalse($instance->hasMembers());
    }

    #[Test]
    public function it_can_unshift_insert_and_replace(): void
    {
        $container = InnerList::from()
            ->unshift('42')
            ->push(42)
            ->insert(1, 42.0)
            ->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertCount(3, $container);
        self::assertTrue($container->hasMembers());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', $container->toHttpValue());
        self::assertSame('(:SGVsbG8gV29ybGQ=: 42.0 42)', (string) $container);
    }

    #[Test]
    public function it_returns_the_same_object_if_nothing_is_changed(): void
    {
        $container = InnerList::from(42, 'forty-two');

        $sameContainer = $container
            ->unshift()
            ->push()
            ->insert(1);

        self::assertSame($container, $sameContainer);
    }

    #[Test]
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->replace(0, ByteSequence::fromDecoded('Hello World'));
    }

    #[Test]
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->insert(3, ByteSequence::fromDecoded('Hello World'));
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = InnerList::from();

        self::assertFalse($instance->has(3));

        $this->expectException(InvalidOffset::class);

        $instance->get(3);
    }

    #[Test]
    public function it_can_access_its_parameter_values(): void
    {
        $instance = InnerList::fromAssociative([false], ['foo' => 'bar']);

        self::assertSame('bar', $instance->parameters()->get('foo')->value());
        self::assertSame('bar', $instance->parameter('foo'));
    }

    #[Test]
    public function it_fails_to_access_unknown_parameter_values(): void
    {
        $this->expectException(StructuredFieldError::class);

        InnerList::from(false)->parameters()->get('bar')->value();
    }

    #[Test]
    public function it_successfully_parse_a_http_field(): void
    {
        $instance = InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("');

        self::assertCount(3, $instance);
        self::assertCount(1, $instance->parameters());
        self::assertSame('bar(', $instance->parameters()->get('foo')->value());
        self::assertSame('hello)world', $instance->get(0)->value());
        self::assertSame(42, $instance->get(1)->value());
        self::assertSame(42.0, $instance->get(2)->value());
        self::assertEquals(Token::fromString('doe'), $instance->get(2)->parameters()->get('john')->value());
    }

    #[Test]
    public function it_successfully_parse_a_http_field_with_optional_white_spaces_in_front(): void
    {
        self::assertEquals(
            InnerList::fromHttpValue('("hello)world" 42 42.0;john=doe);foo="bar("'),
            InnerList::fromHttpValue('        ("hello)world" 42 42.0;john=doe);foo="bar("')
        );
    }

    #[Test]
    public function it_fails_to_insert_unknown_index_via_the_array_access_interface(): void
    {
        $this->expectException(StructuredFieldError::class);

        InnerList::from()->insert(0, Item::from(42.0));
    }

    #[Test]
    public function it_returns_the_same_object_if_no_member_is_removed(): void
    {
        self::assertCount(0, InnerList::from()->remove(0));
    }

    #[Test]
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(InvalidOffset::class);

        InnerList::from()->get('zero');
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');
        $input = ['foobar', 0, false, $token];
        $structuredField = InnerList::from(...$input);

        self::assertFalse($structuredField->get(2)->value());
        self::assertEquals($token, $structuredField->get(-1)->value());
    }

    #[Test]
    public function it_can_create_via_with_parameters_method_a_new_object(): void
    {
        $instance1 = InnerList::fromPair(
            [
            [Token::fromString('babayaga'), 'a', true],
            [['a', true]]],
        );
        $instance1bis = InnerList::fromPair([
            [Token::fromString('babayaga'), 'a', true],
            [['a', true]],
        ]);
        $instance2 = $instance1->withParameters(Parameters::fromAssociative(['a' => true]));
        $instance3 = $instance1->withParameters(Parameters::fromAssociative(['a' => false]));

        self::assertSame($instance1->toHttpValue(), $instance1bis->toHttpValue());
        self::assertSame($instance1, $instance2);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals([...$instance1], [...$instance3]);
    }

    #[Test]
    public function it_can_create_via_parameters_access_methods_a_new_object(): void
    {
        $instance1 = InnerList::fromAssociative([Token::fromString('babayaga'), 'a', true], ['a' => true]);
        $instance2 = $instance1->appendParameter('a', true);
        $instance7 = $instance1->addParameter('a', true);
        $instance3 = $instance1->prependParameter('a', false);
        $instance4 = $instance1->withoutParameter('b');
        $instance5 = $instance1->withoutParameter('a');
        $instance6 = $instance1->withoutAnyParameter();

        self::assertSame($instance1, $instance2);
        self::assertSame($instance1, $instance7);
        self::assertNotSame($instance1->parameters(), $instance3->parameters());
        self::assertEquals([...$instance1], [...$instance3]);
        self::assertSame($instance1, $instance4);
        self::assertFalse($instance5->parameters()->hasMembers());
        self::assertTrue($instance6->parameters()->hasNoMembers());
        self::assertTrue($instance1->parameter('a'));
        self::assertNull($instance5->parameter('a'));
    }

    #[Test]
    #[DataProvider('invalidPairProvider')]
    /**
     * @param array<mixed> $pair
     */
    public function it_fails_to_create_an_item_from_an_array_of_pairs(array $pair): void
    {
        $this->expectException(SyntaxError::class);

        InnerList::fromPair($pair);  // @phpstan-ignore-line
    }

    /**
     * @return iterable<string, array{pair:array<mixed>}>
     */
    public static function invalidPairProvider(): iterable
    {
        yield 'empty pair' => ['pair' => []];
        yield 'empty extra filled pair' => ['pair' => [1, [2], 3]];
        yield 'associative array' => ['pair' => ['value' => 'bar', 'parameters' => ['foo' => 'bar']]];
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $structuredField = InnerList::from('foobar', 'foobar', 'zero', 0);

        self::assertInstanceOf(Item::class, $structuredField->get(0));
        self::assertInstanceOf(Item::class, $structuredField[0]);

        self::assertFalse(isset($structuredField[42]));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(InnerList::from('foobar', 'foobar', 'zero', 0)[0]);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        InnerList::from('foobar', 'foobar', 'zero', 0)[0] = Item::from(false);
    }


    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = InnerList::from();

        self::assertSame([], $instance->keys());

        $newInstance = $instance
            ->push(Item::from(false), Item::from(true));

        self::assertSame([0, 1], $newInstance->keys());

        $container = InnerList::from()
            ->unshift('42')
            ->push(42)
            ->insert(1, 42.0)
            ->replace(0, ByteSequence::fromDecoded('Hello World'));

        self::assertSame([0, 1, 2], $container->keys());
    }
}
