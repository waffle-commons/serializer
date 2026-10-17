<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Waffle\Commons\Serializer\Normalizer\CompiledNormalizerLoader;
use Waffle\Commons\Serializer\Normalizer\FallbackNormalizerProvider;
use Waffle\Commons\Serializer\Normalizer\ShapeNormalizerProvider;
use WaffleTests\Commons\Serializer\AbstractTestCase as TestCase;
use WaffleTests\Commons\Serializer\Compiled\WaffleTestsCommonsSerializerFixtureOrderNormalizer;
use WaffleTests\Commons\Serializer\Fixture\Fixtures;
use WaffleTests\Commons\Serializer\Fixture\Late;
use WaffleTests\Commons\Serializer\Fixture\Order;

use function putenv;

#[CoversClass(CompiledNormalizerLoader::class)]
final class CompiledNormalizerLoaderTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../../fixtures/aot';

    #[\Override]
    protected function tearDown(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG);
        parent::tearDown();
    }

    public function testWithoutTheFlagTheInterpretedProviderServes(): void
    {
        $interpreted = new ShapeNormalizerProvider();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $loader = new CompiledNormalizerLoader(
            Fixtures::GOLDEN_DIRECTORY,
            Fixtures::COMPILED_NAMESPACE,
            logger: $logger,
            interpreted: $interpreted,
        );

        static::assertSame($interpreted, $loader->load());
    }

    public function testAnOffValueKeepsTheFlagOff(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=0');
        $interpreted = new ShapeNormalizerProvider();

        static::assertSame(
            $interpreted,
            new CompiledNormalizerLoader(
                Fixtures::GOLDEN_DIRECTORY,
                Fixtures::COMPILED_NAMESPACE,
                interpreted: $interpreted,
            )->load(),
        );
    }

    public function testWithTheFlagTheCompiledNormalizersServeBackedByTheInterpretedOnes(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=1');
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('NOT validated against the current code'));

        $provider = new CompiledNormalizerLoader(
            Fixtures::GOLDEN_DIRECTORY,
            Fixtures::COMPILED_NAMESPACE,
            logger: $logger,
        )->load();

        static::assertInstanceOf(FallbackNormalizerProvider::class, $provider);
        static::assertInstanceOf(
            WaffleTestsCommonsSerializerFixtureOrderNormalizer::class,
            $provider->get(Order::class),
        );
        static::assertNotNull($provider->get(Late::class), 'A DTO the build did not cover is still served.');
    }

    public function testWithTheFlagButNoCompiledFilesTheInterpretedProviderServes(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=true');
        $interpreted = new ShapeNormalizerProvider();
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('no compiled normalizers were found'));

        $loader = new CompiledNormalizerLoader(self::FIXTURES . '/missing', logger: $logger, interpreted: $interpreted);

        static::assertSame($interpreted, $loader->load());
    }

    public function testAnIndexThatIsNotAProviderFallsBack(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=on');
        $interpreted = new ShapeNormalizerProvider();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('is not a normalizer provider'));

        $loader = new CompiledNormalizerLoader(
            self::FIXTURES . '/not-a-provider',
            'WaffleTests\\Commons\\Serializer\\Aot\\NotAProvider',
            logger: $logger,
            interpreted: $interpreted,
        );

        static::assertSame($interpreted, $loader->load());
    }

    public function testAnIndexInAnotherNamespaceFallsBack(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=yes');
        $interpreted = new ShapeNormalizerProvider();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('is missing'));

        $loader = new CompiledNormalizerLoader(
            Fixtures::GOLDEN_DIRECTORY,
            'App\\Elsewhere',
            logger: $logger,
            interpreted: $interpreted,
        );

        static::assertSame($interpreted, $loader->load());
    }

    public function testABuildThatThrowsWhenLoadedFallsBack(): void
    {
        putenv(CompiledNormalizerLoader::ENV_FLAG . '=1');
        $interpreted = new ShapeNormalizerProvider();
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to load the compiled normalizers'));

        $loader = new CompiledNormalizerLoader(
            self::FIXTURES . '/throwing',
            logger: $logger,
            interpreted: $interpreted,
        );

        static::assertSame($interpreted, $loader->load());
    }
}
