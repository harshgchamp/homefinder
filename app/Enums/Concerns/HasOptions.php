<?php

namespace App\Enums\Concerns;

/**
 * @property-read string $value
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasOptions
{
    abstract public function label(): string;

    /**
     * @return list<static>
     */
    abstract public static function cases(): array;

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
