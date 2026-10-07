<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Exception;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use stdClass;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;
use WaffleTests\Commons\Serializer\AbstractTestCase;

#[CoversClass(InvalidPayloadException::class)]
#[CoversClass(NormalizerException::class)]
final class ExceptionTest extends AbstractTestCase
{
    public function testAnInvalidPayloadIsA422ValidationFailureOnAField(): void
    {
        $previous = new LogicException('hook');
        $exception = new InvalidPayloadException('Too small.', 'lines[0].quantity', $previous);

        static::assertInstanceOf(ValidationExceptionInterface::class, $exception);
        static::assertSame(422, $exception->getCode());
        static::assertSame('lines[0].quantity', $exception->getField());
        static::assertSame($previous, $exception->getPrevious());
        static::assertNull(new InvalidPayloadException('Not an object.')->getField());
    }

    public function testANormalizerFailureIsALogicError(): void
    {
        static::assertSame(
            'No normalizer for "App\Dto\Order": it must be a #[Dto] class, compiled by serializer:compile.',
            NormalizerException::missing('App\Dto\Order')->getMessage(),
        );
        static::assertSame(
            'The normalizer for "App\Dto\Order" was given a "stdClass".',
            NormalizerException::mismatch('App\Dto\Order', new stdClass())->getMessage(),
        );

        $shape = new UnsupportedDtoShapeException('App\Dto\Order', 'property $meta', 'declares no type');
        $unsupported = NormalizerException::unsupported($shape);
        static::assertSame($shape->getMessage(), $unsupported->getMessage());
        static::assertSame($shape, $unsupported->getPrevious());

        $reflection = new ReflectionException('Access to non-public constructor.');
        $unbuildable = NormalizerException::unbuildable('App\Dto\Order', $reflection);
        static::assertInstanceOf(LogicException::class, $unbuildable);
        static::assertSame(
            'Cannot instantiate "App\Dto\Order": Access to non-public constructor.',
            $unbuildable->getMessage(),
        );
        static::assertSame($reflection, $unbuildable->getPrevious());
    }
}
