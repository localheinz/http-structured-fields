<?php

declare(strict_types=1);

namespace Bakame\Http\StructuredFields;

use LogicException;
use PHPUnit\Framework\Attributes\Test;

final class DictionaryTest extends StructuredFieldTestCase
{
    /** @var array<string> */
    protected static array $httpWgTestFilenames = [
        'dictionary.json',
    ];

    #[Test]
    public function it_can_be_instantiated_with_an_collection_of_item_or_inner_list(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));
        self::assertFalse($instance->has('not-present'));
        self::assertEquals([['string', $stringItem], ['boolean', $booleanItem]], [...$instance->toPairs()]);
        self::assertEquals($arrayParams, [...$instance]);
    }

    #[Test]
    public function it_can_be_instantiated_with_key_value_pairs(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = [['string', $stringItem], ['boolean', $booleanItem]];
        $instance = Dictionary::fromPairs($arrayParams);

        self::assertSame(['string', $stringItem], $instance->pair(0));
        self::assertSame($stringItem, $instance->get('string'));
        self::assertTrue($instance->has('string'));
        self::assertEquals($arrayParams, [...$instance->toPairs()]);
        self::assertEquals(['string' => $stringItem, 'boolean' => $booleanItem], [...$instance]);
    }

    #[Test]
    public function it_can_add_or_remove_members(): void
    {
        $stringItem = Item::from('helloWorld');
        $booleanItem = Item::from(true);
        $arrayParams = ['string' => $stringItem, 'boolean' => $booleanItem];
        $instance = Dictionary::fromAssociative($arrayParams);

        self::assertCount(2, $instance);
        self::assertTrue($instance->hasMembers());
        self::assertFalse($instance->hasNoMembers());

        $deletedInstance = $instance->remove('boolean');

        self::assertCount(1, $deletedInstance);
        self::assertFalse($deletedInstance->has('boolean'));
        self::assertFalse($deletedInstance->hasPair(1));

        $appendInstance = $deletedInstance->append('foobar', Item::from('BarBaz'));
        self::assertTrue($appendInstance->hasPair(1));

        /** @var array{0:string, 1:Item} $foundItem */
        $foundItem = $appendInstance->pair(1);

        self::assertIsString($foundItem[1]->value());
        self::assertStringContainsString('BarBaz', $foundItem[1]->value());

        $deleteAgain = $appendInstance->remove('foobar', 'string');

        self::assertCount(0, $deleteAgain);
        self::assertFalse($deleteAgain->hasMembers());
    }

    #[Test]
    public function it_returns_the_same_object_if_no_member_is_removed(): void
    {
        $instance = Dictionary::create();

        self::assertSame($instance, $instance->remove('foo', 'bar', 'baz'));
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_key(): void
    {
        $instance = Dictionary::create();

        self::assertFalse($instance->has('foobar'));

        $this->expectException(InvalidOffset::class);

        $instance->get('foobar');
    }

    #[Test]
    public function it_fails_to_return_an_member_with_invalid_index(): void
    {
        $instance = Dictionary::create();

        self::assertFalse($instance->hasPair(1, 2, 3));
        self::assertFalse($instance->hasPair());

        $this->expectException(InvalidOffset::class);

        $instance->pair(3);
    }

    #[Test]
    public function it_fails_to_add_an_item_with_wrong_key(): void
    {
        $this->expectException(SyntaxError::class);

        Dictionary::fromAssociative(['bébé'=> Item::from(false)]);
    }

    #[Test]
    public function it_can_prepend_a_new_member(): void
    {
        $instance = Dictionary::create()
            ->append('a', Item::from(false))
            ->prepend('b', Item::from(true));

        self::assertSame('b, a=?0', (string) $instance);
    }

    #[Test]
    public function it_can_returns_the_container_member_keys(): void
    {
        $instance = Dictionary::create();

        self::assertSame([], $instance->keys());

        $newInstance = $instance
            ->append('a', Item::from(false))
            ->prepend('b', Item::from(true));

        self::assertSame(['b', 'a'], $newInstance->keys());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::from(42)]);

        $instance4 = $instance1->mergeAssociative($instance2, $instance3);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::from(42), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_two_or_more_instances_to_yield_different_result(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);
        $instance3 = Dictionary::fromAssociative(['a' => Item::from(42)]);

        $instance4 = $instance3->mergeAssociative($instance2, $instance1);

        self::assertCount(2, $instance4);
        self::assertEquals(Item::from(false), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_argument_and_not_throw(): void
    {
        self::assertCount(1, Dictionary::fromAssociative(['a' => Item::from(false)])->mergeAssociative());
    }

    #[Test]
    public function it_can_merge_one_or_more_instances_using_pairs(): void
    {
        $instance1 = Dictionary::fromPairs([['a', Item::from(false)]]);
        $instance2 = Dictionary::fromPairs([['b', Item::from(true)]]);
        $instance3 = Dictionary::fromPairs([['a', Item::from(42)]]);

        $instance4 = $instance3->mergePairs($instance2, $instance1);

        self::assertCount(2, $instance4);

        self::assertEquals(Item::from(false), $instance4->get('a'));
        self::assertEquals(Item::from(true), $instance4->get('b'));
    }

    #[Test]
    public function it_can_merge_without_pairs_and_not_throw(): void
    {
        $instance = Dictionary::fromAssociative(['a' => Item::from(false)]);

        self::assertCount(1, $instance->mergePairs());
    }

    #[Test]
    public function it_can_merge_dictionary_instances_via_pairs_or_associative(): void
    {
        $instance1 = Dictionary::fromAssociative(['a' => Item::from(false)]);
        $instance2 = Dictionary::fromAssociative(['b' => Item::from(true)]);

        $instance3 = clone $instance1;
        $instance4 = clone $instance2;

        self::assertEquals($instance3->mergeAssociative($instance4), $instance1->mergePairs($instance2));
    }

    #[Test]
    public function it_can_handle_string_with_comma(): void
    {
        $expected = 'a=foobar;test="bar, baz", b=toto';
        $instance = Dictionary::fromHttpValue($expected);

        self::assertSame($expected, $instance->toHttpValue());
        self::assertCount(2, $instance);
    }

    #[Test]
    public function it_can_delete_a_member_via_array_access(): void
    {
        $structuredField = Dictionary::create();
        $newInstance = $structuredField->add('foo', 'bar');

        self::assertTrue($newInstance->hasMembers());
        self::assertFalse($newInstance->remove('foo')->hasMembers());
    }

    #[Test]
    public function it_fails_to_fetch_an_value_using_an_integer(): void
    {
        $this->expectException(StructuredFieldError::class);

        Dictionary::create()->get(0);
    }

    #[Test]
    public function it_can_access_the_item_value(): void
    {
        $token = Token::fromString('token');

        $structuredField = Dictionary::fromPairs([
            ['foobar', 'foobar'],
            ['zero', 0],
            ['false', false],
            ['token', $token],
        ]);

        self::assertInstanceOf(Item::class, $structuredField->get('false'));
        self::assertFalse($structuredField->get('false')->value());
    }

    #[Test]
    public function it_implements_the_array_access_interface(): void
    {
        $token = Token::fromString('token');

        $structuredField = Dictionary::fromPairs([
            ['foobar', 'foobar'],
            ['zero', 0],
            ['false', false],
            ['token', $token],
        ]);

        self::assertInstanceOf(Item::class, $structuredField->get('false'));
        self::assertInstanceOf(Item::class, $structuredField['false']);

        self::assertFalse($structuredField->get('false')->value());
        self::assertFalse($structuredField['false']->value());
        self::assertFalse(isset($structuredField['toto']));
    }

    #[Test]
    public function it_forbids_removing_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        unset(Dictionary::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar']);
    }

    #[Test]
    public function it_forbids_adding_members_using_the_array_access_interface(): void
    {
        $this->expectException(LogicException::class);

        Dictionary::fromPairs([['foobar', 'foobar'], ['zero', 0]])['foobar'] = Item::from(false);
    }
}
