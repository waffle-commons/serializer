<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use ReflectionClass;
use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Utils\Dto\Shape\DtoShapeReader;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function class_exists;
use function enum_exists;

/**
 * Hands out a {@see ShapeNormalizer} for any `#[Dto]` class, reading its shape
 * on demand: the development path, with no build step.
 *
 * It caches nothing, so each lookup reflects on the class again. That is fine in
 * development, and nothing survives a worker request. In production, compile the
 * normalizers with `serializer:compile` and serve the generated index instead.
 */
final readonly class ShapeNormalizerProvider implements NormalizerProviderInterface
{
    public function __construct(
        private DtoShapeReader $reader = new DtoShapeReader(),
    ) {}

    /**
     * @throws NormalizerException When the class is a `#[Dto]` the serializer cannot represent.
     */
    #[\Override]
    public function get(string $class): ?DtoNormalizerInterface
    {
        if (
            !class_exists($class)
            || enum_exists($class)
            || new ReflectionClass($class)->getAttributes(Dto::class) === []
        ) {
            return null;
        }

        try {
            return new ShapeNormalizer($this->reader->read($class), $this);
        } catch (UnsupportedDtoShapeException $exception) {
            throw NormalizerException::unsupported($exception);
        }
    }
}
