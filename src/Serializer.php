<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer;

use JsonException;
use stdClass;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\MalformedPayloadException;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Serializer\Exception\PayloadTooLargeException;
use Waffle\Commons\Serializer\Json\JsonCodec;
use Waffle\Commons\Serializer\Normalizer\DtoNormalizerInterface;
use Waffle\Commons\Serializer\Normalizer\NormalizerProviderInterface;

/**
 * Turns `#[Dto]` objects into JSON and JSON into `#[Dto]` objects, at the HTTP
 * boundary (RFC-016, API-02).
 *
 * It is scoped on purpose: it handles the DTO shapes `DtoShapeReader` accepts,
 * nothing else. A DTO is always built through its constructor, so its `set`
 * hooks validate every value; a hook's rejection becomes a 422 that names the
 * field by its path (`lines[2].quantity`).
 *
 * The normalizers come from a {@see NormalizerProviderInterface}: the index that
 * `serializer:compile` generates (reflection-free) in production, or a
 * `ShapeNormalizerProvider` (reflection, no build step) in development.
 *
 * Stateless: safe to share across FrankenPHP worker requests.
 */
final readonly class Serializer
{
    public function __construct(
        private NormalizerProviderInterface $normalizers,
        private JsonCodec $codec = new JsonCodec(),
    ) {}

    /**
     * Whether a normalizer exists for `$class`.
     *
     * @throws NormalizerException When the class is a `#[Dto]` the serializer cannot represent.
     */
    public function supports(string $class): bool
    {
        return $this->normalizers->get($class) !== null;
    }

    /**
     * The DTO's readable properties, by name, as JSON-ready values.
     *
     * @return array<string, int|float|string|bool|array|stdClass|null>
     *
     * @throws NormalizerException When the object's class has no normalizer.
     */
    public function normalize(object $dto): array
    {
        return $this->normalizer($dto::class)->normalize($dto);
    }

    /**
     * Builds a DTO from a decoded JSON object.
     *
     * @template T of object
     *
     * @param array           $data  The decoded JSON object.
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws InvalidPayloadException When a field is missing, mistyped or rejected by the DTO.
     * @throws NormalizerException     When the class has no normalizer.
     */
    public function denormalize(array $data, string $class): object
    {
        $dto = $this->normalizer($class)->denormalize($data);
        if (!$dto instanceof $class) {
            throw NormalizerException::mismatch($class, $dto);
        }

        return $dto;
    }

    /**
     * The DTO as JSON. A DTO without readable properties is `{}`.
     *
     * @throws NormalizerException When the object's class has no normalizer.
     * @throws JsonException       When a value cannot be encoded.
     */
    public function serialize(object $dto): string
    {
        $data = $this->normalize($dto);

        return $this->codec->encode($data === [] ? new stdClass() : $data);
    }

    /**
     * Builds a DTO from a JSON request body.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws PayloadTooLargeException  When the body exceeds the codec's byte limit (413).
     * @throws MalformedPayloadException When the body is not JSON or nests too deep (400).
     * @throws InvalidPayloadException   When it is not an object or does not fit the DTO (422).
     * @throws NormalizerException       When the class has no normalizer.
     */
    public function deserialize(string $json, string $class): object
    {
        return $this->denormalize($this->codec->decodeObject($json), $class);
    }

    /**
     * @throws NormalizerException
     */
    private function normalizer(string $class): DtoNormalizerInterface
    {
        return $this->normalizers->get($class) ?? throw NormalizerException::missing($class);
    }
}
