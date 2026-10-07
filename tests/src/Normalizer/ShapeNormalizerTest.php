<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Serializer\Normalizer\DtoNormalizerInterface;
use Waffle\Commons\Serializer\Normalizer\NormalizerProviderInterface;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizer;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use Waffle\Commons\Utils\Dto\Shape\DtoShape;
use Waffle\Commons\Utils\Dto\Shape\DtoShapeReader;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;
use WaffleTests\Commons\Serializer\AbstractTestCase;
use WaffleTests\Commons\Serializer\Fixture\Address;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\OrderLine;
use WaffleTests\Commons\Serializer\Fixture\Sealed;
use WaffleTests\Commons\Serializer\Fixture\Status;

#[CoversClass(ShapeNormalizer::class)]
#[CoversClass(ShapeNormalizerProvider::class)]
#[CoversClass(NormalizerException::class)]
final class ShapeNormalizerTest extends AbstractTestCase
{
    public function testTheProviderHandsOutANormalizerForADtoOnly(): void
    {
        $provider = new ShapeNormalizerProvider();

        static::assertInstanceOf(ShapeNormalizer::class, $provider->get(Address::class));
        static::assertNull($provider->get(stdClass::class));
        static::assertNull($provider->get(Status::class));
        static::assertNull($provider->get('WaffleTests\Commons\Serializer\Fixture\Missing'));
    }

    public function testADtoTheSerializerCannotRepresentIsALogicError(): void
    {
        $dto = new
            #[Dto]
            class([]) {
                /**
                 * @param list<int> $values
                 */
                public function __construct(
                    public array $values,
                ) {}
            };

        try {
            new ShapeNormalizerProvider()->get($dto::class);
            static::fail('The DTO was accepted.');
        } catch (NormalizerException $exception) {
            static::assertInstanceOf(UnsupportedDtoShapeException::class, $exception->getPrevious());
            static::assertStringContainsString('is an array without #[ListOf]', $exception->getMessage());
        }
    }

    public function testANormalizerRefusesAnObjectOfAnotherClass(): void
    {
        $normalizer = new ShapeNormalizer(new DtoShapeReader()->read(Address::class), new ShapeNormalizerProvider());

        $this->expectException(NormalizerException::class);
        $this->expectExceptionMessage('The normalizer for "' . Address::class . '" was given a "stdClass".');

        $normalizer->normalize(new stdClass());
    }

    public function testANestedDtoWithoutANormalizerIsALogicError(): void
    {
        $nothing = new class() implements NormalizerProviderInterface {
            #[\Override]
            public function get(string $class): ?DtoNormalizerInterface
            {
                return null;
            }
        };
        $normalizer = new ShapeNormalizer(new DtoShapeReader()->read(Order::class), $nothing);

        try {
            $normalizer->denormalize([
                'reference' => 'r',
                'status' => 'paid',
                'lines' => [['sku' => 's', 'quantity' => 1, 'unitPrice' => 1]],
            ]);
            static::fail('The nested DTO was built.');
        } catch (NormalizerException $exception) {
            static::assertStringContainsString(
                'No normalizer for "' . OrderLine::class . '"',
                $exception->getMessage(),
            );
        }

        $this->expectException(NormalizerException::class);
        $normalizer->normalize(new Order('r', Status::Paid, [new OrderLine('s', 1, 1.0)]));
    }

    public function testAClassReflectionCannotBuildIsALogicError(): void
    {
        $normalizer = new ShapeNormalizer(new DtoShape(Sealed::class, [], []), new ShapeNormalizerProvider());

        $this->expectException(NormalizerException::class);
        $this->expectExceptionMessage('Cannot instantiate "' . Sealed::class . '"');

        $normalizer->denormalize([]);
    }
}
