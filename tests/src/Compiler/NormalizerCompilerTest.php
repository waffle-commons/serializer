<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Serializer\Compiler\NormalizerCompiler;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;
use WaffleTests\Commons\Serializer\AbstractTestCase;
use WaffleTests\Commons\Serializer\Fixture\Clash\A\BC;
use WaffleTests\Commons\Serializer\Fixture\Clash\AB\C;
use WaffleTests\Commons\Serializer\Fixture\Fixtures;
use WaffleTests\Commons\Serializer\Fixture\Order;
use WaffleTests\Commons\Serializer\Fixture\Sealed;

use function array_keys;
use function array_reverse;
use function array_values;
use function file_put_contents;
use function getenv;
use function scandir;
use function str_ends_with;

#[CoversClass(NormalizerCompiler::class)]
final class NormalizerCompilerTest extends AbstractTestCase
{
    /**
     * The golden files are the compiler's output for the fixture matrix; Mago
     * lints and analyzes them like hand-written code. After a deliberate change
     * to the compiler, regenerate them with `WAFFLE_UPDATE_GOLDEN=1 composer tests`.
     */
    public function testTheGeneratedFilesMatchTheGoldenFiles(): void
    {
        $files = new NormalizerCompiler()->compile(Fixtures::ROOTS, Fixtures::COMPILED_NAMESPACE);

        if (getenv('WAFFLE_UPDATE_GOLDEN') === '1') {
            foreach ($files as $name => $source) {
                file_put_contents(Fixtures::GOLDEN_DIRECTORY . '/' . $name, $source);
            }
        }

        static::assertSame($this->goldenFiles(), array_keys($files));
        foreach ($files as $name => $source) {
            static::assertStringEqualsFile(Fixtures::GOLDEN_DIRECTORY . '/' . $name, $source);
        }
    }

    public function testTheOutputDoesNotDependOnTheOrderOfTheRoots(): void
    {
        $compiler = new NormalizerCompiler();

        static::assertSame(
            $compiler->compile(Fixtures::ROOTS),
            $compiler->compile(array_values(array_reverse(Fixtures::ROOTS))),
        );
    }

    public function testTwoDtosThatFlattenToTheSameNameAreNumbered(): void
    {
        $files = new NormalizerCompiler()->compile([BC::class, C::class], 'App\\Generated');

        static::assertSame(
            [
                'CompiledNormalizers.php',
                'WaffleTestsCommonsSerializerFixtureClashABC2Normalizer.php',
                'WaffleTestsCommonsSerializerFixtureClashABCNormalizer.php',
            ],
            array_keys($files),
        );
        // Sorted by class name, `…\Clash\AB\C` comes first (`B` sorts before `\`)
        // and keeps the plain name; `…\Clash\A\BC` is numbered.
        static::assertStringContainsString(
            '\WaffleTests\Commons\Serializer\Fixture\Clash\A\BC::class => new WaffleTestsCommonsSerializerFixtureClashABC2Normalizer(),',
            $files['CompiledNormalizers.php'] ?? '',
        );
    }

    public function testNoDtoCompilesToAnEmptyIndexInTheGivenNamespace(): void
    {
        $files = new NormalizerCompiler()->compile([], 'App\\Generated', 'Normalizers');

        static::assertSame(['Normalizers.php'], array_keys($files));
        $index = $files['Normalizers.php'] ?? '';
        static::assertStringContainsString("namespace App\\Generated;\n", $index);
        static::assertStringContainsString(
            'final readonly class Normalizers implements NormalizerProviderInterface',
            $index,
        );
        static::assertStringContainsString('$this->normalizers = [];', $index);
        static::assertStringNotContainsString('use function', $index);
    }

    public function testTheDefaultNamespaceAndIndex(): void
    {
        $files = new NormalizerCompiler()->compile([Order::class]);

        static::assertStringContainsString(
            "namespace Waffle\\Generated\\Serializer;\n",
            $files['CompiledNormalizers.php'] ?? '',
        );
    }

    public function testAClassThatIsNotADtoIsRefused(): void
    {
        $this->expectException(UnsupportedDtoShapeException::class);

        new NormalizerCompiler()->compile([Sealed::class]);
    }

    /**
     * @return list<string>
     */
    private function goldenFiles(): array
    {
        $entries = scandir(Fixtures::GOLDEN_DIRECTORY);
        if ($entries === false) {
            static::fail('The golden directory cannot be read.');
        }

        $files = [];
        foreach ($entries as $entry) {
            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            $files[] = $entry;
        }

        return $files;
    }
}
