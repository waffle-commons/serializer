<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture\Unsupported;

use DateTimeImmutable;
use Waffle\Commons\Contracts\Attribute\Dto;

/** A date is not a shape the serializer represents yet (D3): compilation refuses it. */
#[Dto]
final readonly class Moment
{
    public function __construct(
        public DateTimeImmutable $at,
    ) {}
}
