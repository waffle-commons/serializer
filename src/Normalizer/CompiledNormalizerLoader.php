<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Throwable;
use Waffle\Commons\Serializer\Compiler\NormalizerCompiler;

use function class_exists;
use function getenv;
use function glob;
use function in_array;
use function is_file;
use function is_string;
use function strtolower;

/**
 * Loads the normalizers `serializer:compile` wrote, for the `WAFFLE_AOT=1`
 * fast path (API-02; the AOT-01 pattern of RFC-019).
 *
 * With the flag on and the compiled files present, `load()` returns the compiled
 * index, backed by the interpreted provider for any DTO the last compilation did
 * not cover. In every other case — the flag off (the development default), the
 * files missing, an index that does not load — it returns the interpreted
 * provider, which reads DTO shapes by reflection, and logs why when the flag
 * asked for more. The two are interchangeable: they accept, reject and word
 * errors the same way.
 *
 * The compiled files are not checked against the current code, so a stale build
 * serves stale normalizers: every successful load says so in the log, with the
 * command to rebuild. Loading happens once, at boot.
 */
final readonly class CompiledNormalizerLoader
{
    /** Environment flag that opts a worker into the compiled fast path. */
    public const string ENV_FLAG = 'WAFFLE_AOT';

    /** Where `serializer:compile` writes by default, relative to the application root. */
    public const string DEFAULT_DIRECTORY = 'var/cache/serializer';

    /** The command to re-run after any change to a DTO. */
    public const string REBUILD_COMMAND = 'bin/waffle serializer:compile';

    public function __construct(
        private string $directory,
        private string $namespace = NormalizerCompiler::DEFAULT_NAMESPACE,
        private string $index = NormalizerCompiler::DEFAULT_INDEX,
        private LoggerInterface $logger = new NullLogger(),
        private NormalizerProviderInterface $interpreted = new ShapeNormalizerProvider(),
    ) {}

    public function load(): NormalizerProviderInterface
    {
        if (!self::enabled()) {
            return $this->interpreted;
        }

        $indexFile = $this->directory . '/' . $this->index . '.php';
        if (!is_file($indexFile)) {
            $this->logger->warning('AOT fast path enabled but no compiled normalizers were found; falling back to the interpreted serializer.', [
                'directory' => $this->directory,
            ]);

            return $this->interpreted;
        }

        try {
            $files = glob($this->directory . '/*.php');
            foreach ($files === false ? [] : $files as $file) {
                require_once $file;
            }

            $class = $this->namespace . '\\' . $this->index;
            $compiled = class_exists($class) ? new ReflectionClass($class)->newInstance() : null;
            if (!$compiled instanceof NormalizerProviderInterface) {
                $this->logger->warning('The compiled normalizer index is missing or is not a normalizer provider; falling back to the interpreted serializer.', [
                    'class' => $class,
                ]);

                return $this->interpreted;
            }
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to load the compiled normalizers; falling back to the interpreted serializer.', [
                'directory' => $this->directory,
                'error' => $exception->getMessage(),
            ]);

            return $this->interpreted;
        }

        $this->logger->warning('AOT compiled normalizers loaded — they are NOT validated against the current code; '
        . 'a stale build serves OUTDATED normalizers. Regenerate them with "'
        . self::REBUILD_COMMAND
        . '" after ANY change to a DTO.', [
            'directory' => $this->directory,
            'rebuild_command' => self::REBUILD_COMMAND,
        ]);

        return new FallbackNormalizerProvider($compiled, $this->interpreted);
    }

    /**
     * Whether the `WAFFLE_AOT` flag is set to an on value (`1`, `true`, `on`, `yes`).
     */
    private static function enabled(): bool
    {
        $value = getenv(self::ENV_FLAG);

        return is_string($value) && in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }
}
