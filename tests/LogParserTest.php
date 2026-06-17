<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\LogParser;
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
        file_put_contents(
            $tmp,
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
        file_put_contents(
            $tmp,
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

    public function testParsesNginxErrorLogFormat(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents(
            $tmp,
            "2026/06/05 07:00:00 [error] 1234#0: *12345 upstream timed out\n" .
            "2026/06/05 07:01:00 [warn] 1234#0: *12346 client closed connection\n"
        );

        $entries = (new LogParser())->parseFile($tmp);
        unlink($tmp);

        $this->assertCount(2, $entries);
        $this->assertSame('2026-06-05 07:01:00', $entries[0]['datetime']);
        $this->assertSame('WARNING', $entries[0]['level']);
        $this->assertSame('client closed connection', $entries[0]['message']);
        $this->assertSame('2026-06-05 07:00:00', $entries[1]['datetime']);
        $this->assertSame('ERROR', $entries[1]['level']);
    }

    public function testNginxLevelMapping(): void
    {
        $levelMap = [
            'error' => 'ERROR', 'warn' => 'WARNING',
            'notice' => 'NOTICE', 'info' => 'INFO', 'crit' => 'CRITICAL',
            'alert' => 'ALERT', 'emerg' => 'EMERGENCY',
        ];

        foreach ($levelMap as $nginxLevel => $expectedLevel) {
            $line = "2026/06/05 07:00:00 [$nginxLevel] 1234#0: *12345 test message";
            $tmp = tempnam(sys_get_temp_dir(), 'log');
            file_put_contents($tmp, $line);
            $entries = (new LogParser())->parseFile($tmp);
            unlink($tmp);

            $this->assertSame($expectedLevel, $entries[0]['level'], "Failed for nginx level $nginxLevel");
        }
    }

    public function testParsesSimpleFormat(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($tmp, "[2024-06-05 12:00:00] INFO: Application started\n");

        $entries = (new LogParser())->parseFile($tmp);
        unlink($tmp);

        $this->assertCount(1, $entries);
        $this->assertSame('2024-06-05 12:00:00', $entries[0]['datetime']);
        $this->assertSame('INFO', $entries[0]['level']);
        $this->assertSame('Application started', $entries[0]['message']);
    }

    public function testParsesPhpErrorFormat(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        file_put_contents($tmp, "[04-May-2026 09:09:37 Europe/Warsaw] PHP Fatal error: Call to undefined function in /var/www/index.php on line 42\n");

        $entries = (new LogParser())->parseFile($tmp);
        unlink($tmp);

        $this->assertCount(1, $entries);
        $this->assertSame('ERROR', $entries[0]['level']);
        $this->assertSame('/var/www/index.php:42', $entries[0]['location']);
        $this->assertStringContainsString('Call to undefined function', $entries[0]['message']);
    }
}
