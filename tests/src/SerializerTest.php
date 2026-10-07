<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Waffle\Commons\Serializer\Exception\MalformedPayloadException;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Serializer\Exception\PayloadTooLargeException;
use Waffle\Commons\Serializer\Json\JsonCodec;
use Waffle\Commons\Serializer\Normalizer\DtoNormalizerInterface;
use Waffle\Commons\Serializer\Normalizer\NormalizerProviderInterface;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use Waffle\Commons\Serializer\Serializer;
use WaffleTests\Commons\Serializer\Compiled\CompiledNormalizers;
use WaffleTests\Commons\Serializer\Fixture\Address;
use WaffleTests\Commons\Serializer\Fixture\Blank;
use WaffleTests\Commons\Serializer\Fixture\Marker;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\Status;

#[CoversClass(Serializer::class)]
#[CoversClass(NormalizerException::class)]
final class SerializerTest extends AbstractTestCase
{
    public function testSupportsTheDtosItHasANormalizerFor(): void
    {
        foreach ([new CompiledNormalizers(), new ShapeNormalizerProvider()] as $normalizers) {
            $serializer = new Serializer($normalizers);

            static::assertTrue($serializer->supports(Order::class));
            static::assertFalse($serializer->supports(stdClass::class));
            static::assertFalse($serializer->supports(Status::class));
            static::assertFalse($serializer->supports('WaffleTests\Commons\Serializer\Fixture\Missing'));
        }
    }

    public function testAClassWithoutANormalizerIsALogicError(): void
    {
        $serializer = new Serializer(new CompiledNormalizers());

        try {
            $serializer->normalize(new stdClass());
            static::fail('stdClass was normalized.');
        } catch (NormalizerException $exception) {
            static::assertStringContainsString('No normalizer for "stdClass"', $exception->getMessage());
        }

        $this->expectException(NormalizerException::class);
        $serializer->deserialize('{}', stdClass::class);
    }

    public function testADtoWithoutReadablePropertiesSerializesAsAnEmptyObject(): void
    {
        foreach ([new CompiledNormalizers(), new ShapeNormalizerProvider()] as $normalizers) {
            $serializer = new Serializer($normalizers);

            static::assertSame('{}', $serializer->serialize(new Marker('m')));
            static::assertSame('{}', $serializer->serialize(new Blank()));
            static::assertSame([], $serializer->normalize(new Marker('m')));
        }
    }

    public function testDenormalizesADecodedArray(): void
    {
        $address = new Serializer(new CompiledNormalizers())->denormalize([
            'street' => 's',
            'city' => 'c',
            'zip' => '59000',
        ], Address::class);

        static::assertSame('59000', $address->zip);
    }

    public function testTheCodecBoundsTheBody(): void
    {
        $serializer = new Serializer(new CompiledNormalizers(), new JsonCodec(maxBytes: 16, maxDepth: 2));

        try {
            $serializer->deserialize('{"street":"s","city":"c"}', Address::class);
            static::fail('The body was decoded.');
        } catch (PayloadTooLargeException $exception) {
            static::assertSame(16, $exception->limit);
        }

        $this->expectException(MalformedPayloadException::class);
        $serializer->deserialize('{"a":{"b":{}}}', Address::class);
    }

    public function testANormalizerThatBuildsAnotherClassIsALogicError(): void
    {
        $normalizers = new class() implements NormalizerProviderInterface {
            #[\Override]
            public function get(string $class): DtoNormalizerInterface
            {
                return new class() implements DtoNormalizerInterface {
                    #[\Override]
                    public function normalize(object $dto): array
                    {
                        return [];
                    }

                    #[\Override]
                    public function denormalize(array $data, string $path = ''): object
                    {
                        return new stdClass();
                    }
                };
            }
        };

        $this->expectException(NormalizerException::class);
        $this->expectExceptionMessage('The normalizer for "' . Address::class . '" was given a "stdClass".');

        new Serializer($normalizers)->denormalize([], Address::class);
    }
}
