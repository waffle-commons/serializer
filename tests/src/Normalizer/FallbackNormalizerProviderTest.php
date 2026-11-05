<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Serializer\Normalizer\FallbackNormalizerProvider;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use WaffleTests\Commons\Serializer\AbstractTestCase as TestCase;
use WaffleTests\Commons\Serializer\Compiled\CompiledNormalizers;
use WaffleTests\Commons\Serializer\Compiled\WaffleTestsCommonsSerializerFixtureOrderNormalizer;
use WaffleTests\Commons\Serializer\Fixture\Late;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\Sealed;

#[CoversClass(FallbackNormalizerProvider::class)]
final class FallbackNormalizerProviderTest extends TestCase
{
    public function testThePrimaryProviderAnswersFirst(): void
    {
        $provider = new FallbackNormalizerProvider(new CompiledNormalizers(), new ShapeNormalizerProvider());

        static::assertInstanceOf(
            WaffleTestsCommonsSerializerFixtureOrderNormalizer::class,
            $provider->get(Order::class),
        );
    }

    public function testTheFallbackAnswersWhatThePrimaryDoesNotKnow(): void
    {
        $provider = new FallbackNormalizerProvider(new CompiledNormalizers(), new ShapeNormalizerProvider());

        static::assertNotNull($provider->get(Late::class));
        static::assertNull($provider->get(Sealed::class), 'A class that is not a #[Dto] has no normalizer.');
    }
}
