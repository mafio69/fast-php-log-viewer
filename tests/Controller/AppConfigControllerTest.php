<?php

declare(strict_types=1);

namespace Mariusz\LogViewer\Tests\Controller;

use Mariusz\LogViewer\Controller\AppConfigController;
use Mariusz\LogViewer\Config\ConfigManager;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

class AppConfigControllerTest extends TestCase
{
    private AppConfigController $controller;
    private ConfigManager $configManager;
    private string $tempConfig;
    private string $tempEnv;

    protected function setUp(): void
    {
        $this->tempConfig = sys_get_temp_dir() . '/config_' . bin2hex(random_bytes(8)) . '.json';
        $this->tempEnv = sys_get_temp_dir() . '/env_' . bin2hex(random_bytes(8));

        $this->configManager = new ConfigManager($this->tempConfig, $this->tempEnv);
        $this->controller = new AppConfigController($this->configManager);
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

    public function testGetConfigReturnsPublicConfig(): void
    {
        $this->configManager->saveConfig([
            'app_name' => 'Test App',
            'ssh_password' => 'secret',
            'setup_complete' => true
        ]);

        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('GET', '/api/app-config');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->getConfig($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('Test App', $body['app_name']);
        $this->assertEquals('********', $body['ssh_password']);
    }

    public function testPatchConfigUpdatesConfig(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/app-config');
        $data = ['app_name' => 'Updated App'];
        $request = $request->withParsedBody($data);
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->patchConfig($request, $response);

        $this->assertEquals(200, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertTrue($body['success']);

        $config = $this->configManager->getConfig();
        $this->assertEquals('Updated App', $config['app_name']);
    }

    public function testPatchConfigWithInvalidJson(): void
    {
        $requestFactory = new RequestFactory();
        $request = $requestFactory->createRequest('POST', '/api/app-config');
        $responseFactory = new ResponseFactory();
        $response = $responseFactory->createResponse();

        $result = $this->controller->patchConfig($request, $response);

        $this->assertEquals(400, $result->getStatusCode());
        $body = json_decode((string)$result->getBody(), true);
        $this->assertEquals('invalid_json', $body['error']);
    }
}
