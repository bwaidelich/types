<?php

declare(strict_types=1);

namespace Wwwision\Types\Tests\PHPUnit;

use Exception;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function get_debug_type;
use function preg_match;
use function preg_match_all;
use function sprintf;

#[CoversNothing]
final class ReadmeCodeBlockTest extends TestCase
{

    private static ?string $previousNamespace = null;

    public static function code_blocks_dataProvider(): Generator
    {
        $readmeFilePath = realpath(__DIR__ . '/../../README.md');
        $readmeContents = file_get_contents($readmeFilePath);
        if (!is_string($readmeContents)) {
            self::fail(sprintf('Failed to read README file from "%s"', $readmeFilePath));
        }
        preg_match_all('/(?<=```php)(.+?)(?=```)/s', $readmeContents, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $matchGroup) {
            $lineNumber = substr_count(mb_substr($readmeContents, 0, $matchGroup[1]), PHP_EOL) + 1;
            $code = $matchGroup[0];
            preg_match('/Exception: (?<message>.*)/', $code, $exceptionMatches);
            yield ['code' => $code, 'lineNumber' => $lineNumber, 'expectedExceptionMessage' => $exceptionMatches['message'] ?? null];
        }
    }

    #[DataProvider('code_blocks_dataProvider')]
    public function test_code_blocks(string $code, int $lineNumber, string $expectedExceptionMessage = null): void
    {
        if (self::$previousNamespace !== null && str_starts_with(trim($code), '// ...')) {
            $namespace = self::$previousNamespace;
        } else {
            $namespace = "Wwwision\Types\Tests\CodeBlock_$lineNumber";
        }
        self::$previousNamespace = $namespace;
        $namespacedCode = <<<CODE
            namespace $namespace {
                use Countable;
                use JsonSerializable;
                use IteratorAggregate;
                use ArrayIterator;
                use Traversable;
                use Wwwision\Types\Attributes\Description;
                use Wwwision\Types\Attributes\IntegerBased;
                use Wwwision\Types\Attributes\ListBased;
                use Wwwision\Types\Attributes\StringBased;
                use Wwwision\Types\Parser;
                use Wwwision\Types\Schema\StringTypeFormat;
                use function Wwwision\Types\instantiate;
                $code
            }
            CODE;
        $caughtException = null;
        try {
            eval($namespacedCode);
        } catch (Exception $exception) {
            $caughtException = $exception;
        }
        if ($caughtException !== null) {
            self::assertNotNull($expectedExceptionMessage, sprintf('Did not expect an exception for code block in line %d but got one of type %s: %s', $lineNumber, get_debug_type($caughtException), $caughtException->getMessage()));
            $exceptionType = $caughtException::class;
            self::assertSame($expectedExceptionMessage, $exceptionType . ': ' . $caughtException->getMessage(), sprintf('Exception for code block in line %d did not match the expected', $lineNumber));
        } else {
            self::assertNull($expectedExceptionMessage, sprintf('Expected exception "%s" in code block in line %d but none was thrown', $expectedExceptionMessage, $lineNumber));
        }
    }
}