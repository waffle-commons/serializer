<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * Accepts a field but exposes no readable property: it serializes as `{}`.
 */
#[Dto]
final readonly class Marker
{
    public function __construct(
        private string $code,
    ) {}

    public function code(): string
    {
        return $this->code;
    }
}
