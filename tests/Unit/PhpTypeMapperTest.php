<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Trongio\PhpTsTypes\Emitter\TypeScriptEmitter;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Php\PhpTypeMapper;
use Trongio\PhpTsTypes\Source\PublicPropertiesSource;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureLazy;
use Trongio\PhpTsTypes\Tests\Fixtures\FixturePage;
use Trongio\PhpTsTypes\Tests\Fixtures\Shape;
use Trongio\PhpTsTypes\Type\ObjectType;
use Trongio\PhpTsTypes\Type\Property;
use Trongio\PhpTsTypes\Type\Wrapped;
use Trongio\PhpTsTypes\Type\WrapperKind;
use Trongio\PhpTsTypes\TypeGenerator;

/**
 * The mapping table, row by row, and everything outside it. Docs are read in FixturePage's file, so `Level`
 * resolves through its `use ... as Level` import and the other fixtures by namespace.
 */
final class PhpTypeMapperTest extends TestCase
{
    private static function render(string $php): string
    {
        $generator = new TypeGenerator([new PublicPropertiesSource([Shape::class])], wrappers: [FixtureLazy::class => WrapperKind::Deferred]);
        $type = $generator->docType($php, new ReflectionClass(FixturePage::class), 'FixturePage::$probe');
        $definitions = $generator->definitions();
        $definitions->define('Probe', new ObjectType([new Property('x', $type)]));
        $out = (new TypeScriptEmitter)->emit($definitions, '//');

        return preg_match('/^export type Probe = \{\n\tx: (.*);\n\};$/m', $out, $match) === 1 ? $match[1] : $out;
    }

    /** @return array<string, array{string, string}> */
    public static function allowed(): array
    {
        return [
            'int' => ['int', 'number'],
            'float' => ['float', 'number'],
            'refined int' => ['positive-int', 'number'],
            'string' => ['string', 'string'],
            'refined string' => ['non-empty-string', 'string'],
            'bool' => ['bool', 'boolean'],
            'true' => ['true', 'true'],
            'null' => ['null', 'null'],
            'nullable' => ['?string', 'string | null'],
            'union, deduplicated' => ['int|float|null', 'number | null'],
            'string literal union' => ["'info'|'warn'", '"info" | "warn"'],
            'int literal union' => ['1|2', '1 | 2'],
            'hex literal' => ['0x10', '16'],
            'binary literal' => ['0b101', '5'],
            'explicit octal literal' => ['0o17', '15'],
            'legacy octal literal' => ['017', '15'],
            'separated literal' => ['1_000', '1000'],
            'negative literal' => ['-3', '-3'],
            'zero' => ['0', '0'],
            'string enum' => ['FixtureTone', 'FixtureTone'],
            'int enum through an alias' => ['Level', 'FixtureLevel'],
            'fully qualified class' => ['\Trongio\PhpTsTypes\Tests\Fixtures\FixtureChild', 'FixtureChild'],
            'list' => ['list<string>', 'string[]'],
            'non-empty list' => ['non-empty-list<int>', 'number[]'],
            'list of a union' => ['list<int|string>', '(number | string)[]'],
            'list of a class' => ['list<FixtureChild>', 'FixtureChild[]'],
            'string-keyed map' => ['array<string, bool>', 'Record<string, boolean>'],
            'nested map' => ['array<string, array<string, bool>>', 'Record<string, Record<string, boolean>>'],
            'shape' => ['array{a: int, b: ?FixtureTone}', '{ a: number; b: FixtureTone | null }'],
            'optional and nullable keys' => ['array{a?: int, b?: int|null}', '{ a?: number; b?: number | null }'],
            'quoted key, verbatim' => ["array{'b-c': list<string>, snake_key: int}", '{ "b-c": string[]; snake_key: number }'],
            'discriminated union' => [
                "array{kind: 'a', a: int}|array{kind: 'b', b: string}",
                '{ kind: "a"; a: number } | { kind: "b"; b: string }',
            ],
        ];
    }

    #[DataProvider('allowed')]
    public function test_it_maps_each_allowed_type(string $php, string $typescript): void
    {
        self::assertSame($typescript, self::render($php));
    }

    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        return [
            'mixed' => ['mixed'],
            'bare array' => ['array'],
            'bare list' => ['list'],
            'object' => ['object'],
            'T[] (keys unsaid)' => ['string[]'],
            'int-keyed map' => ['array<int, string>'],
            'one-parameter array' => ['array<string>'],
            'unsealed shape' => ['array{a: int, ...}'],
            'positional shape' => ['array{int}'],
            'list shape' => ['list{int}'],
            'class constant' => ['self::COMPONENT'],
            'int range' => ['int<0, 10>'],
            'float literal' => ['1.5'],
            'an int literal that overflows' => ['0xFFFFFFFFFFFFFFFFFF'],
            'a date' => ['\DateTimeImmutable'],
            'a plain class' => ['\stdClass'],
            'a unit enum' => ['FixtureUnit'],
            'an unknown class' => ['NoSuchThing'],
            'a type alias' => ['Row'],
            'unparsable' => ['list<'],
            'a wrapper without its type argument' => ['FixtureLazy'],
            'a wrapper with two type arguments' => ['FixtureLazy<int, string>'],
            'a wrapper inside a list' => ['list<FixtureLazy<int>>'],
            'a wrapper inside a shape' => ['array{a: FixtureLazy<int>}'],
            'a wrapper inside a map' => ['array<string, FixtureLazy<int>>'],
            'a wrapper in a union' => ['FixtureLazy<int>|string'],
            'a wrapper inside a wrapper' => ['FixtureLazy<FixtureLazy<int>>'],
            'a nullable wrapper inside a wrapper' => ['FixtureLazy<FixtureLazy<int>|null>'],
            'an unconfigured generic class' => ['FixtureChild<int>'],
        ];
    }

    #[DataProvider('refused')]
    public function test_it_refuses_every_type_outside_the_table_naming_the_property(string $php): void
    {
        $this->expectException(UnsupportedType::class);
        $this->expectExceptionMessage('FixturePage::$probe');

        self::render($php);
    }

    public function test_a_configured_wrapper_maps_to_its_kind_around_the_inner_type(): void
    {
        $generator = new TypeGenerator([new PublicPropertiesSource([Shape::class])], wrappers: [FixtureLazy::class => WrapperKind::Deferred]);
        $type = $generator->docType('FixtureLazy<list<int>>', new ReflectionClass(FixturePage::class), 'FixturePage::$probe');

        self::assertInstanceOf(Wrapped::class, $type);
        self::assertSame('deferred<list<number>>', $type->key());
    }

    public function test_an_integer_literal_too_large_for_an_int_is_refused_not_saturated(): void
    {
        self::assertSame(PHP_INT_MAX, PhpTypeMapper::integer('0x7fff_ffff_ffff_ffff', 'w'));

        $this->expectExceptionMessage('w: unsupported type `0xFFFFFFFFFFFFFFFFFF (not an integer literal that fits an int)`');
        PhpTypeMapper::integer('0xFFFFFFFFFFFFFFFFFF', 'w');
    }

    public function test_an_error_inside_a_shape_names_the_key_path(): void
    {
        $this->expectExceptionMessage('FixturePage::$probe.outer.inner[]: unsupported type `mixed`');

        self::render('array{outer: array{inner: list<mixed>}}');
    }
}
