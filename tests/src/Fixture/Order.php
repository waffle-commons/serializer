<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/**
 * Nested DTOs, lists of DTOs, scalars and enums, nullable fields.
 */
#[Dto]
final readonly class Order
{
    /**
     * @param list<OrderLine> $lines
     * @param list<string>    $tags
     * @param list<Status>    $history
     */
    public function __construct(
        public string $reference,
        public Status $status,
        #[ListOf(OrderLine::class)]
        public array $lines,
        public ?Address $shipping = null,
        #[ListOf('string')]
        public array $tags = [],
        public ?Priority $priority = null,
        #[ListOf(Status::class)]
        public array $history = [],
        public bool $gift = false,
    ) {}
}
