<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Normalizer;

use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Normalizer\Payload;
use Waffle\Commons\Utils\Exception\ValidationException;
use WaffleTests\Commons\Serializer\AbstractTestCase;
use WaffleTests\Commons\Serializer\Fixture\Priority;
use WaffleTests\Commons\Serializer\Fixture\Status;

#[CoversClass(Payload::class)]
#[CoversClass(InvalidPayloadException::class)]
final class PayloadTest extends AbstractTestCase
{
    public function testAPathJoinsFieldsWithDotsAndIndexesWithBrackets(): void
    {
        static::assertSame('email', Payload::path('', 'email'));
        static::assertSame('shipping.city', Payload::path('shipping', 'city'));
        static::assertSame('lines[2]', Payload::path('lines', 2));
        static::assertSame('lines[2].sku', Payload::path(Payload::path('lines', 2), 'sku'));
    }

    public function testEachCheckReturnsTheValueAtTheKeyWithItsType(): void
    {
        $data = [
            'count' => 7,
            'ratio' => 0.5,
            'whole' => 7,
            'name' => 'x',
            'on' => false,
            'shipping' => ['city' => 'Lille'],
            'empty' => [],
            'tags' => [1, 'a'],
        ];

        static::assertSame(7, Payload::int($data, 'count', ''));
        static::assertSame(0.5, Payload::float($data, 'ratio', ''));
        static::assertSame(7.0, Payload::float($data, 'whole', ''));
        static::assertSame('x', Payload::string($data, 'name', ''));
        static::assertFalse(Payload::bool($data, 'on', ''));
        static::assertSame(['city' => 'Lille'], Payload::object($data, 'shipping', ''));
        static::assertSame([], Payload::object($data, 'empty', ''));
        static::assertSame([1, 'a'], Payload::list($data, 'tags', ''));
        static::assertSame([7, 8], Payload::intList(['l' => [7, 8]], 'l', ''));
        static::assertSame([1.0, 2.5], Payload::floatList(['l' => [1, 2.5]], 'l', ''));
        static::assertSame(['a'], Payload::stringList(['l' => ['a']], 'l', ''));
        static::assertSame([true], Payload::boolList(['l' => [true]], 'l', ''));
    }

    /**
     * @return iterable<string, array{Closure, string, string}>
     */
    public static function mistypedValues(): iterable
    {
        yield 'int' => [
            static fn(): int => Payload::int(['count' => '7'], 'count', 'order'),
            'order.count',
            'int, string given',
        ];
        yield 'float' => [
            static fn(): float => Payload::float(['ratio' => true], 'ratio', ''),
            'ratio',
            'float, bool given',
        ];
        yield 'string' => [
            static fn(): string => Payload::string(['name' => null], 'name', ''),
            'name',
            'string, null given',
        ];
        yield 'bool' => [static fn(): bool => Payload::bool(['on' => 1.5], 'on', ''), 'on', 'bool, float given'];
        yield 'object' => [static fn(): array => Payload::object(['to' => [1]], 'to', ''), 'to', 'object, list given'];
        yield 'list' => [
            static fn(): array => Payload::list(['tags' => ['a' => 1]], 'tags', ''),
            'tags',
            'list, object given',
        ];
        yield 'int list element' => [
            static fn(): array => Payload::intList(['ids' => [1, 'x']], 'ids', ''),
            'ids[1]',
            'int, string given',
        ];
        yield 'float list element' => [
            static fn(): array => Payload::floatList(['r' => ['x']], 'r', ''),
            'r[0]',
            'float, string given',
        ];
        yield 'string list element' => [
            static fn(): array => Payload::stringList(['s' => [[]]], 's', ''),
            's[0]',
            'string, list given',
        ];
        yield 'bool list element' => [
            static fn(): array => Payload::boolList(['b' => [0]], 'b', 'a'),
            'a.b[0]',
            'bool, int given',
        ];
    }

    #[DataProvider('mistypedValues')]
    public function testAMistypedValueIsA422OnItsField(Closure $check, string $field, string $type): void
    {
        try {
            $check();
            static::fail('The value was accepted.');
        } catch (InvalidPayloadException $exception) {
            static::assertSame(sprintf('Field "%s" must be of type %s.', $field, $type), $exception->getMessage());
            static::assertSame($field, $exception->getField());
            static::assertSame(422, $exception->getCode());
        }
    }

    /**
     * @return iterable<string, array{Closure}>
     */
    public static function absentKeys(): iterable
    {
        yield 'int' => [static fn(): int => Payload::int([], 'sku', 'lines[0]')];
        yield 'float' => [static fn(): float => Payload::float([], 'sku', 'lines[0]')];
        yield 'string' => [static fn(): string => Payload::string([], 'sku', 'lines[0]')];
        yield 'bool' => [static fn(): bool => Payload::bool([], 'sku', 'lines[0]')];
        yield 'object' => [static fn(): array => Payload::object([], 'sku', 'lines[0]')];
        yield 'list' => [static fn(): array => Payload::list([], 'sku', 'lines[0]')];
    }

    #[DataProvider('absentKeys')]
    public function testAnAbsentKeyIsAMissingField(Closure $check): void
    {
        try {
            $check();
            static::fail('The absent key was accepted.');
        } catch (InvalidPayloadException $exception) {
            static::assertSame('Missing required field "lines[0].sku".', $exception->getMessage());
            static::assertSame('lines[0].sku', $exception->getField());
        }
    }

    public function testAnUnknownCaseListsTheAllowedValues(): void
    {
        try {
            Payload::unknownCase(Status::cases(), '', 'status');
        } catch (InvalidPayloadException $exception) {
            static::assertSame('Field "status" must be one of "draft", "paid", "shipped".', $exception->getMessage());
            static::assertSame('status', $exception->getField());
        }

        try {
            Payload::unknownCase(Priority::cases(), 'items', 3);
        } catch (InvalidPayloadException $exception) {
            static::assertSame('Field "items[3]" must be one of 1, 2.', $exception->getMessage());
        }
    }

    public function testARejectionIsReportedOnTheFieldTheHookNamed(): void
    {
        $hook = new ValidationException('Too small.', 'quantity');

        $nested = Payload::rejected($hook, 'lines[1]');
        static::assertSame('Too small.', $nested->getMessage());
        static::assertSame('lines[1].quantity', $nested->getField());
        static::assertSame($hook, $nested->getPrevious());

        static::assertSame('quantity', Payload::rejected($hook, '')->getField());
    }

    public function testARejectionWithoutAFieldIsReportedOnTheEnclosingObject(): void
    {
        $plain = new InvalidArgumentException('Invalid.');

        static::assertSame('owner', Payload::rejected($plain, 'owner')->getField());
        static::assertNull(Payload::rejected($plain, '')->getField());
        static::assertNull(Payload::rejected(new ValidationException('Invalid.'), '')->getField());
    }

    public function testANestedRejectionKeepsItsPath(): void
    {
        $nested = new InvalidPayloadException('Missing required field "a.b".', 'a.b');

        static::assertSame($nested, Payload::rejected($nested, 'outer'));
    }
}
