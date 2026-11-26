<?php

declare(strict_types=1);

namespace Trongio\PhpTsTypes\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trongio\PhpTsTypes\Emitter\Writer;

/** Write mode, and the check mode a lint gate runs. */
final class WriterTest extends TestCase
{
    public function test_check_passes_on_equal_content_and_prints_a_diff_head_otherwise(): void
    {
        $root = sys_get_temp_dir().'/ts-types-writer-'.bin2hex(random_bytes(4));
        $temp = $root.'/tmp';
        mkdir($temp, 0o755, true);
        $path = $root.'/out/out.d.ts';
        $writer = new Writer($temp);
        $writer->write($path, "a\nb\nc\n");

        try {
            self::assertNull($writer->check($path, "a\nb\nc\n"));
            self::assertSame([], self::leftovers($temp), 'a matching check left its temp file behind');

            $diff = $writer->check($path, "a\nB\nc\n");
            self::assertIsString($diff);
            self::assertStringContainsString("+++ fresh build\n@@ line 2 @@\n-b\n+B", $diff);
            self::assertSame("a\nb\nc\n", file_get_contents($path), 'check() must not write the committed file');
            self::assertSame([], self::leftovers($temp), 'a drifted check left its temp file behind');

            unlink($path);
            self::assertStringContainsString('(missing)', (string) $writer->check($path, "a\n"));
            self::assertFileDoesNotExist($path);
            self::assertSame([], self::leftovers($temp), 'a missing-file check left its temp file behind');
        } finally {
            foreach ([$temp, $root.'/out'] as $directory) {
                $files = glob($directory.'/*');
                array_map(unlink(...), $files === false ? [] : $files);
                rmdir($directory);
            }

            rmdir($root);
        }
    }

    /** @return list<string> */
    private static function leftovers(string $directory): array
    {
        $entries = scandir($directory);

        return array_values(array_diff($entries === false ? [] : $entries, ['.', '..']));
    }

    public function test_the_diff_head_is_bounded(): void
    {
        $diff = Writer::diff('', implode("\n", range(1, 100)), 5);

        self::assertCount(6, $diff);
        self::assertSame('...', $diff[5]);
    }
}
