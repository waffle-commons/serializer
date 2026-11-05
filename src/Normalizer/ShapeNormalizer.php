<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Normalizer;

use BackedEnum;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionEnum;
use ReflectionException;
use ReflectionObject;
use ReflectionProperty;
use stdClass;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;
use Waffle\Commons\Serializer\Exception\InvalidPayloadException;
use Waffle\Commons\Serializer\Exception\NormalizerException;
use Waffle\Commons\Utils\Dto\Shape\DtoShape;
use Waffle\Commons\Utils\Dto\Shape\Enum\TypeKind;
use Waffle\Commons\Utils\Dto\Shape\FieldType;

use function array_key_exists;
use function array_keys;
use function assert;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;

/**
 * Normalizes one DTO by interpreting its shape at run time.
 *
 * It is the fallback when no compiled normalizer exists (the development path),
 * and the reference the compiled normalizers are tested against: both call the
 * same {@see Payload} checks, so they accept, reject and emit the same things.
 *
 * Unlike the compiled normalizers it uses reflection: `ReflectionProperty`
 * reads the outputs (running their `get` hooks), and
 * `ReflectionClass::newInstance()` calls the constructor with named arguments
 * (running its `set` hooks). A field the payload omits is left out of the
 * arguments when the parameter has a default, so PHP applies it.
 *
 * Reflection, like `json_decode()`, hands values back untyped: they are kept in
 * an array and narrowed in place (`$values[$key]`), never bound on their own.
 */
final readonly class ShapeNormalizer implements DtoNormalizerInterface
{
    public function __construct(
        private DtoShape $shape,
        private NormalizerProviderInterface $normalizers,
    ) {}

    /**
     * @return array<string, int|float|string|bool|array|stdClass|null>
     *
     * @throws NormalizerException
     */
    #[\Override]
    public function normalize(object $dto): array
    {
        if (!is_a($dto::class, $this->shape->class, true)) {
            throw NormalizerException::mismatch($this->shape->class, $dto);
        }

        $properties = [];
        foreach (new ReflectionObject($dto)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[$property->getName()] = $property;
        }

        $values = [];
        $data = [];
        foreach ($this->shape->outputs as $output) {
            $property = $properties[$output->name] ?? null;
            assert($property !== null, 'The shape lists only readable properties.');
            $values[$output->name] = $property->getValue($dto);
            $data[$output->name] = $this->toJson($values, $output->name, $output->type);
        }

        return $data;
    }

    /**
     * @throws InvalidPayloadException
     * @throws NormalizerException
     */
    #[\Override]
    public function denormalize(array $data, string $path = ''): object
    {
        $arguments = [];
        foreach ($this->shape->inputs as $input) {
            $name = $input->name;
            if (array_key_exists($name, $data)) {
                $arguments[$name] = $this->fromJson($data, $name, $input->type, $path);
                continue;
            }

            if ($input->hasDefault) {
                continue;
            }

            if ($input->required) {
                Payload::missing($path, $name);
            }

            $arguments[$name] = null;
        }

        try {
            return new ReflectionClass($this->shape->class)->newInstance(...$arguments);
        } catch (ValidationExceptionInterface|InvalidArgumentException $exception) {
            throw Payload::rejected($exception, $path);
        } catch (ReflectionException $exception) {
            throw NormalizerException::unbuildable($this->shape->class, $exception);
        }
    }

    /**
     * The constructor argument for `$data[$key]`.
     *
     * @throws InvalidPayloadException
     * @throws NormalizerException
     */
    private function fromJson(
        array $data,
        string|int $key,
        FieldType $type,
        string $path,
    ): int|float|string|bool|object|array|null {
        if ($type->nullable && ($data[$key] ?? null) === null) {
            return null;
        }

        return match ($type->kind) {
            TypeKind::Int => Payload::int($data, $key, $path),
            TypeKind::Float => Payload::float($data, $key, $path),
            TypeKind::String => Payload::string($data, $key, $path),
            TypeKind::Bool => Payload::bool($data, $key, $path),
            TypeKind::Enum => $this->enumFromJson($data, $key, $type, $path),
            TypeKind::Dto => $this->normalizer($type)->denormalize(
                Payload::object($data, $key, $path),
                Payload::path($path, $key),
            ),
            TypeKind::List => $this->listFromJson($data, $key, $type, $path),
        };
    }

    /**
     * The case whose backing value the payload carries, found by reflection:
     * the compiled normalizers call `Enum::tryFrom()` on the concrete enum.
     *
     * @throws InvalidPayloadException
     * @throws NormalizerException
     */
    private function enumFromJson(array $data, string|int $key, FieldType $type, string $path): BackedEnum
    {
        $backing = $type->backing === TypeKind::Int
            ? Payload::int($data, $key, $path)
            : Payload::string($data, $key, $path);

        $cases = $this->cases($type);
        foreach ($cases as $case) {
            if ($case->value === $backing) {
                return $case;
            }
        }

        Payload::unknownCase($cases, $path, $key);
    }

    /**
     * @return list<BackedEnum>
     *
     * @throws NormalizerException
     */
    private function cases(FieldType $type): array
    {
        $enum = $type->class;
        assert($enum !== null, 'An enum field names its enum.');

        try {
            $reflection = new ReflectionEnum($enum);
        } catch (ReflectionException $exception) {
            throw NormalizerException::unbuildable($enum, $exception);
        }

        $cases = [];
        foreach ($reflection->getCases() as $case) {
            $instance = $case->getValue();
            assert($instance instanceof BackedEnum, 'The shape types a backed enum.');
            $cases[] = $instance;
        }

        return $cases;
    }

    /**
     * @return list<int|float|string|bool|object|array|null>
     *
     * @throws InvalidPayloadException
     * @throws NormalizerException
     */
    private function listFromJson(array $data, string|int $key, FieldType $type, string $path): array
    {
        $item = $type->item;
        assert($item !== null, 'The shape types a list element.');

        $items = Payload::list($data, $key, $path);
        $field = Payload::path($path, $key);

        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = $this->fromJson($items, $index, $item, $field);
        }

        return $list;
    }

    /**
     * The JSON-ready form of `$values[$key]`, a property value of `$type`.
     *
     * @throws NormalizerException
     */
    private function toJson(array $values, string|int $key, FieldType $type): int|float|string|bool|array|stdClass|null
    {
        // A typed property holds what its type says: null only when nullable.
        if (($values[$key] ?? null) === null) {
            return null;
        }

        return match ($type->kind) {
            TypeKind::Int, TypeKind::Float, TypeKind::String, TypeKind::Bool => $this->scalar($values, $key),
            TypeKind::Enum => $this->enumToJson($values, $key),
            TypeKind::Dto => $this->dtoToJson($values, $key, $type),
            TypeKind::List => $this->listToJson($values, $key, $type),
        };
    }

    private function scalar(array $values, string|int $key): int|float|string|bool
    {
        assert(
            array_key_exists($key, $values)
            && (is_int($values[$key]) || is_float($values[$key]) || is_string($values[$key]) || is_bool($values[$key])),
            'A scalar field.',
        );

        return $values[$key];
    }

    private function enumToJson(array $values, string|int $key): int|string
    {
        assert(array_key_exists($key, $values) && $values[$key] instanceof BackedEnum, 'An enum field.');

        return $values[$key]->value;
    }

    /**
     * A DTO without readable properties is the empty object, `{}`.
     *
     * @return array<string, int|float|string|bool|array|stdClass|null>|stdClass
     *
     * @throws NormalizerException
     */
    private function dtoToJson(array $values, string|int $key, FieldType $type): array|stdClass
    {
        // Narrowing does not survive a call: fetch the normalizer first.
        $normalizer = $this->normalizer($type);
        assert(array_key_exists($key, $values) && is_object($values[$key]), 'A DTO field.');

        $data = $normalizer->normalize($values[$key]);

        return $data === [] ? new stdClass() : $data;
    }

    /**
     * @return list<int|float|string|bool|array|stdClass|null>
     *
     * @throws NormalizerException
     */
    private function listToJson(array $values, string|int $key, FieldType $type): array
    {
        $item = $type->item;
        assert($item !== null && array_key_exists($key, $values) && is_array($values[$key]), 'A list field.');

        $items = $values[$key];
        $list = [];
        foreach (array_keys($items) as $index) {
            $list[] = $this->toJson($items, $index, $item);
        }

        return $list;
    }

    /**
     * @throws NormalizerException
     */
    private function normalizer(FieldType $type): DtoNormalizerInterface
    {
        $class = $type->class;
        assert($class !== null, 'A DTO field names its class.');

        return $this->normalizers->get($class) ?? throw NormalizerException::missing($class);
    }
}
