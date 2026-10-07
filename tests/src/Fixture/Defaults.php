<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * Every kind of default value, including a non-null default on a nullable field.
 */
#[Dto]
final readonly class Defaults
{
    public function __construct(
        public ?string $nickname = 'anon',
        public int $count = 3,
        public float $ratio = 1.5,
        public Priority $priority = Priority::High,
        public ?Status $status = Status::Draft,
        public bool $active = true,
        public string $label = "it's",
    ) {}
}
