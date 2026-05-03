<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\LogParser;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase
{
    private LogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LogParser();
    }

    public function testParsesFullLine(): void
    {
        $entry = $this->parser->parseLine('[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Something off {"user":"jan@example.com"}');

        $this->assertSame('2026-05-03 14:25:00', $entry['datetime']);
        $this->assertSame('WARNING', $entry['level']);
        $this->assertSame('app/index.php:42', $entry['location']);
        $this->assertSame('Something off', $entry['message']);
        $this->assertSame(['user' => 'jan@example.com'], $entry['context']);
    }

    public function testParsesLineWithoutContext(): void
    {
        $entry = $this->parser->parseLine('[2026-05-03 10:00:00] [INFO] [src/App.php:10] App started');

        $this->assertSame('INFO', $entry['level']);
        $this->assertSame('App started', $entry['message']);
        $this->assertSame([], $entry['context']);
    }

    public function testLevelIsAlwaysUppercase(): void
    {
        $entry = $this->parser->parseLine('[2026-05-03 10:00:00] [error] [src/App.php:1] Oops');

        $this->assertSame('ERROR', $entry['level']);
    }

    public function testReturnsNullForInvalidLine(): void
    {
        $this->assertNull($this->parser->parseLine('not a log line'));
        $this->assertNull($this->parser->parseLine(''));
    }

    public function testParsesAllLevels(): void
    {
        foreach (['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'] as $level) {
            $entry = $this->parser->parseLine("[2026-05-03 10:00:00] [$level] [f.php:1] msg");
            $this->assertSame($level, $entry['level'], "Failed for level $level");
        }
    }

    public function testParseFileReturnsEntriesReversed(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($tmp,
            "[2026-05-03 10:00:00] [INFO] [a.php:1] first\n" .
            "[2026-05-03 11:00:00] [INFO] [a.php:2] second\n"
        );

        $entries = (new LogParser())->parseFile($tmp);
        unlink($tmp);

        $this->assertCount(2, $entries);
        $this->assertSame('second', $entries[0]['message']); // reversed
        $this->assertSame('first', $entries[1]['message']);
    }

    public function testParseFileSkipsInvalidLines(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($tmp,
            "[2026-05-03 10:00:00] [INFO] [a.php:1] valid\n" .
            "garbage line\n"
        );

        $entries = (new LogParser())->parseFile($tmp);
        unlink($tmp);

        $this->assertCount(1, $entries);
    }

    public function testParseFileReturnsEmptyForMissingFile(): void
    {
        $this->assertSame([], (new LogParser())->parseFile('/nonexistent/path.log'));
    }
}
