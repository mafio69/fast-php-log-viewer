<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Service;

use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\FileAccessValidator;
use Mariusz\LogViewer\Service\PathResolver;
use PHPUnit\Framework\TestCase;

class FileAccessValidatorTest extends TestCase
{
    private FileAccessValidator $validator;
    private $pathResolver;
    private $logConfig;

    protected function setUp(): void
    {
        $this->pathResolver = $this->createMock(PathResolver::class);
        $this->logConfig = $this->createMock(LogConfig::class);
        $this->validator = new FileAccessValidator($this->pathResolver, $this->logConfig);
    }

    public function testSshDirReturnsTrueForExistingFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'va_');
        file_put_contents($tmpFile, 'test');

        $result = $this->validator->isFileAllowed($tmpFile, 'ssh:server1');

        $this->assertTrue($result);
        unlink($tmpFile);
    }

    public function testSshDirReturnsFalseForNonExistentFile(): void
    {
        $this->assertFalse($this->validator->isFileAllowed('/nonexistent/path.log', 'ssh:server1'));
    }

    public function testDirKeyResolvesAndAllowsFileWithinDir(): void
    {
        $tmpDir = sys_get_temp_dir() . '/va_test_' . uniqid();
        mkdir($tmpDir);
        $tmpFile = $tmpDir . '/test.log';
        file_put_contents($tmpFile, 'test');

        $this->pathResolver->method('resolveDirPath')
            ->with('docker:/var/log')
            ->willReturn($tmpDir);

        $result = $this->validator->isFileAllowed($tmpFile, 'docker:/var/log');

        $this->assertTrue($result);
        unlink($tmpFile);
        rmdir($tmpDir);
    }

    public function testFallsBackToDbDirsWhenNoDirKey(): void
    {
        $tmpDir = sys_get_temp_dir() . '/va_fb_' . uniqid();
        mkdir($tmpDir);
        $tmpFile = $tmpDir . '/app.log';
        file_put_contents($tmpFile, 'test');

        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'app', 'path' => $tmpDir]
        ]);

        $result = $this->validator->isFileAllowed($tmpFile, null);

        $this->assertTrue($result);
        unlink($tmpFile);
        rmdir($tmpDir);
    }

    public function testDeniesFileOutsideAllowedDirs(): void
    {
        $this->logConfig->method('getDirectories')->willReturn([
            ['name' => 'safe', 'path' => '/var/log']
        ]);

        $this->assertFalse($this->validator->isFileAllowed('/etc/passwd', null));
    }

    public function testIsFileInDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/va_sub_' . uniqid();
        mkdir($tmpDir);
        $tmpFile = $tmpDir . '/test.log';
        file_put_contents($tmpFile, 'test');

        $this->assertTrue($this->validator->isFileInDirectory($tmpFile, $tmpDir));
        $this->assertFalse($this->validator->isFileInDirectory('/etc/passwd', $tmpDir));

        unlink($tmpFile);
        rmdir($tmpDir);
    }
}
