<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use Waffle\Commons\Serializer\Exception\NormalizerException;

/**
 * Hands out the normalizer of a DTO class.
 *
 * The compiled index that `NormalizerCompiler` generates implements it with a
 * fixed map; {@see ShapeNormalizerProvider} implements it by reading the DTO
 * shape on demand.
 */
interface NormalizerProviderInterface
{
    /**
     * The normalizer for `$class`, or null when the class is not a DTO this
     * provider knows.
     *
     * @throws NormalizerException When the class is a `#[Dto]` the serializer cannot represent.
     */
    public function get(string $class): ?DtoNormalizerInterface;
}
