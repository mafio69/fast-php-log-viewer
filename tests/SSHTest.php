<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\SSH;
use PHPUnit\Framework\TestCase;

class SSHTest extends TestCase
{
    public function testIsAvailableReturnsBoolean(): void
    {
        $isAvailable = SSH::isAvailable();
        $this->assertIsBool($isAvailable);
    }

    public function testConstructorAcceptsConfigArray(): void
    {
        $config = [
            'ssh_host' => 'example.com',
            'ssh_user' => 'user',
            'ssh_port' => 22,
        ];

        $ssh = new SSH($config);
        $this->assertInstanceOf(SSH::class, $ssh);
    }

    public function testConnectThrowsExceptionWhenHostMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH host and user are required');

        $ssh = new SSH(['ssh_user' => 'user']);
        $ssh->connect();
    }

    public function testConnectThrowsExceptionWhenUserMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH host and user are required');

        $ssh = new SSH(['ssh_host' => 'example.com']);
        $ssh->connect();
    }

    public function testConnectThrowsExceptionWhenPasswordMissingForPasswordAuth(): void
    {
        if (!SSH::isAvailable()) {
            $this->markTestSkipped('SSH2 extension not available');
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH password is required for password authentication');

        $ssh = new SSH([
            'ssh_host' => 'example.com',
            'ssh_user' => 'user',
            'ssh_auth_method' => 'password',
        ]);
        $ssh->connect();
    }

    public function testExecThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->exec('ls');
    }

    public function testFileExistsThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->fileExists('/path/to/file');
    }

    public function testDirectoryExistsThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->directoryExists('/path/to/dir');
    }

    public function testFileSizeThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->fileSize('/path/to/file');
    }

    public function testReadFileThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->readFile('/path/to/file');
    }

    public function testListFilesThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->listFiles('/path/to/dir');
    }

    public function testDisconnectDoesNothingWhenNotConnected(): void
    {
        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->disconnect();

        $this->assertTrue(true); // Should not throw exception
    }

    public function testDisconnectClosesConnection(): void
    {
        if (!SSH::isAvailable()) {
            $this->markTestSkipped('SSH2 extension not available');
        }

        $ssh = new SSH([
            'ssh_host' => 'example.com',
            'ssh_user' => 'user',
            'ssh_password' => 'pass',
        ]);

        try {
            $ssh->connect();
        } catch (\RuntimeException $e) {
            // Connection failed, but disconnect should still work
        }

        $ssh->disconnect();
        $this->assertTrue(true);
    }
}
