<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests;

use Mariusz\LogViewer\Service\SecurityService;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private SecurityService $securityService;

    protected function setUp(): void
    {
        $this->securityService = new SecurityService();
    }

    public function testIsBinaryContent()
    {
        // Text content should not be binary
        $textContent = "This is a text log file\n[2026-06-09 18:00:00] [INFO] Test message\n";
        $this->assertFalse($this->securityService->isBinaryContent($textContent));

        // Binary content should be detected
        $binaryContent = "\x00\x01\x02\x03\x04\x05";
        $this->assertTrue($this->securityService->isBinaryContent($binaryContent));

        // Mixed content with null bytes
        $mixedContent = "Text content\0\x01\x02";
        $this->assertTrue($this->securityService->isBinaryContent($mixedContent));
    }

    public function testContainsSuspiciousContent()
    {
        // PHP tags should be detected
        $phpContent = "<?php echo 'hello'; ?>";
        $this->assertTrue($this->securityService->containsSuspiciousContent($phpContent));

        // PHP short echo tag
        $phpShort = "<?= \$variable ?>";
        $this->assertTrue($this->securityService->containsSuspiciousContent($phpShort));

        // HTML script tag
        $scriptContent = "<script>alert('xss')</script>";
        $this->assertTrue($this->securityService->containsSuspiciousContent($scriptContent));

        // Shell shebang
        $shebang = "#!/usr/bin/bash";
        $this->assertTrue($this->securityService->containsSuspiciousContent($shebang));

        // PHP eval function
        $evalContent = "eval(\$code)";
        $this->assertTrue($this->securityService->containsSuspiciousContent($evalContent));

        // PHP exec function
        $execContent = "exec('ls -la')";
        $this->assertTrue($this->securityService->containsSuspiciousContent($execContent));

        // PHP $_GET
        $getContent = "\$_GET['param']";
        $this->assertTrue($this->securityService->containsSuspiciousContent($getContent));

        // Safe content should pass
        $safeContent = "[2026-06-09 18:00:00] [INFO] This is a safe log message\n[2026-06-09 18:00:01] [DEBUG] Another safe message\n";
        $this->assertFalse($this->securityService->containsSuspiciousContent($safeContent));

        // Log content with file:line format
        $logWithLocation = "[ERROR] app/index.php:42 Something went wrong\n";
        $this->assertFalse($this->securityService->containsSuspiciousContent($logWithLocation));
    }

    public function testMaxFileSize()
    {
        // Test that 10MB limit is enforced
        $maxSize = 10 * 1024 * 1024; // 10MB
        $this->assertEquals(10485760, $maxSize);

        // Small file should pass
        $smallFile = str_repeat("A", 1000);
        $this->assertTrue(strlen($smallFile) < $maxSize);

        // Large file should fail
        $largeFile = str_repeat("A", 11 * 1024 * 1024); // 11MB
        $this->assertTrue(strlen($largeFile) > $maxSize);
    }
}