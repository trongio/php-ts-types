<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Type;

/** `Record<string, T>`: a JSON object with arbitrary string keys (a PHP `array<string, T>`). */
final readonly class RecordType implements Type
{
    public function __construct(
        public Type $value,
    ) {}

    public function key(): string
    {
        return 'record<'.$this->value->key().'>';
    }
}
