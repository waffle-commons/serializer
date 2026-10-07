<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * A nullable parameter without a default: an absent key is `null`.
 */
#[Dto]
final readonly class Contact
{
    public function __construct(
        public string $name,
        public ?string $phone,
    ) {}
}
