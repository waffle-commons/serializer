<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/** A DTO no compilation covered: the interpreted provider serves it. */
#[Dto]
final readonly class Late
{
    public function __construct(
        public string $note,
    ) {}
}
