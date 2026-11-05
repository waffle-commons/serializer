<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use BackedEnum;
use InvalidArgumentException;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * The checks every normalizer runs on decoded JSON, compiled or interpreted.
 *
 * `json_decode()` hands values back untyped, so a value never travels on its
 * own: each method takes the decoded object or list (`$data`) and a key, and
 * narrows `$data[$key]` in place to the type a DTO constructor expects. It
 * throws an {@see InvalidPayloadException} (a 422) that names the field by its
 * path from the payload root: an absent key is a missing field, any other
 * mismatch a mistyped one. Compiled and interpreted normalizers call the same
 * methods, so they accept, reject and word their errors identically.
 *
 * `$path` is the path of `$data` itself (`''` at the root). A scalar's path is
 * built only when its check fails; a nested object's or a list's path is built
 * as it is entered.
 *
 * Pure functions: no state.
 */
final readonly class Payload
{
    /**
     * The path of `$key` inside the object or the list at `$path`: `email`,
     * `shipping.city`, `lines[2]`.
     */
    public static function path(string $path, string|int $key): string
    {
        if (is_int($key)) {
            return sprintf('%s[%d]', $path, $key);
        }

        return $path === '' ? $key : $path . '.' . $key;
    }

    /**
     * @throws InvalidPayloadException Always: the payload lacks a required field.
     */
    public static function missing(string $path, string|int $key): never
    {
        $field = self::path($path, $key);

        throw new InvalidPayloadException(sprintf('Missing required field "%s".', $field), $field);
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function int(array $data, string|int $key, string $path): int
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (!is_int($data[$key])) {
            throw self::mistyped($data, $key, 'int', $path);
        }

        return $data[$key];
    }

    /**
     * An integer is accepted: JSON does not tell `1` from `1.0`.
     *
     * @throws InvalidPayloadException
     */
    public static function float(array $data, string|int $key, string $path): float
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (is_int($data[$key])) {
            return (float) $data[$key];
        }

        if (!is_float($data[$key])) {
            throw self::mistyped($data, $key, 'float', $path);
        }

        return $data[$key];
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function string(array $data, string|int $key, string $path): string
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (!is_string($data[$key])) {
            throw self::mistyped($data, $key, 'string', $path);
        }

        return $data[$key];
    }

    /**
     * @throws InvalidPayloadException
     */
    public static function bool(array $data, string|int $key, string $path): bool
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (!is_bool($data[$key])) {
            throw self::mistyped($data, $key, 'bool', $path);
        }

        return $data[$key];
    }

    /**
     * A JSON object, decoded as an associative array. `{}` decodes to `[]`, so
     * an empty array is accepted as an empty object.
     *
     * @throws InvalidPayloadException
     */
    public static function object(array $data, string|int $key, string $path): array
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (!is_array($data[$key]) || $data[$key] !== [] && array_is_list($data[$key])) {
            throw self::mistyped($data, $key, 'object', $path);
        }

        return $data[$key];
    }

    /**
     * A JSON array: the list itself, for its elements to be checked in turn.
     *
     * @throws InvalidPayloadException
     */
    public static function list(array $data, string|int $key, string $path): array
    {
        if (!array_key_exists($key, $data)) {
            self::missing($path, $key);
        }

        if (!is_array($data[$key]) || !array_is_list($data[$key])) {
            throw self::mistyped($data, $key, 'list', $path);
        }

        return $data[$key];
    }

    /**
     * @return list<int>
     *
     * @throws InvalidPayloadException
     */
    public static function intList(array $data, string $key, string $path): array
    {
        $items = self::list($data, $key, $path);
        $field = self::path($path, $key);

        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = self::int($items, $index, $field);
        }

        return $list;
    }

    /**
     * @return list<float>
     *
     * @throws InvalidPayloadException
     */
    public static function floatList(array $data, string $key, string $path): array
    {
        $items = self::list($data, $key, $path);
        $field = self::path($path, $key);

        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = self::float($items, $index, $field);
        }

        return $list;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidPayloadException
     */
    public static function stringList(array $data, string $key, string $path): array
    {
        $items = self::list($data, $key, $path);
        $field = self::path($path, $key);

        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = self::string($items, $index, $field);
        }

        return $list;
    }

    /**
     * @return list<bool>
     *
     * @throws InvalidPayloadException
     */
    public static function boolList(array $data, string $key, string $path): array
    {
        $items = self::list($data, $key, $path);
        $field = self::path($path, $key);

        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = self::bool($items, $index, $field);
        }

        return $list;
    }

    /**
     * @param list<BackedEnum> $cases The enum's cases, for the message.
     *
     * @throws InvalidPayloadException Always: the value is not one of the cases.
     */
    public static function unknownCase(array $cases, string $path, string|int $key): never
    {
        $field = self::path($path, $key);
        $values = array_map(static fn(BackedEnum $case): string => is_string($case->value)
            ? '"' . $case->value . '"'
            : (string) $case->value, $cases);

        throw new InvalidPayloadException(
            sprintf('Field "%s" must be one of %s.', $field, implode(', ', $values)),
            $field,
        );
    }

    /**
     * Turns what a constructor or a `set` hook threw into a 422 at the right
     * path.
     *
     * A field the hook named is resolved against `$path`; a hook that named no
     * field is reported on the object at `$path` (or on no field at the root).
     * An {@see InvalidPayloadException} comes from a nested normalizer and
     * already carries its full path: it is returned unchanged.
     */
    public static function rejected(
        ValidationExceptionInterface|InvalidArgumentException $exception,
        string $path,
    ): InvalidPayloadException {
        if ($exception instanceof InvalidPayloadException) {
            return $exception;
        }

        $field = $exception instanceof ValidationExceptionInterface ? $exception->getField() : null;

        return new InvalidPayloadException(
            $exception->getMessage(),
            match (true) {
                $field !== null => self::path($path, $field),
                $path !== '' => $path,
                default => null,
            },
            $exception,
        );
    }

    private static function mistyped(
        array $data,
        string|int $key,
        string $expected,
        string $path,
    ): InvalidPayloadException {
        $field = self::path($path, $key);

        return new InvalidPayloadException(
            sprintf('Field "%s" must be of type %s, %s given.', $field, $expected, self::typeOf($data, $key)),
            $field,
        );
    }

    /**
     * The JSON type of `$data[$key]`, in the words of the error messages.
     */
    private static function typeOf(array $data, string|int $key): string
    {
        return match (true) {
            !array_key_exists($key, $data), $data[$key] === null => 'null',
            is_bool($data[$key]) => 'bool',
            is_int($data[$key]) => 'int',
            is_float($data[$key]) => 'float',
            is_string($data[$key]) => 'string',
            is_array($data[$key]) && array_is_list($data[$key]) => 'list',
            default => 'object',
        };
    }
}
