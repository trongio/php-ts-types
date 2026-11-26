<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes;

use BackedEnum;
use ReflectionClass;
use Trongio\PhpTsTypes\Attributes\TsType;
use Trongio\PhpTsTypes\Exceptions\UnsupportedType;
use Trongio\PhpTsTypes\Php\ClassTypeResolver;
use Trongio\PhpTsTypes\Php\LeafMapper;
use Trongio\PhpTsTypes\Php\PhpTypeMapper;
use Trongio\PhpTsTypes\Source\TypeSource;
use Trongio\PhpTsTypes\Type\Json;
use Trongio\PhpTsTypes\Type\Literal;
use Trongio\PhpTsTypes\Type\Reference;
use Trongio\PhpTsTypes\Type\Type;
use Trongio\PhpTsTypes\Type\TypeDefinitions;
use Trongio\PhpTsTypes\Type\Union;
use Trongio\PhpTsTypes\Type\WrapperKind;

/**
 * PHP classes to {@see TypeDefinitions}. Starting from the root classes, each class is described by the first
 * source that supports it, and every class met inside a type is resolved in this order: a leaf mapper's
 * answer, a backed enum (a literal union of its values), a class some source supports (a named reference,
 * described in turn), else an {@see UnsupportedType}. A class nothing claims is an error, never `unknown`.
 */
final class TypeGenerator implements ClassTypeResolver
{
    private readonly TypeDefinitions $definitions;

    private readonly PhpTypeMapper $mapper;

    /** @var list<class-string> */
    private array $queue = [];

    /** @var array<string, true> */
    private array $seen = [];

    /**
     * One generator per output file: the definitions accumulate across `generate()` and `docType()` calls.
     *
     * @param  list<TypeSource>  $sources
     * @param  list<LeafMapper>  $leaves
     * @param  array<string, WrapperKind>  $wrappers  lazy-prop wrapper classes (fully qualified) => their kind; see
     *                                                {@see PhpTypeMapper}
     */
    public function __construct(
        private readonly array $sources,
        private readonly array $leaves = [],
        ?TypeDefinitions $definitions = null,
        array $wrappers = [],
    ) {
        $this->definitions = $definitions ?? new TypeDefinitions;
        $this->mapper = new PhpTypeMapper($this, $wrappers);
    }

    /**
     * Describes every root a source supports (others are skipped) and everything they reach.
     *
     * @param  list<class-string>  $roots
     */
    public function generate(array $roots): TypeDefinitions
    {
        foreach ($roots as $class) {
            if ($this->sourceFor(new ReflectionClass($class)) !== null) {
                $this->queue[] = $class;
            }
        }

        $this->drain();

        return $this->definitions;
    }

    /**
     * A PHPDoc type string read in `$context`'s file, with every class it reaches described too. The entry
     * point for a source or a caller that has a type string rather than a property.
     *
     * @param  ReflectionClass<object>  $context
     */
    public function docType(string $type, ReflectionClass $context, string $where): Type
    {
        $mapped = $this->mapper->docType($type, $context, $where);
        $this->drain();

        return $mapped;
    }

    public function definitions(): TypeDefinitions
    {
        return $this->definitions;
    }

    private function drain(): void
    {
        while ($this->queue !== []) {
            $class = array_shift($this->queue);
            $reflection = new ReflectionClass($class);
            $source = $this->sourceFor($reflection);

            if ($source === null || array_key_exists($class, $this->seen)) {
                continue;
            }

            $this->seen[$class] = true;
            $this->definitions->define($source->name($reflection), $source->describe($reflection, $this->mapper), $class);
        }
    }

    /** The TypeScript name the sources give a class, or null when none supports it. */
    public function nameOf(string $class): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        return $this->sourceFor($reflection)?->name($reflection);
    }

    public function resolveClass(string $class, string $where): Type
    {
        foreach ($this->leaves as $leaf) {
            $type = $leaf->map($class);

            if ($type instanceof Json) {
                $this->definitions->define(Json::NAME, $type, 'the json leaf');
            }

            if ($type !== null) {
                return $type;
            }
        }

        if (enum_exists($class)) {
            return $this->enum($class, $where);
        }

        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            $source = $this->sourceFor($reflection);

            if ($source !== null) {
                $this->queue[] = $reflection->getName();

                return new Reference($source->name($reflection));
            }
        }

        throw new UnsupportedType($where, $class);
    }

    /** @param  class-string<\UnitEnum>  $class */
    private function enum(string $class, string $where): Type
    {
        if (! is_subclass_of($class, BackedEnum::class)) {
            throw new UnsupportedType($where, $class.' (a unit enum has no wire value)');
        }

        $values = [];

        foreach ($class::cases() as $case) {
            $values[] = new Literal($case->value);
        }

        if ($values === []) {
            throw new UnsupportedType($where, $class.' (an enum without cases)');
        }

        $reflection = new ReflectionClass($class);
        $name = $reflection->getShortName();

        foreach ($reflection->getAttributes(TsType::class) as $attribute) {
            $name = $attribute->newInstance()->name ?? $name;
        }

        $this->definitions->define($name, Union::of(...$values), $class);

        return new Reference($name);
    }

    /** @param  ReflectionClass<object>  $class */
    private function sourceFor(ReflectionClass $class): ?TypeSource
    {
        foreach ($this->sources as $source) {
            if ($source->supports($class)) {
                return $source;
            }
        }

        return null;
    }
}
