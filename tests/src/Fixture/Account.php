<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * Nests a DTO whose hook throws a plain exception, without naming a field.
 */
#[Dto]
final readonly class Account
{
    public function __construct(
        public Customer $owner,
    ) {}
}
