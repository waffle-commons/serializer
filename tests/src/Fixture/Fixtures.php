<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use WaffleTests\Commons\Serializer\Fixture\Clash\A\BC;
use WaffleTests\Commons\Serializer\Fixture\Clash\AB\C;

/**
 * The DTO matrix the compiled normalizers are generated from.
 *
 * The golden files in `tests/compiled/` are the compiler's output for these
 * roots. After a deliberate change to the compiler, regenerate them with
 * `WAFFLE_UPDATE_GOLDEN=1 composer tests`, then review the diff.
 */
final class Fixtures
{
    public const string COMPILED_NAMESPACE = 'WaffleTests\\Commons\\Serializer\\Compiled';
    public const string GOLDEN_DIRECTORY = __DIR__ . '/../../compiled';

    /** @var list<class-string> */
    public const array ROOTS = [
        Order::class,
        Customer::class,
        Account::class,
        Contact::class,
        TreeNode::class,
        Envelope::class,
        Defaults::class,
        Measures::class,
        Blank::class,
        BC::class,
        C::class,
    ];
}
