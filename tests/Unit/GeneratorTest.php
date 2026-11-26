<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Unit;

use DateTimeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Trongio\PhpTsTypes\Emitter\TypeScriptEmitter;
use Trongio\PhpTsTypes\Exceptions\ConflictingTypeName;
use Trongio\PhpTsTypes\Exceptions\TypeGenerationException;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Inertia\InertiaPageManifest;
use Trongio\PhpTsTypes\Php\ClassFinder;
use Trongio\PhpTsTypes\Php\ClassMapLeafMapper;
use Trongio\PhpTsTypes\Source\KeyCase;
use Trongio\PhpTsTypes\Source\PublicPropertiesSource;
use Trongio\PhpTsTypes\Testing\ShapeRules;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureAliased;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureBareArray;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureBracketed;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureCarbon;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureChild;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureGroupUse;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureLazy;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureLazyPage;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureNamed;
use Trongio\PhpTsTypes\Tests\Fixtures\FixturePage;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureProtected;
use Trongio\PhpTsTypes\Tests\Fixtures\FixtureWhen;
use Trongio\PhpTsTypes\Tests\Fixtures\Other\FixtureChild as OtherChild;
use Trongio\PhpTsTypes\Tests\Fixtures\Shape;
use Trongio\PhpTsTypes\Type\Json;
use Trongio\PhpTsTypes\Type\Primitive;
use Trongio\PhpTsTypes\Type\Types;
use Trongio\PhpTsTypes\Type\WrapperKind;
use Trongio\PhpTsTypes\TypeGenerator;

/** Classes in, one module out: the public-properties source, leaf mappers, key case, the Inertia manifest. */
final class GeneratorTest extends TestCase
{
    private static function emit(TypeGenerator $generator, string ...$classes): string
    {
        /** @var list<class-string> $classes */
        return (new TypeScriptEmitter)->emit($generator->generate($classes), '//');
    }

    public function test_it_describes_a_class_and_everything_it_reaches_deterministically(): void
    {
        $generator = new TypeGenerator([new PublicPropertiesSource([Shape::class])]);
        $out = self::emit($generator, FixturePage::class);

        self::assertStringContainsString(<<<'TS'
            export type FixturePage = {
            	count: number;
            	ratio: number | null;
            	on: boolean;
            	id: string | number;
            	tone: FixtureTone;
            	child: FixtureChild | null;
            	/** the rows */
            	children: FixtureChild[];
            	shape: { a: number; "b-c": FixtureLevel };
            };
            TS, $out);
        self::assertStringContainsString('export type FixtureTone = "info" | "warn";', $out);
        self::assertStringContainsString('export type FixtureLevel = 1 | 2;', $out);

        preg_match_all('/^export type (\w+)/m', $out, $names);
        self::assertSame(['FixtureChild', 'FixtureLevel', 'FixturePage', 'FixtureTone'], $names[1]);
        self::assertSame($out, self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), FixtureChild::class, FixturePage::class));
    }

    /** @return array<string, array{class-string, string}> */
    public static function refusedClasses(): array
    {
        return [
            'a type alias on the class' => [FixtureAliased::class, '@phpstan-type'],
            'a date with no leaf mapper' => [FixtureCarbon::class, 'FixtureCarbon::$at: unsupported type `DateTimeImmutable`'],
            'an array without a key type' => [FixtureBareArray::class, 'FixtureBareArray::$items: unsupported type `array<mixed>`'],
            'a non-public property' => [FixtureProtected::class, 'FixtureProtected::$secret'],
        ];
    }

    #[DataProvider('refusedClasses')]
    public function test_it_refuses_a_class_it_cannot_describe_faithfully(string $class, string $message): void
    {
        $this->expectException(TypeGenerationException::class);
        $this->expectExceptionMessage($message);

        self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), $class);
    }

    public function test_a_leaf_mapper_maps_a_class_the_table_refuses(): void
    {
        $generator = new TypeGenerator(
            [new PublicPropertiesSource([Shape::class])],
            [new ClassMapLeafMapper([DateTimeInterface::class => Primitive::string()])],
        );

        self::assertStringContainsString("export type FixtureWhen = {\n\tat: string;\n};", self::emit($generator, FixtureWhen::class));
    }

    public function test_a_json_leaf_is_one_named_recursive_type_never_unknown(): void
    {
        $generator = new TypeGenerator(
            [new PublicPropertiesSource([Shape::class])],
            [new ClassMapLeafMapper([DateTimeInterface::class => new Json])],
        );
        $out = self::emit($generator, FixtureWhen::class);

        self::assertStringContainsString("export type FixtureWhen = {\n\tat: JsonValue;\n};", $out);
        self::assertStringContainsString('export type JsonValue = string | number | boolean | null | JsonValue[] | { [key: string]: JsonValue };', $out);
        self::assertStringNotContainsString('unknown', $out);
        self::assertInstanceOf(Json::class, Types::named('json'));
        self::assertNull(Types::named('unknown'));
    }

    public function test_a_configured_wrapper_makes_the_key_optional_and_a_null_wrapper_is_the_same(): void
    {
        $generator = new TypeGenerator([new PublicPropertiesSource([Shape::class])], wrappers: [FixtureLazy::class => WrapperKind::Optional]);

        self::assertStringContainsString(<<<'TS'
            export type FixtureLazyPage = {
            	/** sent only when asked for */
            	child?: FixtureChild;
            	names?: string[];
            	count?: number;
            	title: string;
            };
            TS, self::emit($generator, FixtureLazyPage::class));
    }

    public function test_a_wrapper_class_is_refused_when_it_is_not_configured(): void
    {
        $this->expectException(UnsupportedType::class);
        $this->expectExceptionMessage('FixtureLazyPage::$child: unsupported type `FixtureLazy<FixtureChild>`');

        self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), FixtureLazyPage::class);
    }

    public function test_the_attribute_names_the_type_and_keys_follow_the_configured_case(): void
    {
        $verbatim = self::emit(new TypeGenerator([new PublicPropertiesSource]), FixtureNamed::class);
        $camel = self::emit(new TypeGenerator([new PublicPropertiesSource([], true, KeyCase::Camel)]), FixtureNamed::class);
        $none = self::emit(new TypeGenerator([new PublicPropertiesSource([], false)]), FixtureNamed::class, stdClass::class);

        self::assertStringContainsString("export type Renamed = {\n\tsnake_case_key: string;\n};", $verbatim);
        self::assertStringContainsString("\tsnakeCaseKey: string;", $camel);
        self::assertSame("//\n\n", $none);
        self::assertSame('snake_case_key', KeyCase::Snake->apply('snakeCaseKey'));
    }

    public function test_the_inertia_manifest_maps_each_page_to_its_props_type(): void
    {
        $generator = new TypeGenerator([new PublicPropertiesSource([Shape::class])]);
        $classes = [FixtureNamed::class, FixturePage::class, FixtureChild::class];
        $definitions = $generator->generate($classes);
        (new InertiaPageManifest)->addTo($definitions, $generator, $classes);

        self::assertStringContainsString(
            "export type InertiaPages = {\n\t\"fixture/Named\": Renamed;\n\t\"fixture/Page\": FixturePage;\n};",
            (new TypeScriptEmitter)->emit($definitions),
        );
    }

    public function test_group_imports_resolve_and_a_same_named_identical_enum_shares_one_name(): void
    {
        $out = self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), FixtureGroupUse::class, FixturePage::class);

        self::assertStringContainsString("export type FixtureGroupUse = {\n\tkids: FixtureChild[];\n\tlevel: FixtureLevel;\n\ttone: FixtureTone;\n};", $out);
        self::assertSame(1, substr_count($out, 'export type FixtureTone ='));
    }

    public function test_two_classes_with_one_short_name_and_different_shapes_name_both_fqcns(): void
    {
        $this->expectException(ConflictingTypeName::class);
        $this->expectExceptionMessage('Type name FixtureChild is claimed by two different shapes: '.FixtureChild::class.' and '.OtherChild::class);

        self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), FixtureChild::class, OtherChild::class);
    }

    public function test_a_bracketed_namespace_is_refused_rather_than_resolved_against_the_wrong_block(): void
    {
        $this->expectException(UnsupportedType::class);
        $this->expectExceptionMessage(FixtureBracketed::class.': unsupported type `a bracketed namespace block');

        self::emit(new TypeGenerator([new PublicPropertiesSource([Shape::class])]), FixtureBracketed::class);
    }

    public function test_the_shape_rules_and_the_finder(): void
    {
        $classes = ClassFinder::find(dirname(__DIR__).'/Fixtures', 'Trongio\\PhpTsTypes\\Tests\\Fixtures');

        self::assertContains(FixturePage::class, $classes);
        self::assertSame([], ShapeRules::violations([FixturePage::class, FixtureChild::class]));
        self::assertSame([
            FixtureAliased::class.' docblock uses @phpstan-type: use a named class or a backed enum',
            FixtureProtected::class.'::$secret must be a public, non-static, constructor-promoted property',
        ], ShapeRules::violations([FixtureAliased::class, FixtureProtected::class]));
    }
}
