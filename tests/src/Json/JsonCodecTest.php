<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Json;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\MalformedPayloadException;
use Waffle\Commons\Serializer\Exception\PayloadTooLargeException;
use Waffle\Commons\Serializer\Json\JsonCodec;
use WaffleTests\Commons\Serializer\AbstractTestCase;

#[CoversClass(JsonCodec::class)]
#[CoversClass(MalformedPayloadException::class)]
#[CoversClass(PayloadTooLargeException::class)]
#[CoversClass(InvalidPayloadException::class)]
final class JsonCodecTest extends AbstractTestCase
{
    public function testDecodesAnObject(): void
    {
        $codec = new JsonCodec();

        static::assertSame(['a' => 1, 'b' => ['c' => true]], $codec->decodeObject('{"a": 1, "b": {"c": true}}'));
        static::assertSame([], $codec->decodeObject(' {} '));
        static::assertSame(JsonCodec::DEFAULT_MAX_BYTES, $codec->maxBytes);
        static::assertSame(JsonCodec::DEFAULT_MAX_DEPTH, $codec->maxDepth);
    }

    public function testABodyAtTheByteLimitIsDecodedAndOneByteMoreIsNot(): void
    {
        $codec = new JsonCodec(maxBytes: 9);

        static::assertSame(['a' => 12], $codec->decodeObject('{"a": 12}'));

        try {
            $codec->decodeObject('{"a": 123}');
            static::fail('The body was decoded.');
        } catch (PayloadTooLargeException $exception) {
            static::assertSame(413, $exception->getCode());
            static::assertSame(9, $exception->limit);
            static::assertSame('The payload is larger than 9 bytes.', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'truncated' => ['{"a": '];
        yield 'empty' => [''];
        yield 'not UTF-8' => ["{\"a\": \"\xB1\"}"];
        yield 'too deep' => ['{"a": {"b": {"c": 1}}}'];
    }

    #[DataProvider('malformedBodies')]
    public function testAMalformedOrTooDeepBodyIsA400(string $body): void
    {
        try {
            new JsonCodec(maxDepth: 3)->decodeObject($body);
            static::fail('The body was decoded.');
        } catch (MalformedPayloadException $exception) {
            static::assertSame(400, $exception->getCode());
            static::assertSame('The payload is not valid JSON.', $exception->getMessage());
            static::assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonObjects(): iterable
    {
        yield 'a list' => ['[1, 2]'];
        yield 'a string' => ['"text"'];
        yield 'a number' => ['42'];
        yield 'null' => ['null'];
    }

    #[DataProvider('nonObjects')]
    public function testJsonThatIsNotAnObjectIsA422(string $body): void
    {
        try {
            new JsonCodec()->decodeObject($body);
            static::fail('The body was decoded.');
        } catch (InvalidPayloadException $exception) {
            static::assertSame('The payload must be a JSON object.', $exception->getMessage());
            static::assertNull($exception->getField());
        }
    }

    public function testEncodesUtf8WithItsFractionsAndSlashesKept(): void
    {
        $codec = new JsonCodec();

        static::assertSame('{"city":"Montréal/Nord","price":2.0,"list":[1]}', $codec->encode([
            'city' => 'Montréal/Nord',
            'price' => 2.0,
            'list' => [1],
        ]));
        static::assertSame('{}', $codec->encode(new stdClass()));
    }

    public function testAStringThatIsNotUtf8CannotBeEncoded(): void
    {
        $this->expectException(JsonException::class);

        new JsonCodec()->encode(['name' => "\xB1"]);
    }
}
