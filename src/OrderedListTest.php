<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

/**
 * @coversDefaultClass \Bakame\Http\StructuredFields\OrderedList
 */
final class OrderedListTest extends StructuredFieldTest
{
    /** @var array|string[] */
    protected array $paths = [
        __DIR__.'/../vendor/httpwg/structured-field-tests/list.json',
        __DIR__.'/../vendor/httpwg/structured-field-tests/listlist.json',
    ];

    /**
     * @test
     */
    public function it_can_be_instantiated_with_an_collection_of_item(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromList($arrayParams);

        self::assertSame($stringItem, $instance->get(0));
        self::assertFalse($instance->isEmpty());

        self::assertEquals($arrayParams, iterator_to_array($instance, true));
    }

    /**
     * @test
     */
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [$stringItem, $booleanItem];
        $instance = OrderedList::fromList($arrayParams);

        self::assertCount(2, $instance);
        self::assertSame($booleanItem, $instance->get(1));

        $instance->remove(1);

        self::assertCount(1, $instance);
        self::assertFalse($instance->has(1));

        $instance->push(Item::from('BarBaz'));
        $member = $instance->get(1);

        self::assertCount(2, $instance);
        self::assertInstanceOf(Item::class, $member);
        self::assertIsString($member->value);
        self::assertStringContainsString('BarBaz', $member->value);

        $instance->remove(0, 1);
        self::assertCount(0, $instance);
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_can_unshift_insert_and_replace(): void
    {
        $instance = OrderedList::fromList();
        $instance->unshift(Item::from('42'));
        $instance->push(Item::from(42));
        $instance->insert(1, Item::from(42.0));
        $instance->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));

        self::assertCount(3, $instance);
        self::assertFalse($instance->isEmpty());
        self::assertSame(':SGVsbG8gV29ybGQ=:, 42.0, 42', $instance->toHttpValue());
        $instance->clear();
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @test
     */
    public function it_fails_to_replace_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromList();
        $container->replace(0, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_insert_at_an_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $container = OrderedList::fromList();
        $container->insert(3, Item::from(ByteSequence::fromDecoded('Hello World')));
    }

    /**
     * @test
     */
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $this->expectException(InvalidOffset::class);

        $instance = OrderedList::fromList();
        self::assertFalse($instance->has(3));

        $instance->get(3);
    }

    /**
     * @test
     */
    public function it_can_be_regenerated_with_eval(): void
    {
        $instance = OrderedList::from(Item::from(false));

        /** @var OrderedList $generatedInstance */
        $generatedInstance = eval('return '.var_export($instance, true).';');

        self::assertEquals($instance, $generatedInstance);
    }

    /**
     * @test
     */
    public function test_it_can_generate_the_same_value(): void
    {
        $res = OrderedList::fromHttpValue('token, "string", ?1; parameter, (42 42.0)');

        $list = OrderedList::fromList([
            Token::fromString('token'),
            'string',
            Item::from(true, ['parameter' => true]),
            InnerList::fromList([42, 42.0]),
        ]);

        self::assertSame($res->toHttpValue(), $list->toHttpValue());
    }
}
