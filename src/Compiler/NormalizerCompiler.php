<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Compiler;

use BackedEnum;
use Waffle\Commons\Utils\Dto\Shape\DtoShape;
use Waffle\Commons\Utils\Dto\Shape\DtoShapeReader;
use Waffle\Commons\Utils\Dto\Shape\Enum\TypeKind;
use Waffle\Commons\Utils\Dto\Shape\FieldType;
use Waffle\Commons\Utils\Dto\Shape\InputField;
use Waffle\Commons\Utils\Dto\Shape\OutputField;
use Waffle\Commons\Utils\Exception\UnsupportedDtoShapeException;

use function array_keys;
use function array_push;
use function assert;
use function implode;
use function in_array;
use function is_bool;
use function is_string;
use function ksort;
use function sort;
use function sprintf;
use function str_replace;
use function ucfirst;
use function var_export;

/**
 * Compiles the normalizers of `#[Dto]` classes to PHP source, at build time
 * (API-02; the AOT philosophy of RFC-019).
 *
 * It reads each DTO, and every `#[Dto]` it reaches, through the `utils`
 * `DtoShapeReader` (the reader the OpenAPI generator uses too), then generates
 * one file per class (named after it, so a PSR-4 loader or a plain `require`
 * finds it):
 *
 *  - a `final readonly` normalizer class per DTO, with static `toArray()` and
 *    `fromArray()` methods that read and build the DTO with plain PHP: property
 *    reads out, a constructor call with named arguments in. No reflection runs
 *    at request time, and a DTO is always built through its constructor, so its
 *    `set` hooks validate every value;
 *  - an index class implementing `NormalizerProviderInterface`, which maps each
 *    DTO class to its normalizer.
 *
 * Every check on a decoded value goes through `Payload`, like the interpreted
 * `ShapeNormalizer` does, so both accept, reject and word errors the same way.
 * The output is deterministic (DTOs sorted by class name), so it can be
 * committed or diffed, and it is written to pass Mago's linter and analyzer
 * like hand-written code. The source is assembled as strings: a code-generation
 * library would breach the component's dependency perimeter.
 */
final readonly class NormalizerCompiler
{
    public const string DEFAULT_NAMESPACE = 'Waffle\\Generated\\Serializer';
    public const string DEFAULT_INDEX = 'CompiledNormalizers';

    private const string DTO_NORMALIZER = 'Waffle\\Commons\\Serializer\\Normalizer\\DtoNormalizerInterface';
    private const string PROVIDER = 'Waffle\\Commons\\Serializer\\Normalizer\\NormalizerProviderInterface';

    /** The classes every generated normalizer names. */
    private const array NORMALIZER_IMPORTS = [
        'InvalidArgumentException',
        'Waffle\\Commons\\Contracts\\Exception\\Validation\\ValidationExceptionInterface',
        'Waffle\\Commons\\Serializer\\Exception\\InvalidPayloadException',
        'Waffle\\Commons\\Serializer\\Exception\\NormalizerException',
        self::DTO_NORMALIZER,
        'Waffle\\Commons\\Serializer\\Normalizer\\Payload',
    ];

    public function __construct(
        private DtoShapeReader $reader = new DtoShapeReader(),
    ) {}

    /**
     * @param list<class-string> $classes   The DTOs to compile; every `#[Dto]` they reach is compiled too.
     * @param string             $namespace The namespace of the generated classes.
     * @param string             $index     The short name of the generated index class.
     *
     * @return array<string, string> The generated files, one class each: file name => PHP source, sorted by name.
     *
     * @throws UnsupportedDtoShapeException When a DTO uses a construct the serializer cannot represent.
     */
    public function compile(
        array $classes,
        string $namespace = self::DEFAULT_NAMESPACE,
        string $index = self::DEFAULT_INDEX,
    ): array {
        $shapes = $this->reader->readGraph(...$classes);
        ksort($shapes);
        $names = $this->classNames(array_keys($shapes));

        $files = [
            $index . '.php' => $this->file(
                $namespace,
                [self::DTO_NORMALIZER, self::PROVIDER],
                [],
                $this->indexClass($index, $names),
            ),
        ];

        foreach ($shapes as $class => $shape) {
            $functions = [];
            $body = $this->normalizerClass($shape, $shapes, $names, $functions);
            $files[$this->nameOf($names, $class) . '.php'] = $this->file(
                $namespace,
                self::NORMALIZER_IMPORTS,
                $functions,
                $body,
            );
        }

        ksort($files);

        return $files;
    }

    /**
     * One file: the header, its imports, then one class.
     *
     * @param list<string> $classes   The classes the body names.
     * @param list<string> $functions The functions the body calls.
     * @param list<string> $body
     */
    private function file(string $namespace, array $classes, array $functions, array $body): string
    {
        sort($classes);
        sort($functions);

        $lines = ['<?php', '', 'declare(strict_types=1);', '', sprintf('namespace %s;', $namespace), ''];
        foreach ($classes as $class) {
            $lines[] = sprintf('use %s;', $class);
        }

        if ($functions !== []) {
            $lines[] = '';
            foreach ($functions as $function) {
                $lines[] = sprintf('use function %s;', $function);
            }
        }

        return implode("\n", [...$lines, '', ...$body]) . "\n";
    }

    /**
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     * @param list<string>                  $functions Collects the functions the class calls.
     *
     * @return list<string>
     */
    private function normalizerClass(DtoShape $shape, array $shapes, array $names, array &$functions): array
    {
        $dto = '\\' . $shape->class;
        $helpers = [];

        $lines = [
            '/**',
            sprintf(' * Normalizes {@see %s}. Generated by waffle-commons/serializer: do not edit, rebuild.', $dto),
            ' */',
            sprintf('final readonly class %s implements DtoNormalizerInterface', $this->nameOf($names, $shape->class)),
            '{',
            '    /**',
            '     * @return array<string, int|float|string|bool|array|\\stdClass|null>',
            '     *',
            '     * @throws NormalizerException',
            '     */',
            '    #[\\Override]',
            '    public function normalize(object $dto): array',
            '    {',
            sprintf('        if (!$dto instanceof %s) {', $dto),
            sprintf('            throw NormalizerException::mismatch(%s::class, $dto);', $dto),
            '        }',
            '',
            '        return self::toArray($dto);',
            '    }',
            '',
            '    /**',
            '     * @throws InvalidPayloadException',
            '     */',
            '    #[\\Override]',
            sprintf("    public function denormalize(array \$data, string \$path = ''): %s", $dto),
            '    {',
            '        return self::fromArray($data, $path);',
            '    }',
            '',
            ...$this->toArray($shape, $shapes, $names, $functions),
            '',
            ...$this->fromArray($shape, $names, $functions, $helpers),
        ];

        foreach ($helpers as $helper) {
            array_push($lines, '', ...$helper);
        }

        $lines[] = '}';

        return $lines;
    }

    /**
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     * @param list<string>                  $functions
     *
     * @return list<string>
     */
    private function toArray(DtoShape $shape, array $shapes, array $names, array &$functions): array
    {
        $locals = [];
        $entries = [];
        foreach ($shape->outputs as $output) {
            $entries[] = sprintf(
                '            %s => %s,',
                $this->quote($output->name),
                $this->outputValue($output, $shapes, $names, $functions, $locals),
            );
        }

        $lines = [
            '    /**',
            '     * @return array<string, int|float|string|bool|array|\\stdClass|null>',
            '     */',
            sprintf('    public static function toArray(\\%s $dto): array', $shape->class),
            '    {',
        ];

        if ($entries === []) {
            return [...$lines, '        return [];', '    }'];
        }

        foreach ($locals as $local) {
            $lines[] = '        ' . $local;
        }

        if ($locals !== []) {
            $lines[] = '';
        }

        return [...$lines, '        return [', ...$entries, '        ];', '    }'];
    }

    /**
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     * @param list<string>                  $functions
     * @param list<string>                  $locals    Collects the local assignments the entry relies on.
     */
    private function outputValue(
        OutputField $output,
        array $shapes,
        array $names,
        array &$functions,
        array &$locals,
    ): string {
        $type = $output->type;
        $read = sprintf('$dto->%s', $output->name);

        if ($type->kind === TypeKind::Enum) {
            return $read . ($type->nullable ? '?->value' : '->value');
        }

        if (!$type->nullable || $type->kind !== TypeKind::Dto && $type->kind !== TypeKind::List) {
            return $this->encode($read, $type, $shapes, $names, $functions);
        }

        // A nullable object or list is read once into a local, so a `get` hook
        // runs once and the null check narrows the value.
        $local = '$' . $output->name . 'Value';
        $locals[] = sprintf('%s = %s;', $local, $read);

        return sprintf('%s === null ? null : %s', $local, $this->encode($local, $type, $shapes, $names, $functions));
    }

    /**
     * The JSON-ready form of a non-null value of `$type`.
     *
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     * @param list<string>                  $functions
     */
    private function encode(string $value, FieldType $type, array $shapes, array $names, array &$functions): string
    {
        return match ($type->kind) {
            TypeKind::Int, TypeKind::Float, TypeKind::String, TypeKind::Bool => $value,
            TypeKind::Enum => $value . '->value',
            TypeKind::Dto => $this->encodeDto($value, $type, $shapes, $names),
            TypeKind::List => $this->encodeList($value, $type, $shapes, $names, $functions),
        };
    }

    /**
     * A DTO without readable properties is cast to an object, so it encodes as
     * `{}` rather than `[]`.
     *
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     */
    private function encodeDto(string $value, FieldType $type, array $shapes, array $names): string
    {
        $class = $this->classOf($type);
        $call = sprintf('%s::toArray(%s)', $this->nameOf($names, $class), $value);

        return ($shapes[$class] ?? null)?->outputs === [] ? '(object) ' . $call : $call;
    }

    /**
     * @param array<class-string, DtoShape> $shapes
     * @param array<class-string, string>   $names
     * @param list<string>                  $functions
     */
    private function encodeList(string $value, FieldType $type, array $shapes, array $names, array &$functions): string
    {
        $item = $this->itemOf($type);
        $this->uses($functions, 'array_values');
        $list = sprintf('array_values(%s)', $value);

        if ($item->kind->isScalar()) {
            return $list;
        }

        $this->uses($functions, 'array_map');
        if ($item->kind === TypeKind::Enum) {
            return sprintf(
                'array_map(static fn(\\%s $case): %s => $case->value, %s)',
                $this->classOf($item),
                $item->backing === TypeKind::Int ? 'int' : 'string',
                $list,
            );
        }

        $class = $this->classOf($item);
        if (($shapes[$class] ?? null)?->outputs === []) {
            return sprintf(
                'array_map(static fn(\\%s $item): object => (object) %s::toArray($item), %s)',
                $class,
                $this->nameOf($names, $class),
                $list,
            );
        }

        return sprintf('array_map(%s::toArray(...), %s)', $this->nameOf($names, $class), $list);
    }

    /**
     * @param array<class-string, string> $names
     * @param list<string>                $functions
     * @param list<list<string>>          $helpers   Collects the list helpers the arguments call.
     *
     * @return list<string>
     */
    private function fromArray(DtoShape $shape, array $names, array &$functions, array &$helpers): array
    {
        $lines = [
            '    /**',
            '     * @throws InvalidPayloadException',
            '     */',
            sprintf("    public static function fromArray(array \$data, string \$path = ''): \\%s", $shape->class),
            '    {',
            '        try {',
        ];

        if ($shape->inputs === []) {
            $lines[] = sprintf('            return new \\%s();', $shape->class);
        } else {
            $lines[] = sprintf('            return new \\%s(', $shape->class);
            foreach ($shape->inputs as $input) {
                $lines[] = sprintf(
                    '                %s: %s,',
                    $input->name,
                    $this->inputValue($input, $names, $functions, $helpers),
                );
            }

            $lines[] = '            );';
        }

        array_push(
            $lines,
            '        } catch (ValidationExceptionInterface|InvalidArgumentException $exception) {',
            '            throw Payload::rejected($exception, $path);',
            '        }',
            '    }',
        );

        return $lines;
    }

    /**
     * The argument for one constructor parameter. A required field is passed to
     * its check, which reports it missing; an optional one falls back to its
     * default, or to `null`.
     *
     * @param array<class-string, string> $names
     * @param list<string>                $functions
     * @param list<list<string>>          $helpers
     */
    private function inputValue(InputField $input, array $names, array &$functions, array &$helpers): string
    {
        $decoded = $this->decodeField($input, $names, $functions, $helpers);
        if ($input->required) {
            return $decoded;
        }

        $this->uses($functions, 'array_key_exists');
        $key = $this->quote($input->name);
        $present = sprintf('array_key_exists(%s, $data)', $key);

        if (!$input->type->nullable) {
            return sprintf('%s ? %s : %s', $present, $decoded, $this->export($input->default));
        }

        if (!$input->hasDefault || $input->default === null) {
            return sprintf('%s && $data[%s] !== null ? %s : null', $present, $key, $decoded);
        }

        // Three outcomes (absent, null, a value): a match, not a nested ternary.
        return implode("\n", [
            'match (true) {',
            sprintf('                    !%s => %s,', $present, $this->export($input->default)),
            sprintf('                    $data[%s] === null => null,', $key),
            sprintf('                    default => %s,', $decoded),
            '                }',
        ]);
    }

    /**
     * A list of enums or of DTOs is decoded by a private helper, one per field.
     *
     * @param array<class-string, string> $names
     * @param list<string>                $functions
     * @param list<list<string>>          $helpers
     */
    private function decodeField(InputField $input, array $names, array &$functions, array &$helpers): string
    {
        $type = $input->type;
        $key = $this->quote($input->name);
        if ($type->kind !== TypeKind::List) {
            return $this->decode($type, '$data', $key, '$path', $names);
        }

        $item = $this->itemOf($type);
        if ($item->kind->isScalar()) {
            return sprintf('Payload::%sList($data, %s, $path)', $item->kind->value, $key);
        }

        $this->uses($functions, 'array_keys');
        $helper = 'listOf' . ucfirst($input->name);
        $helpers[] = [
            '    /**',
            sprintf('     * @return list<\\%s>', $this->classOf($item)),
            '     *',
            '     * @throws InvalidPayloadException',
            '     */',
            sprintf('    private static function %s(array $data, string $path): array', $helper),
            '    {',
            sprintf('        $items = Payload::list($data, %s, $path);', $key),
            sprintf('        $field = Payload::path($path, %s);', $key),
            '',
            '        $list = [];',
            '        foreach (array_keys($items) as $index) {',
            sprintf('            $list[] = %s;', $this->decode($item, '$items', '$index', '$field', $names)),
            '        }',
            '',
            '        return $list;',
            '    }',
        ];

        return sprintf('self::%s($data, $path)', $helper);
    }

    /**
     * The constructor argument for `$data[$key]`, a non-list value of `$type`,
     * checked in place by `Payload`. `$data`, `$key` and `$path` are PHP
     * expressions.
     *
     * @param array<class-string, string> $names
     */
    private function decode(FieldType $type, string $data, string $key, string $path, array $names): string
    {
        $where = sprintf('%s, %s, %s', $data, $key, $path);

        return match ($type->kind) {
            TypeKind::Int, TypeKind::Float, TypeKind::String, TypeKind::Bool => sprintf(
                'Payload::%s(%s)',
                $type->kind->value,
                $where,
            ),
            TypeKind::Enum => sprintf(
                '\\%1$s::tryFrom(Payload::%2$s(%3$s)) ?? Payload::unknownCase(\\%1$s::cases(), %4$s, %5$s)',
                $this->classOf($type),
                $type->backing === TypeKind::Int ? 'int' : 'string',
                $where,
                $path,
                $key,
            ),
            TypeKind::Dto, TypeKind::List => sprintf(
                '%s::fromArray(Payload::object(%s), Payload::path(%s, %s))',
                $this->nameOf($names, $this->classOf($type)),
                $where,
                $path,
                $key,
            ),
        };
    }

    /**
     * @param array<class-string, string> $names
     *
     * @return list<string>
     */
    private function indexClass(string $index, array $names): array
    {
        $lines = [
            '/**',
            ' * Maps each compiled DTO to its normalizer. Generated by waffle-commons/serializer: do not edit, rebuild.',
            ' */',
            sprintf('final readonly class %s implements NormalizerProviderInterface', $index),
            '{',
            '    /** @var array<string, DtoNormalizerInterface> */',
            '    private array $normalizers;',
            '',
            '    public function __construct()',
            '    {',
        ];

        if ($names === []) {
            $lines[] = '        $this->normalizers = [];';
        } else {
            $lines[] = '        $this->normalizers = [';
            foreach ($names as $class => $name) {
                $lines[] = sprintf('            \\%s::class => new %s(),', $class, $name);
            }

            $lines[] = '        ];';
        }

        array_push(
            $lines,
            '    }',
            '',
            '    #[\\Override]',
            '    public function get(string $class): ?DtoNormalizerInterface',
            '    {',
            '        return $this->normalizers[$class] ?? null;',
            '    }',
            '}',
        );

        return $lines;
    }

    /**
     * One generated class name per DTO: its namespaced name without separators,
     * plus `Normalizer`, numbered when two DTOs would collide.
     *
     * @param list<class-string> $classes
     *
     * @return array<class-string, string>
     */
    private function classNames(array $classes): array
    {
        $names = [];
        foreach ($classes as $class) {
            $base = str_replace('\\', '', $class);
            $name = $base . 'Normalizer';
            for ($suffix = 2; in_array($name, $names, true); $suffix++) {
                $name = $base . $suffix . 'Normalizer';
            }

            $names[$class] = $name;
        }

        return $names;
    }

    /**
     * @param array<class-string, string> $names
     */
    private function nameOf(array $names, string $class): string
    {
        $name = $names[$class] ?? null;
        assert($name !== null, 'Every DTO the shapes reach is compiled.');

        return $name;
    }

    /**
     * @return class-string
     */
    private function classOf(FieldType $type): string
    {
        $class = $type->class;
        assert($class !== null, 'A DTO or enum type names its class.');

        return $class;
    }

    private function itemOf(FieldType $type): FieldType
    {
        $item = $type->item;
        assert($item !== null, 'A list type has an element type.');

        return $item;
    }

    /**
     * @param list<string> $functions
     */
    private function uses(array &$functions, string $function): void
    {
        if (!in_array($function, $functions, true)) {
            $functions[] = $function;
        }
    }

    /**
     * @param int|float|string|bool|BackedEnum|array{}|null $value
     */
    private function export(int|float|string|bool|BackedEnum|array|null $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === [] => '[]',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $this->quote($value),
            $value instanceof BackedEnum => sprintf('\\%s::%s', $value::class, $value->name),
            default => var_export($value, true),
        };
    }

    private function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
