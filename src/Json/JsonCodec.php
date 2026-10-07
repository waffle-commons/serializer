<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Json;

use JsonException;
use stdClass;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\MalformedPayloadException;
use Waffle\Commons\Serializer\Exception\PayloadTooLargeException;

use function array_is_list;
use function is_array;
use function json_decode;
use function json_encode;
use function strlen;

/**
 * Turns a request body into a decoded JSON object, within bounds, and a
 * normalized DTO back into JSON.
 *
 * Decoding is bounded before any parsing happens: a body longer than
 * `$maxBytes` is refused without being parsed (413), and nesting deeper than
 * `$maxDepth` is a parse error (400), like malformed JSON. A well-formed body that is not a JSON
 * object is a 422: a DTO is always an object.
 *
 * Encoding keeps a float's fraction (`2.0` stays `2.0`), and writes Unicode and
 * slashes unescaped: the output is UTF-8 JSON.
 */
final readonly class JsonCodec
{
    public const int DEFAULT_MAX_BYTES = 1_048_576;
    public const int DEFAULT_MAX_DEPTH = 64;

    /**
     * @param positive-int $maxBytes The longest body decoded, in bytes.
     * @param positive-int $maxDepth The deepest nesting decoded.
     */
    public function __construct(
        public private(set) int $maxBytes = self::DEFAULT_MAX_BYTES,
        public private(set) int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {}

    /**
     * Decodes a JSON object into an associative array.
     *
     * @throws PayloadTooLargeException  When the body is longer than `$maxBytes`.
     * @throws MalformedPayloadException When it is not JSON, or nests deeper than `$maxDepth`.
     * @throws InvalidPayloadException   When it is JSON but not an object.
     */
    public function decodeObject(string $json): array
    {
        if (strlen($json) > $this->maxBytes) {
            throw new PayloadTooLargeException($this->maxBytes);
        }

        try {
            // json_decode() returns its result untyped: it is kept in a one-element
            // list and narrowed in place, never bound on its own.
            $decoded = [json_decode($json, true, $this->maxDepth, JSON_THROW_ON_ERROR)];
        } catch (JsonException $exception) {
            throw new MalformedPayloadException($exception);
        }

        // `{}` decodes to `[]`: an empty array is the empty object.
        if (!is_array($decoded[0]) || $decoded[0] !== [] && array_is_list($decoded[0])) {
            throw new InvalidPayloadException('The payload must be a JSON object.');
        }

        return $decoded[0];
    }

    /**
     * @param array|stdClass $value A normalized DTO.
     *
     * @throws JsonException When a value cannot be encoded (a string that is not UTF-8).
     */
    public function encode(array|stdClass $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
