<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Waffle\Commons\Serializer\Json\JsonCodec;
use Waffle\Commons\Serializer\Normalizer\Payload;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizer;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use Waffle\Commons\Serializer\Serializer;
use WaffleTests\Commons\Serializer\Compiled\CompiledNormalizers;
use WaffleTests\Commons\Serializer\Fixture\Account;
use WaffleTests\Commons\Serializer\Fixture\Address;
use WaffleTests\Commons\Serializer\Fixture\Blank;
use WaffleTests\Commons\Serializer\Fixture\Clash\A\BC;
use WaffleTests\Commons\Serializer\Fixture\Clash\AB\C;
use WaffleTests\Commons\Serializer\Fixture\Contact;
use WaffleTests\Commons\Serializer\Fixture\Customer;
use WaffleTests\Commons\Serializer\Fixture\Defaults;
use WaffleTests\Commons\Serializer\Fixture\Envelope;
use WaffleTests\Commons\Serializer\Fixture\Measures;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\OrderLine;
use WaffleTests\Commons\Serializer\Fixture\Priority;
use WaffleTests\Commons\Serializer\Fixture\Status;
use WaffleTests\Commons\Serializer\Fixture\TreeNode;

use function hash;
use function str_repeat;

/**
 * Every DTO shape of the matrix goes JSON → DTO → JSON through the compiled
 * normalizers and through the interpreted ones, which must agree byte for byte.
 */
#[CoversClass(Serializer::class)]
#[CoversClass(ShapeNormalizer::class)]
#[CoversClass(ShapeNormalizerProvider::class)]
#[CoversClass(Payload::class)]
#[CoversClass(JsonCodec::class)]
final class RoundTripTest extends AbstractTestCase
{
    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function roundTrips(): iterable
    {
        yield 'every field of an order' => [
            Order::class,
            '{"reference":"A-1","status":"paid","lines":[{"sku":"X","quantity":2,"unitPrice":9},'
                . '{"sku":"Y","quantity":1,"unitPrice":0.5}],"shipping":{"street":"1 rue Nationale","city":"Lille"},'
                . '"tags":["gift","fragile"],"priority":2,"history":["draft","paid"],"gift":true}',
            '{"reference":"A-1","status":"paid","lines":[{"sku":"X","quantity":2,"unitPrice":9.0},'
                . '{"sku":"Y","quantity":1,"unitPrice":0.5}],"shipping":{"street":"1 rue Nationale","city":"Lille","zip":null},'
                . '"tags":["gift","fragile"],"priority":2,"history":["draft","paid"],"gift":true}',
        ];
        yield 'an order with its defaults' => [
            Order::class,
            '{"reference":"B-2","status":"draft","lines":[],"shipping":null,"priority":null}',
            '{"reference":"B-2","status":"draft","lines":[],"shipping":null,"tags":[],"priority":null,"history":[],"gift":false}',
        ];

        $secret = str_repeat('k', 12);
        yield 'hooks and visibility' => [
            Customer::class,
            '{"id":"c-1","email":"Ada@Example.COM","password":"' . $secret . '"}',
            '{"email":"ada@example.com","passwordHash":"'
                . hash('sha256', $secret)
                . '","display":"ada@example.com","id":"c-1","nickname":null}',
        ];
        yield 'a computed property' => [
            Account::class,
            '{"owner":{"id":"c-2","email":"bob@example.com","password":"' . $secret . '","nickname":"Bobby"}}',
            '{"owner":{"email":"bob@example.com","passwordHash":"'
                . hash('sha256', $secret)
                . '","display":"Bobby","id":"c-2","nickname":"Bobby"}}',
        ];
        yield 'a tree, through a list of itself' => [
            TreeNode::class,
            '{"label":"root","children":[{"label":"a"},{"label":"b","children":[{"label":"b1"}]}]}',
            '{"label":"root","children":[{"label":"a","children":[],"parent":null},'
                . '{"label":"b","children":[{"label":"b1","children":[],"parent":null}],"parent":null}],"parent":null}',
        ];
        yield 'a tree, through self' => [
            TreeNode::class,
            '{"label":"leaf","parent":{"label":"root"}}',
            '{"label":"leaf","children":[],"parent":{"label":"root","children":[],"parent":null}}',
        ];
        yield 'DTOs without a readable property' => [
            Envelope::class,
            '{"marker":{"code":"m"},"markers":[{"code":"a"},{"code":"b"}],"optional":{"code":"o"}}',
            '{"marker":{},"markers":[{},{}],"optional":{}}',
        ];
        yield 'every default' => [
            Defaults::class,
            '{}',
            '{"nickname":"anon","count":3,"ratio":1.5,"priority":2,"status":"draft","active":true,"label":"it\'s"}',
        ];
        yield 'nulls over non-null defaults' => [
            Defaults::class,
            '{"nickname":null,"count":7,"ratio":2,"priority":1,"status":null,"active":false,"label":"x"}',
            '{"nickname":null,"count":7,"ratio":2.0,"priority":1,"status":null,"active":false,"label":"x"}',
        ];
        yield 'a list of every element kind' => [
            Measures::class,
            '{"counts":[1,2],"ratios":[1,2.5],"flags":[true,false],"priorities":[2,1],"notes":["n"]}',
            '{"counts":[1,2],"ratios":[1.0,2.5],"flags":[true,false],"priorities":[2,1],"notes":["n"]}',
        ];
        yield 'a null list' => [
            Measures::class,
            '{"counts":[],"ratios":[],"flags":[],"priorities":[]}',
            '{"counts":[],"ratios":[],"flags":[],"priorities":[],"notes":null}',
        ];
        yield 'an absent nullable field without a default' => [
            Contact::class,
            '{"name":"Ada"}',
            '{"name":"Ada","phone":null}',
        ];
        yield 'nothing in, nothing out' => [Blank::class, '{"ignored":true}', '{}'];
        yield 'unknown keys are ignored, Unicode and slashes kept' => [
            Address::class,
            '{"street":"Rue de l\'Église","city":"Montréal/Nord","country":"CA"}',
            '{"street":"Rue de l\'Église","city":"Montréal/Nord","zip":null}',
        ];
        yield 'colliding generated names' => [BC::class, '{"name":"a"}', '{"name":"a"}'];
        yield 'the other colliding name' => [C::class, '{"name":"c"}', '{"name":"c"}'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('roundTrips')]
    public function testRoundTripsThroughBothNormalizers(string $class, string $input, string $expected): void
    {
        $compiled = new Serializer(new CompiledNormalizers());
        $interpreted = new Serializer(new ShapeNormalizerProvider());

        static::assertSame($expected, $compiled->serialize($compiled->deserialize($input, $class)));
        static::assertSame($expected, $interpreted->serialize($interpreted->deserialize($input, $class)));
        static::assertEquals(
            $interpreted->normalize($interpreted->deserialize($input, $class)),
            $compiled->normalize($compiled->deserialize($input, $class)),
        );
    }

    public function testHydrationBuildsTypedObjectsThroughTheConstructor(): void
    {
        foreach ([new CompiledNormalizers(), new ShapeNormalizerProvider()] as $normalizers) {
            $order = new Serializer($normalizers)->deserialize(
                '{"reference":"A","status":"shipped","lines":[{"sku":"S","quantity":3,"unitPrice":1}],'
                . '"shipping":{"street":"s","city":"c"},"priority":1,"history":["paid"]}',
                Order::class,
            );

            static::assertSame(Status::Shipped, $order->status);
            static::assertSame(Priority::Low, $order->priority);
            static::assertSame([Status::Paid], $order->history);
            static::assertInstanceOf(Address::class, $order->shipping);
            $line = $order->lines[0] ?? null;
            static::assertInstanceOf(OrderLine::class, $line);
            static::assertSame(3, $line->quantity);
            static::assertSame(1.0, $line->unitPrice);
        }
    }
}
