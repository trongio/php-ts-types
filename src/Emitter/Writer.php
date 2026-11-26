<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Emitter;

use RuntimeException;

/**
 * Writes the generated file, or checks it. `check()` writes the fresh build to a temp file, diffs the committed
 * file against it and returns the head of that diff, or null when they are equal. The temp file is always
 * deleted, on a match, a drift or an exception alike.
 */
final readonly class Writer
{
    /** @param  string|null  $tempDirectory  where `check()` puts its temp file; null = the system temp dir */
    public function __construct(
        private ?string $tempDirectory = null,
    ) {}

    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create '.$directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Cannot write '.$path);
        }
    }

    /** null when `$path` holds exactly `$contents`, else a diff head (at most `$lines` lines). */
    public function check(string $path, string $contents, int $lines = 20): ?string
    {
        $fresh = tempnam($this->tempDirectory ?? sys_get_temp_dir(), 'ts-types-');

        if ($fresh === false) {
            throw new RuntimeException('Cannot create a temp file for the check');
        }

        try {
            $this->write($fresh, $contents);
            $built = file_get_contents($fresh);
            $committed = is_file($path) ? file_get_contents($path) : false;

            if ($built === false) {
                throw new RuntimeException('Cannot read back '.$fresh);
            }

            if ($committed === $built) {
                return null;
            }

            return implode("\n", [
                '--- '.$path.($committed === false ? ' (missing)' : ''),
                '+++ fresh build',
                ...self::diff($committed === false ? '' : $committed, $built, $lines),
            ]);
        } finally {
            if (is_file($fresh)) {
                unlink($fresh);
            }
        }
    }

    /**
     * The changed lines from the first difference on, `-` committed and `+` fresh, with their line numbers.
     *
     * @return list<string>
     */
    public static function diff(string $old, string $new, int $limit): array
    {
        $a = explode("\n", $old);
        $b = explode("\n", $new);
        $first = 0;

        while ($first < count($a) && $first < count($b) && $a[$first] === $b[$first]) {
            $first++;
        }

        $lastA = count($a) - 1;
        $lastB = count($b) - 1;

        while ($lastA >= $first && $lastB >= $first && $a[$lastA] === $b[$lastB]) {
            $lastA--;
            $lastB--;
        }

        $out = ['@@ line '.($first + 1).' @@'];

        for ($i = $first; $i <= $lastA; $i++) {
            $out[] = '-'.$a[$i];
        }

        for ($i = $first; $i <= $lastB; $i++) {
            $out[] = '+'.$b[$i];
        }

        return count($out) > $limit ? [...array_slice($out, 0, $limit), '...'] : $out;
    }
}
