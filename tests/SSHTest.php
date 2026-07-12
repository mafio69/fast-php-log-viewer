<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use InvalidArgumentException;
use Mariusz\LogViewer\Service\SSH;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH host and user are required');

        $ssh = new SSH(['ssh_user' => 'user']);
        $ssh->connect();
    }

    public function testConnectThrowsExceptionWhenUserMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH host and user are required');

        $ssh = new SSH(['ssh_host' => 'example.com']);
        $ssh->connect();
    }

    public function testConnectThrowsExceptionWhenPasswordMissingForPasswordAuth(): void
    {
        if (!SSH::isAvailable()) {
            $this->markTestSkipped('SSH2 extension not available');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SSH password is required for password authentication');

        // Ustawiamy brak hosta, aby uniknąć ssh2_connect, 
        // ale konstruktor SSH rzuci InvalidArgumentException jeśli brakuje hosta.
        // Jednak authenticateWithPassword jest wywoływane dopiero PO ssh2_connect w metodzie connect().
        // Aby przetestować samą walidację hasła bez łączenia, musimy zapewnić, 
        // że dojdzie do wywołania authenticateWithPassword.
        // W obecnej strukturze SSH.php jest to trudne bez mockowania.
        // Zmieńmy SSH.php, aby walidacja hasła również była przed połączeniem.
        
        $ssh = new SSH([
            'ssh_host' => '127.0.0.1',
            'ssh_port' => 1,
            'ssh_user' => 'user',
            'ssh_auth_method' => 'password',
            // 'ssh_password' => missing
        ]);
        $ssh->connect();
    }

    public function testExecThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->exec('ls');
    }

    public function testFileExistsThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->fileExists('/path/to/file');
    }

    public function testDirectoryExistsThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->directoryExists('/path/to/dir');
    }

    public function testFileSizeThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->fileSize('/path/to/file');
    }

    public function testReadFileThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SSH connection not established');

        $ssh = new SSH(['ssh_host' => 'example.com', 'ssh_user' => 'user']);
        $ssh->readFile('/path/to/file');
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
            'ssh_host' => '127.0.0.1', // Używamy localhost zamiast example.com dla szybkości
            'ssh_port' => 1, // Port, który na pewno odrzuci połączenie natychmiast
            'ssh_user' => 'user',
            'ssh_password' => 'pass',
        ]);

        try {
            $ssh->connect();
        } catch (RuntimeException $e) {
            // Connection failed, but disconnect should still work
        }

        $ssh->disconnect();
        $this->assertTrue(true);
    }

    public function testDefaultKeyPathDiscovery(): void
    {
        $ssh = new SSH([
            'ssh_host' => '127.0.0.1',
            'ssh_port' => 1,
            'ssh_user' => 'user',
            'ssh_auth_method' => 'key',
        ]);

        // Musimy się upewnić, że nie ma żadnych domyślnych kluczy w systemie, 
        // które mogłyby sprawić, że walidacja przejdzie pomyślnie.
        // Jeśli test uruchamiany jest w środowisku, gdzie /home/www-data/.ssh/id_rsa istnieje, 
        // to ten test może próbować się łączyć.
        
        try {
            $ssh->connect();
            $this->fail('Powinien zostać rzucony wyjątek o braku klucza lub błędzie połączenia');
        } catch (RuntimeException $e) {
            // Jeśli walidacja klucza działa, powinniśmy dostać błąd o braku pliku.
            // Jeśli jednak jakiś domyślny klucz istnieje w środowisku testowym,
            // dostaniemy błąd połączenia (Failed to connect to SSH server).
            
            $message = $e->getMessage();
            if (str_contains($message, 'Failed to connect to SSH server')) {
                $this->markTestSkipped('W środowisku testowym znaleziono domyślny klucz SSH, test walidacji braku klucza pominięty.');
            } else {
                $this->assertStringContainsString('SSH key file not found', $message);
                $this->assertStringContainsString('Tip: Inside docker use /home/www-data/.ssh/ path', $message);
                $this->assertStringContainsString('keyPath: null', $message);
            }
        }
    }

    public function testExceptionMessageContainsLogicalCondition(): void
    {
        $ssh = new SSH([
            'ssh_host' => '127.0.0.1',
            'ssh_port' => 1,
            'ssh_user' => 'user',
            'ssh_auth_method' => 'key',
            'ssh_key_path' => '/non/existent/path/id_rsa'
        ]);

        try {
            $ssh->connect();
        } catch (RuntimeException $e) {
            // W teście sprawdzamy czy sformatowany komunikat zawiera sformatowane ścieżki
            $this->assertStringContainsString('!/non/existent/path/id_rsa || !file_exists(/non/existent/path/id_rsa)', $e->getMessage());
            $this->assertStringContainsString('keyPath: /non/existent/path/id_rsa', $e->getMessage());
        }
    }
}
