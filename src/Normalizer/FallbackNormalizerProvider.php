<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use Waffle\Commons\Serializer\Exception\NormalizerException;

/**
 * Asks a first provider, then a second for what the first does not know: the
 * compiled normalizers first, the interpreted ones for a DTO the last
 * `serializer:compile` did not cover (API-02).
 */
final readonly class FallbackNormalizerProvider implements NormalizerProviderInterface
{
    public function __construct(
        private NormalizerProviderInterface $primary,
        private NormalizerProviderInterface $fallback,
    ) {}

    /**
     * @throws NormalizerException When the class is a `#[Dto]` the serializer cannot represent.
     */
    #[\Override]
    public function get(string $class): ?DtoNormalizerInterface
    {
        return $this->primary->get($class) ?? $this->fallback->get($class);
    }
}
