<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use InvalidArgumentException;
use SensitiveParameter;
use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * Hooks and visibility: what goes in is not what comes out.
 */
#[Dto]
final class Customer
{
    /** Static: not instance data, never serialized. */
    public static string $schema = 'v1';

    /** Validated and normalized by a full `set` hook that throws a plain exception. */
    public private(set) string $email {
        set(string $value) {
            if (!str_contains($value, '@')) {
                throw new InvalidArgumentException('The email address is invalid.');
            }

            $this->email = mb_strtolower($value);
        }
    }

    /** Fed by the `password` parameter, under another name. */
    public private(set) string $passwordHash;

    /** Computed: a virtual property with a `get` hook. */
    public string $display {
        get => $this->nickname ?? $this->email;
    }

    /** Write-only: a virtual property with only a `set` hook. */
    public string $alias {
        set(string $value) {
            $this->nickname = $value;
        }
    }

    public function __construct(
        public readonly string $id,
        string $email,
        #[SensitiveParameter]
        string $password,
        public protected(set) ?string $nickname = null,
    ) {
        $this->email = $email;
        $this->passwordHash = hash('sha256', $password);
    }
}
