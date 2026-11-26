<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trongio\PhpTsTypes\Emitter\TypeScriptEmitter;
use Trongio\PhpTsTypes\Exceptions\ConflictingTypeName;
use Trongio\PhpTsTypes\Exceptions\UnresolvedReference;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Type\ListType;
use Trongio\PhpTsTypes\Type\Literal;
use Trongio\PhpTsTypes\Type\ObjectType;
use Trongio\PhpTsTypes\Type\Primitive;
use Trongio\PhpTsTypes\Type\Property;
use Trongio\PhpTsTypes\Type\RecordType;
use Trongio\PhpTsTypes\Type\Reference;
use Trongio\PhpTsTypes\Type\TypeDefinitions;
use Trongio\PhpTsTypes\Type\Types;
use Trongio\PhpTsTypes\Type\Union;
use Trongio\PhpTsTypes\Type\Wrapped;
use Trongio\PhpTsTypes\Type\WrapperKind;

/** The core alone: build definitions by hand, emit them. No reflection, no Laravel. */
final class TypeModelAndEmitterTest extends TestCase
{
    public function test_types_compare_structurally(): void
    {
        $a = new ObjectType([new Property('x', Primitive::number()), new Property('y', Types::nullable(Primitive::string()))]);
        $b = new ObjectType([new Property('y', Union::of(Primitive::null(), Primitive::string())), new Property('x', Primitive::number(), description: 'ignored')]);

        self::assertTrue(Types::equal($a, $b));
        self::assertFalse(Types::equal($a, new ObjectType([new Property('x', Primitive::number(), true)])));
        self::assertFalse(Types::equal(new Literal(1), new Literal('1')));
        self::assertSame(Primitive::string()->key(), Union::of(Primitive::string(), Primitive::string())->key());
    }

    public function test_a_name_dedupes_the_same_shape_and_refuses_a_different_one(): void
    {
        $definitions = new TypeDefinitions;
        $definitions->define('Row', new ObjectType([new Property('id', Primitive::number())]), 'A');
        $definitions->define('Row', new ObjectType([new Property('id', Primitive::number())]), 'B');

        self::assertCount(1, $definitions->all());

        $this->expectException(ConflictingTypeName::class);
        $this->expectExceptionMessage('Type name Row is claimed by two different shapes: A and C');
        $definitions->define('Row', new ObjectType([new Property('id', Primitive::string())]), 'C');
    }

    public function test_it_emits_sorted_export_types_with_optional_nullable_and_wrapped_keys(): void
    {
        $definitions = new TypeDefinitions;
        $definitions->define('Tone', Union::of(new Literal('info'), new Literal('warn')));
        $definitions->define('Page', new ObjectType([
            new Property('plain', Primitive::string(), description: 'a */ comment'),
            new Property('nullable', Types::nullable(new Reference('Tone'))),
            new Property('optional', Primitive::number(), true),
            new Property('both', Types::nullable(Primitive::number()), true),
            new Property('deferred', new Wrapped(WrapperKind::Deferred, new ListType(new Reference('Tone')))),
            new Property('once', new Wrapped(WrapperKind::Once, Primitive::boolean())),
            new Property('kebab-key', new RecordType(Union::of(Primitive::string(), Primitive::number()))),
            new Property('event', Union::of(
                new ObjectType([new Property('kind', new Literal('a'))]),
                new ObjectType([new Property('kind', new Literal('b')), new Property('n', new Literal(2))]),
            )),
            new Property('empty', new ObjectType([])),
        ]));

        $out = (new TypeScriptEmitter)->emit($definitions, '// head', "declare module 'x' {}");

        self::assertSame(<<<'TS'
            // head

            export type Page = {
            	/** a * / comment */
            	plain: string;
            	nullable: Tone | null;
            	optional?: number;
            	both?: number | null;
            	deferred?: Tone[];
            	once?: boolean;
            	"kebab-key": Record<string, string | number>;
            	event: { kind: "a" } | { kind: "b"; n: 2 };
            	empty: Record<string, never>;
            };

            export type Tone = "info" | "warn";

            declare module 'x' {}

            TS, $out);
        self::assertDoesNotMatchRegularExpression('/\binterface\b|\bany\b|\bunknown\b|: \{\}/', $out);
    }

    public function test_it_refuses_a_dangling_reference_and_a_wrapper_outside_a_property(): void
    {
        $definitions = new TypeDefinitions;
        $definitions->define('A', new ObjectType([new Property('b', new Reference('Missing'))]));

        try {
            (new TypeScriptEmitter)->emit($definitions);
            self::fail('a dangling reference was emitted');
        } catch (UnresolvedReference $error) {
            self::assertSame('A.b: reference to undefined type Missing', $error->getMessage());
        }

        $definitions = new TypeDefinitions;
        $definitions->define('A', new ListType(new Wrapped(WrapperKind::Merge, Primitive::string())));

        $this->expectException(UnsupportedType::class);
        (new TypeScriptEmitter)->emit($definitions);
    }
}
