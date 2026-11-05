<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Utils\Exception\ValidationException;

/**
 * A `set` hook that rejects a value with a typed, field-aware exception.
 */
#[Dto]
final class OrderLine
{
    public function __construct(
        public private(set) string $sku,
        public private(set) int $quantity {
            set(int $value) {
                if ($value < 1) {
                    throw new ValidationException('A line holds at least one item.', 'quantity');
                }

                $this->quantity = $value;
            }
        },
        public private(set) float $unitPrice,
    ) {}
}
