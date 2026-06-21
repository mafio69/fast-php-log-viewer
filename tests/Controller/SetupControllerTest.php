<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\SetupController;
use Mariusz\LogViewer\Config\ConfigManager;
use Mariusz\LogViewer\Config\LogConfig;
use Mariusz\LogViewer\Service\SetupWizard;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class SetupControllerTest extends TestCase
{
    private SetupController $controller;
    private SetupWizard $wizard;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));

        $configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
        $logConfig = $this->createMock(LogConfig::class);
        $this->wizard = new SetupWizard($configManager, $logConfig);
        $this->controller = new SetupController($this->wizard);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfig)) {
            @unlink($this->tempConfig);
        }
        if (file_exists($this->tempEnv)) {
            @unlink($this->tempEnv);
        }
    }

    public function testGetStatusReturnsSetupRequiredWhenIncomplete(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/setup/status');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getStatus($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['setup_required']);
    }

    public function testPostStepWithValidStep(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/setup/step');
        $data = [
            'step' => 'generate_keys',
            'data' => [],
            'skip' => true
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->postStep($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);
    }

    public function testPostStepWithUnknownStep(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/setup/step');
        $data = [
            'step' => 'unknown_step',
            'data' => [],
            'skip' => false
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->postStep($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('unknown_step', $body['error']);
    }

    public function testPostMigrateSSHWithValidData(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/setup/migrate-ssh');
        $data = [
            'connections' => [
                [
                    'name' => 'Test SSH',
                    'ssh_host' => 'example.com',
                    'ssh_user' => 'testuser'
                ]
            ]
        ];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->postMigrateSSH($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertArrayHasKey('migrated', $body);
        $this->assertArrayHasKey('warnings', $body);
    }
}
