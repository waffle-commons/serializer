<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Normalizer\Payload;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizer;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use Waffle\Commons\Serializer\Serializer;
use WaffleTests\Commons\Serializer\Compiled\CompiledNormalizers;
use WaffleTests\Commons\Serializer\Fixture\Account;
use WaffleTests\Commons\Serializer\Fixture\Customer;
use WaffleTests\Commons\Serializer\Fixture\Defaults;
use WaffleTests\Commons\Serializer\Fixture\Envelope;
use WaffleTests\Commons\Serializer\Fixture\Measures;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\TreeNode;

/**
 * Every invalid payload is a 422 with the same message and the same field path,
 * whether the compiled or the interpreted normalizers check it.
 */
#[CoversClass(Serializer::class)]
#[CoversClass(ShapeNormalizer::class)]
#[CoversClass(ShapeNormalizerProvider::class)]
#[CoversClass(Payload::class)]
#[CoversClass(InvalidPayloadException::class)]
final class RejectionTest extends AbstractTestCase
{
    private const string LINES = '"lines":[]';

    /**
     * @return iterable<string, array{class-string, string, string, string|null}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'a missing field' => [
            Order::class,
            '{"status":"paid",' . self::LINES . '}',
            'Missing required field "reference".',
            'reference',
        ];
        yield 'a mistyped field' => [
            Order::class,
            '{"reference":1,"status":"paid",' . self::LINES . '}',
            'Field "reference" must be of type string, int given.',
            'reference',
        ];
        yield 'null for a non-nullable field' => [
            Order::class,
            '{"reference":null,"status":"paid",' . self::LINES . '}',
            'Field "reference" must be of type string, null given.',
            'reference',
        ];
        yield 'an unknown string case' => [
            Order::class,
            '{"reference":"r","status":"lost",' . self::LINES . '}',
            'Field "status" must be one of "draft", "paid", "shipped".',
            'status',
        ];
        yield 'a mistyped enum value' => [
            Order::class,
            '{"reference":"r","status":1,' . self::LINES . '}',
            'Field "status" must be of type string, int given.',
            'status',
        ];
        yield 'an unknown int case' => [
            Order::class,
            '{"reference":"r","status":"paid","priority":9,' . self::LINES . '}',
            'Field "priority" must be one of 1, 2.',
            'priority',
        ];
        yield 'an object for a list' => [
            Order::class,
            '{"reference":"r","status":"paid","lines":{"a":1}}',
            'Field "lines" must be of type list, object given.',
            'lines',
        ];
        yield 'a scalar for a listed object' => [
            Order::class,
            '{"reference":"r","status":"paid","lines":[1]}',
            'Field "lines[0]" must be of type object, int given.',
            'lines[0]',
        ];
        yield 'a missing field in a listed object' => [
            Order::class,
            '{"reference":"r","status":"paid","lines":[{"sku":"x","unitPrice":1}]}',
            'Missing required field "lines[0].quantity".',
            'lines[0].quantity',
        ];
        yield 'a hook rejection in a listed object' => [
            Order::class,
            '{"reference":"r","status":"paid","lines":[{"sku":"x","quantity":1,"unitPrice":1},'
                . '{"sku":"y","quantity":0,"unitPrice":1}]}',
            'A line holds at least one item.',
            'lines[1].quantity',
        ];
        yield 'a scalar for an object' => [
            Order::class,
            '{"reference":"r","status":"paid","shipping":"home",' . self::LINES . '}',
            'Field "shipping" must be of type object, string given.',
            'shipping',
        ];
        yield 'a mistyped nested field' => [
            Order::class,
            '{"reference":"r","status":"paid","shipping":{"street":"s","city":5},' . self::LINES . '}',
            'Field "shipping.city" must be of type string, int given.',
            'shipping.city',
        ];
        yield 'a mistyped list element' => [
            Order::class,
            '{"reference":"r","status":"paid","tags":["a",2],' . self::LINES . '}',
            'Field "tags[1]" must be of type string, int given.',
            'tags[1]',
        ];
        yield 'an unknown case in a list' => [
            Order::class,
            '{"reference":"r","status":"paid","history":["draft","lost"],' . self::LINES . '}',
            'Field "history[1]" must be one of "draft", "paid", "shipped".',
            'history[1]',
        ];
        yield 'a mistyped bool' => [
            Order::class,
            '{"reference":"r","status":"paid","gift":"yes",' . self::LINES . '}',
            'Field "gift" must be of type bool, string given.',
            'gift',
        ];
        yield 'a plain hook exception at the root' => [
            Customer::class,
            '{"id":"c","email":"nope","password":"p"}',
            'The email address is invalid.',
            null,
        ];
        yield 'a plain hook exception in a nested object' => [
            Account::class,
            '{"owner":{"id":"c","email":"nope","password":"p"}}',
            'The email address is invalid.',
            'owner',
        ];
        yield 'a float for an int element' => [
            Measures::class,
            '{"counts":[1.5],"ratios":[],"flags":[],"priorities":[]}',
            'Field "counts[0]" must be of type int, float given.',
            'counts[0]',
        ];
        yield 'a string for a float element' => [
            Measures::class,
            '{"counts":[],"ratios":["x"],"flags":[],"priorities":[]}',
            'Field "ratios[0]" must be of type float, string given.',
            'ratios[0]',
        ];
        yield 'an int for a bool element' => [
            Measures::class,
            '{"counts":[],"ratios":[],"flags":[1],"priorities":[]}',
            'Field "flags[0]" must be of type bool, int given.',
            'flags[0]',
        ];
        yield 'an unknown int case in a list' => [
            Measures::class,
            '{"counts":[],"ratios":[],"flags":[],"priorities":[3]}',
            'Field "priorities[0]" must be one of 1, 2.',
            'priorities[0]',
        ];
        yield 'a string for an int-backed case' => [
            Measures::class,
            '{"counts":[],"ratios":[],"flags":[],"priorities":["1"]}',
            'Field "priorities[0]" must be of type int, string given.',
            'priorities[0]',
        ];
        yield 'a scalar for a nullable list' => [
            Measures::class,
            '{"counts":[],"ratios":[],"flags":[],"priorities":[],"notes":"x"}',
            'Field "notes" must be of type list, string given.',
            'notes',
        ];
        yield 'deep in a tree' => [
            TreeNode::class,
            '{"label":"root","children":[{"label":"a","children":[{}]}]}',
            'Missing required field "children[0].children[0].label".',
            'children[0].children[0].label',
        ];
        yield 'a mistyped nullable field with a default' => [
            Defaults::class,
            '{"nickname":5}',
            'Field "nickname" must be of type string, int given.',
            'nickname',
        ];
        yield 'an unknown case for a nullable field with a default' => [
            Defaults::class,
            '{"status":"lost"}',
            'Field "status" must be one of "draft", "paid", "shipped".',
            'status',
        ];
        yield 'a missing field in an empty object' => [
            Envelope::class,
            '{"marker":[]}',
            'Missing required field "marker.code".',
            'marker.code',
        ];
        yield 'null in a list of objects' => [
            Envelope::class,
            '{"marker":{"code":"m"},"markers":[null]}',
            'Field "markers[0]" must be of type object, null given.',
            'markers[0]',
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('invalidPayloads')]
    public function testBothNormalizersRejectWithTheSameMessageAndField(
        string $class,
        string $json,
        string $message,
        ?string $field,
    ): void {
        foreach ([new CompiledNormalizers(), new ShapeNormalizerProvider()] as $normalizers) {
            try {
                new Serializer($normalizers)->deserialize($json, $class);
                static::fail(sprintf('%s accepted the payload.', $normalizers::class));
            } catch (InvalidPayloadException $exception) {
                static::assertSame($message, $exception->getMessage(), $normalizers::class);
                static::assertSame($field, $exception->getField(), $normalizers::class);
            }
        }
    }
}
