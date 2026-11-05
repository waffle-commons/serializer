<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use stdClass;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\NormalizerException;

/**
 * Converts one `#[Dto]` class to and from its JSON-ready form.
 *
 * Two kinds implement it, and they must agree byte for byte: the per-DTO
 * classes that `NormalizerCompiler` generates at build time (reflection-free),
 * and {@see ShapeNormalizer}, which interprets the DTO shape at run time.
 */
interface DtoNormalizerInterface
{
    /**
     * The DTO's readable properties, by name, as JSON-ready values: a backed
     * enum becomes its backing value, a nested DTO its own normalized form.
     *
     * @return array<string, int|float|string|bool|array|stdClass|null>
     *
     * @throws NormalizerException When the object is not of this normalizer's class.
     */
    public function normalize(object $dto): array;

    /**
     * Builds the DTO from a decoded JSON object, through its constructor, so its
     * `set` hooks run.
     *
     * @param array  $data The decoded JSON object.
     * @param string $path Where `$data` sits in the payload, for error messages.
     *
     * @throws InvalidPayloadException When a field is missing, mistyped or rejected by the DTO.
     * @throws NormalizerException     When a nested DTO has no normalizer.
     */
    public function denormalize(array $data, string $path = ''): object;
}
