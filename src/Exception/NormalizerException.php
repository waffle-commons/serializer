<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Exception;

use JsonException;
use LogicException;
use ReflectionException;
use Waffle\Commons\Contracts\Serializer\Exception\SerializerExceptionInterface;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function get_debug_type;
use function sprintf;

/**
 * The serializer cannot handle a class: no normalizer exists for it, a
 * normalizer was handed an object of another class, or the class is a `#[Dto]`
 * the serializer cannot represent.
 *
 * These are wiring or DTO-design mistakes, never client errors: it is a
 * `LogicException`, which the error handler renders as a `500`.
 */
final class NormalizerException extends LogicException implements SerializerExceptionInterface
{
    public static function missing(string $class): self
    {
        return new self(sprintf(
            'No normalizer for "%s": it must be a #[Dto] class, compiled by serializer:compile.',
            $class,
        ));
    }

    public static function mismatch(string $expected, object $given): self
    {
        return new self(sprintf('The normalizer for "%s" was given a "%s".', $expected, get_debug_type($given)));
    }

    public static function unsupported(UnsupportedDtoShapeException $previous): self
    {
        return new self($previous->getMessage(), 0, $previous);
    }

    public static function unbuildable(string $class, ReflectionException $previous): self
    {
        return new self(sprintf('Cannot instantiate "%s": %s', $class, $previous->getMessage()), 0, $previous);
    }

    public static function unencodable(string $class, JsonException $previous): self
    {
        return new self(sprintf('Cannot encode "%s" as JSON: %s', $class, $previous->getMessage()), 0, $previous);
    }
}
