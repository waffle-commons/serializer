<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture\Clash\AB;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * `A\BC` and `AB\C` flatten to the same generated name.
 */
#[Dto]
final readonly class C
{
    public function __construct(
        public string $name,
    ) {}
}
