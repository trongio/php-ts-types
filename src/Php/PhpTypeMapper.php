<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Php;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use PhpToken;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Type\ListType;
use Trongio\PhpTsTypes\Type\Literal;
use Trongio\PhpTsTypes\Type\ObjectType;
use Trongio\PhpTsTypes\Type\Primitive;
use Trongio\PhpTsTypes\Type\Property;
use Trongio\PhpTsTypes\Type\RecordType;
use Trongio\PhpTsTypes\Type\Type;
use Trongio\PhpTsTypes\Type\Types;
use Trongio\PhpTsTypes\Type\Union;
use Trongio\PhpTsTypes\Type\Wrapped;
use Trongio\PhpTsTypes\Type\WrapperKind;

/**
 * PHP types (native, or PHPDoc read with phpstan/phpdoc-parser) to the type model. It knows exactly this table
 * and refuses everything else with an {@see UnsupportedType} naming `Class::$property.key`; it never falls back
 * to `unknown` or `any`.
 *
 * | PHP                                        | TypeScript                     |
 * |--------------------------------------------|--------------------------------|
 * | int, float and the int refinements         | number                         |
 * | string and the string refinements          | string                         |
 * | bool, true, false, null                    | boolean, true, false, null     |
 * | 'a', 2 (literal types)                     | "a", 2                         |
 * | ?T, A|B                                    | T \| null, A \| B              |
 * | a class                                    | whatever the resolver says     |
 * | list<T>, non-empty-list<T>                 | T[]                            |
 * | array<string, T>                           | Record<string, T>              |
 * | array{a: T, 'b-c'?: U} (sealed)            | { a: T; "b-c"?: U }            |
 * | Wrapper<T>, a configured wrapper class     | x?: T (the property only)      |
 *
 * Refused: mixed, object, bare array/list, `T[]` and `array<int, T>` (a list and an int-keyed map serialize
 * differently, so say `list<>`), unsealed or positional shapes, class constants (`self::A`), int ranges and
 * `@phpstan-type`/`@psalm-type` aliases. Classes are handed to the {@see ClassTypeResolver}. When a PHPDoc type
 * exists it wins over the native one; a native `?T` still adds `| null`.
 *
 * A wrapper (`$wrappers`, class => {@see WrapperKind}) is a lazy prop the server may leave out: `Optional<T>` as a
 * property's type maps to a {@see Wrapped} T, which emits `x?: T`. A nullable wrapper (`?Optional<T>`,
 * `Optional<T>|null`) is the same type: a null wrapper is a key left out, never a `null` sent. A wrapper anywhere
 * but a property's top level, without its one type argument, or in a union with anything but null is refused.
 */
final class PhpTypeMapper
{
    private const array NUMBER = [
        'int', 'float', 'positive-int', 'negative-int', 'non-negative-int', 'non-positive-int', 'non-zero-int',
    ];

    private const array STRING = [
        'string', 'non-empty-string', 'non-falsy-string', 'lowercase-string', 'numeric-string',
    ];

    private readonly Lexer $lexer;

    private readonly TypeParser $typeParser;

    private readonly PhpDocParser $docParser;

    /** @var array<string, array<string, string>> file => lowercased alias => fully qualified name */
    private array $imports = [];

    /** @param  array<string, WrapperKind>  $wrappers  wrapper class (fully qualified) => the lazy kind it is */
    public function __construct(
        private readonly ClassTypeResolver $classes,
        private readonly array $wrappers = [],
    ) {
        $config = new ParserConfig([]);
        $constants = new ConstExprParser($config);
        $this->lexer = new Lexer($config);
        $this->typeParser = new TypeParser($config, $constants);
        $this->docParser = new PhpDocParser($config, $this->typeParser, $constants);
    }

    /** One property's type, from its `@var` / constructor `@param` when present, else its native type. */
    public function property(ReflectionProperty $property): Type
    {
        $where = self::where($property);
        $native = $property->getType();
        $tag = $this->tag($property);

        if ($tag === null) {
            return $native === null
                ? throw new UnsupportedType($where, 'no type')
                : $this->native($native, $where);
        }

        $type = $this->node($tag->type, $property->getDeclaringClass(), $where);
        $wrapped = $this->wrapped($type, $where);

        if ($wrapped !== null) {
            return $wrapped;
        }

        return $native !== null && $native->allowsNull() ? Types::nullable($type) : $type;
    }

    /**
     * The wrapper a property's type is, or null when it is none: `W<T>` alone, or `W<T>|null` (a null wrapper
     * is a key left out). A wrapper in a union with any other member has no single wire meaning: refused.
     */
    private function wrapped(Type $type, string $where): ?Wrapped
    {
        if ($type instanceof Wrapped) {
            return $type;
        }

        if (! $type instanceof Union) {
            return null;
        }

        $wrapped = [];
        $others = 0;

        foreach ($type->members as $member) {
            if ($member instanceof Wrapped) {
                $wrapped[] = $member;
            } elseif ($member->key() !== 'null') {
                $others++;
            }
        }

        if ($wrapped === []) {
            return null;
        }

        if (count($wrapped) > 1 || $others > 0) {
            throw new UnsupportedType($where, 'a wrapper in a union (only `|null` may join it)');
        }

        return $wrapped[0];
    }

    /** The PHPDoc description of a property's `@var` or constructor `@param`, whitespace collapsed. */
    public function description(ReflectionProperty $property): string
    {
        $tag = $this->tag($property);

        return $tag === null ? '' : trim(preg_replace('/\s+/', ' ', $tag->description) ?? '');
    }

    /**
     * A PHPDoc type string read in `$context`'s file, so its `use` imports and namespace resolve class names.
     * The entry point for a source that reads types from elsewhere (a `@return`, a PHPStan collector's output).
     *
     * @param  ReflectionClass<object>  $context
     */
    public function docType(string $type, ReflectionClass $context, string $where): Type
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));

        try {
            $node = $this->typeParser->parse($tokens);
            $tokens->consumeTokenType(Lexer::TOKEN_END);
        } catch (ParserException) {
            throw new UnsupportedType($where, $type);
        }

        return $this->node($node, $context, $where);
    }

    /**
     * Refuses type aliases on a class: they are not resolved, so a type naming one would fail with a worse message.
     *
     * @param  ReflectionClass<object>  $class
     */
    public function assertNoAliases(ReflectionClass $class): void
    {
        if (preg_match('/@(phpstan|psalm)-(import-)?type\b/', (string) $class->getDocComment(), $match) === 1) {
            throw new UnsupportedType($class->getName(), $match[0].' (use a named class or a backed enum)');
        }
    }

    public static function where(ReflectionProperty $property): string
    {
        return $property->getDeclaringClass()->getName().'::$'.$property->getName();
    }

    private function tag(ReflectionProperty $property): VarTagValueNode|ParamTagValueNode|null
    {
        $own = $property->getDocComment();

        if ($own !== false) {
            foreach ($this->doc($own, $property)->getVarTagValues() as $var) {
                return $var;
            }
        }

        $constructor = $property->isPromoted() ? $property->getDeclaringClass()->getConstructor() : null;
        $doc = $constructor?->getDocComment();

        if ($doc === null || $doc === false) {
            return null;
        }

        foreach ($this->doc($doc, $property)->getParamTagValues() as $param) {
            if ($param->parameterName === '$'.$property->getName()) {
                return $param;
            }
        }

        return null;
    }

    /** Parses a docblock, refusing tool-specific tags: a `@phpstan-var` beside a `@var` lets the two disagree. */
    private function doc(string $doc, ReflectionProperty $property): PhpDocNode
    {
        $node = $this->docParser->parse(new TokenIterator($this->lexer->tokenize($doc)));

        foreach ($node->getTags() as $tag) {
            $typed = $tag->value instanceof VarTagValueNode || $tag->value instanceof ParamTagValueNode
                || $tag->value instanceof ReturnTagValueNode;

            if ($typed && (str_starts_with($tag->name, '@phpstan-') || str_starts_with($tag->name, '@psalm-'))) {
                throw new UnsupportedType(self::where($property), $tag->name.' (use @var or @param)');
            }
        }

        return $node;
    }

    private function native(ReflectionType $type, string $where): Type
    {
        if ($type instanceof ReflectionUnionType) {
            return Union::of(...array_map(fn (ReflectionType $member): Type => $this->native($member, $where), $type->getTypes()));
        }

        if (! $type instanceof ReflectionNamedType) {
            throw new UnsupportedType($where, (string) $type);
        }

        $name = $type->getName();

        if ($name === 'array') {
            throw new UnsupportedType($where, 'array without a PHPDoc list<T>, array<string, T> or array{...}');
        }

        $mapped = $type->isBuiltin() ? $this->builtin($name, $where) : $this->classes->resolveClass($name, $where);

        return $type->allowsNull() ? Types::nullable($mapped) : $mapped;
    }

    /** @param  ReflectionClass<object>  $context */
    private function node(TypeNode $node, ReflectionClass $context, string $where): Type
    {
        return match (true) {
            $node instanceof NullableTypeNode => Types::nullable($this->node($node->type, $context, $where)),
            $node instanceof UnionTypeNode => $this->union(Union::of(...array_map(
                fn (TypeNode $member): Type => $this->node($member, $context, $where),
                $node->types,
            )), $where),
            $node instanceof IdentifierTypeNode => $this->isBuiltin($node->name)
                ? $this->builtin($node->name, $where)
                : $this->identifier($this->resolveName($node->name, $context), $where),
            $node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprStringNode => new Literal($node->constExpr->value),
            $node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode => new Literal(self::integer($node->constExpr->value, $where)),
            $node instanceof GenericTypeNode => $this->generic($node, $context, $where),
            $node instanceof ArrayShapeNode => $this->shape($node, $context, $where),
            default => throw new UnsupportedType($where, (string) $node),
        };
    }

    /** A union, refused when it joins a wrapper to anything but null (see {@see self::wrapped()}). */
    private function union(Type $type, string $where): Type
    {
        $this->wrapped($type, $where);

        return $type;
    }

    /** `$type`, refused when it is a wrapper: inside a list, a map, a shape or another wrapper nothing can leave a key out. */
    private function eager(Type $type, string $where): Type
    {
        if ($this->wrapped($type, $where) !== null) {
            throw new UnsupportedType($where, 'a wrapper inside a list, map, shape or wrapper (only a property may be lazy)');
        }

        return $type;
    }

    /** A class named without type arguments. A wrapper needs its one: `Optional` alone says nothing about the value. */
    private function identifier(string $class, string $where): Type
    {
        if (array_key_exists($class, $this->wrappers)) {
            throw new UnsupportedType($where, $class.' (a wrapper needs its type argument: '.$class.'<T>)');
        }

        return $this->classes->resolveClass($class, $where);
    }

    /** @param  ReflectionClass<object>  $context */
    private function generic(GenericTypeNode $node, ReflectionClass $context, string $where): Type
    {
        $base = (string) $node->type;
        $params = $node->genericTypes;

        if (! $this->isBuiltin($base)) {
            $kind = $this->wrappers[$this->resolveName($base, $context)] ?? null;

            if ($kind !== null && count($params) === 1) {
                return new Wrapped($kind, $this->eager($this->node($params[0], $context, $where), $where));
            }

            throw new UnsupportedType($where, (string) $node);
        }

        if (in_array($base, ['list', 'non-empty-list'], true) && count($params) === 1) {
            return new ListType($this->eager($this->node($params[0], $context, $where.'[]'), $where.'[]'));
        }

        $key = $params[0] ?? null;

        if ($base === 'array' && count($params) === 2 && $key instanceof IdentifierTypeNode && in_array($key->name, self::STRING, true)) {
            return new RecordType($this->eager($this->node($params[1], $context, $where.'{}'), $where.'{}'));
        }

        throw new UnsupportedType($where, (string) $node);
    }

    /** @param  ReflectionClass<object>  $context */
    private function shape(ArrayShapeNode $node, ReflectionClass $context, string $where): Type
    {
        if ($node->kind !== ArrayShapeNode::KIND_ARRAY || ! $node->sealed || $node->items === []) {
            throw new UnsupportedType($where, (string) $node);
        }

        $properties = [];

        foreach ($node->items as $item) {
            $key = $item->keyName;
            $name = match (true) {
                $key instanceof IdentifierTypeNode => $key->name,
                $key instanceof ConstExprStringNode => $key->value,
                default => throw new UnsupportedType($where, (string) $node.' (positional or integer key)'),
            };
            $properties[] = new Property($name, $this->eager($this->node($item->valueType, $context, $where.'.'.$name), $where.'.'.$name), $item->optional);
        }

        return new ObjectType($properties);
    }

    /**
     * The value of a PHP integer literal as phpdoc-parser keeps it: a sign, `_` separators, and a `0x`, `0b`,
     * `0o` or leading-`0` (octal) prefix. One that does not fit an int is refused rather than saturated.
     */
    public static function integer(string $literal, string $where): int
    {
        $digits = strtolower(str_replace('_', '', $literal));
        $negative = str_starts_with($digits, '-');
        $digits = ltrim($digits, '+-');

        [$base, $body, $format, $pattern] = match (true) {
            str_starts_with($digits, '0x') => [16, substr($digits, 2), '%x', '/^[0-9a-f]+$/'],
            str_starts_with($digits, '0b') => [2, substr($digits, 2), '%b', '/^[01]+$/'],
            str_starts_with($digits, '0o') => [8, substr($digits, 2), '%o', '/^[0-7]+$/'],
            strlen($digits) > 1 && $digits[0] === '0' => [8, substr($digits, 1), '%o', '/^[0-7]+$/'],
            default => [10, $digits, '%d', '/^[0-9]+$/'],
        };
        $body = ltrim($body, '0');
        $body = $body === '' ? '0' : $body;
        $value = intval($body, $base);

        if (preg_match($pattern, $body) !== 1 || sprintf($format, $value) !== $body) {
            throw new UnsupportedType($where, $literal.' (not an integer literal that fits an int)');
        }

        return $negative ? -$value : $value;
    }

    /** A lowercase, unqualified name is a PHPDoc keyword (`int`, `mixed`, `list`), never a class. */
    private function isBuiltin(string $name): bool
    {
        return ! str_contains($name, '\\') && strtolower($name) === $name;
    }

    private function builtin(string $name, string $where): Type
    {
        return match (true) {
            in_array($name, self::NUMBER, true) => Primitive::number(),
            in_array($name, self::STRING, true) => Primitive::string(),
            $name === 'bool' => Primitive::boolean(),
            $name === 'null' => Primitive::null(),
            $name === 'true' => new Literal(true),
            $name === 'false' => new Literal(false),
            default => throw new UnsupportedType($where, $name),
        };
    }

    /** @param  ReflectionClass<object>  $context */
    private function resolveName(string $name, ReflectionClass $context): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $slash = strpos($name, '\\');
        $first = $slash === false ? $name : substr($name, 0, $slash);
        $rest = $slash === false ? null : substr($name, $slash + 1);
        $imported = $this->importsOf($context)[strtolower($first)] ?? null;

        if ($imported !== null) {
            return $rest === null ? $imported : $imported.'\\'.$rest;
        }

        $namespace = $context->getNamespaceName();

        return $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    /**
     * The `use` imports of the file declaring `$context`: lowercased alias => FQCN. Handles `use A\B;`,
     * `use A\B as C;`, `use A, B;` and group imports `use A\{B, C as D};`; `use function` / `use const`
     * (and such items inside a group) are skipped, as they never name a class. A bracketed `namespace X { }`
     * block is refused: imports would then be per block, and resolving against the wrong one is silent.
     *
     * @param  ReflectionClass<object>  $context
     * @return array<string, string>
     */
    private function importsOf(ReflectionClass $context): array
    {
        $file = $context->getFileName();

        if ($file === false) {
            return [];
        }

        if (array_key_exists($file, $this->imports)) {
            return $this->imports[$file];
        }

        $source = file_get_contents($file);
        $tokens = array_values(array_filter(
            $source === false ? [] : PhpToken::tokenize($source),
            static fn (PhpToken $token): bool => ! $token->isIgnorable(),
        ));
        $imports = [];
        $depth = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->is(T_NAMESPACE) && $depth === 0) {
                $next = $tokens[$i + 1] ?? null;
                $after = $tokens[$i + 2] ?? null;

                if ($next?->text === '{' || $after?->text === '{') {
                    throw new UnsupportedType($context->getName(), 'a bracketed namespace block in '.$file);
                }
            }

            $depth += match (true) {
                $token->text === '{', $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]) => 1,
                $token->text === '}' => -1,
                default => 0,
            };

            if ($depth !== 0 || ! $token->is(T_USE)) {
                continue;
            }

            $statement = [];

            for ($i++; $i < $count && $tokens[$i]->text !== ';'; $i++) {
                $statement[] = $tokens[$i];
            }

            foreach (self::useItems($statement) as $alias => $fqcn) {
                $imports[$alias] = $fqcn;
            }
        }

        return $this->imports[$file] = $imports;
    }

    /**
     * The class imports of one `use` statement (the tokens between `use` and `;`): lowercased alias => FQCN.
     *
     * @param  list<PhpToken>  $tokens
     * @return array<string, string>
     */
    private static function useItems(array $tokens): array
    {
        if ($tokens === [] || $tokens[0]->is([T_FUNCTION, T_CONST])) {
            return [];
        }

        $prefix = '';
        $open = null;

        foreach ($tokens as $index => $token) {
            if ($token->text === '{') {
                $open = $index;

                break;
            }
        }

        if ($open !== null) {
            foreach (array_slice($tokens, 0, $open) as $token) {
                $prefix .= $token->text;
            }

            $prefix = trim($prefix, '\\').'\\';
            $tokens = array_slice($tokens, $open + 1);
        }

        $items = [];
        $current = [];

        foreach ([...$tokens, null] as $token) {
            if ($token === null || $token->text === ',' || $token->text === '}') {
                $items[] = $current;
                $current = [];

                continue;
            }

            $current[] = $token;
        }

        $imports = [];

        foreach ($items as $item) {
            if ($item === [] || $item[0]->is([T_FUNCTION, T_CONST])) {
                continue;
            }

            $name = ltrim($item[0]->text, '\\');
            $fqcn = $open === null ? $name : $prefix.$name;
            $segments = explode('\\', $fqcn);
            $alias = count($item) === 3 && $item[1]->is(T_AS) ? $item[2]->text : end($segments);
            $imports[strtolower($alias)] = $fqcn;
        }

        return $imports;
    }
}
