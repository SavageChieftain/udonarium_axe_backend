<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/appconfig_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        foreach (new DirectoryIterator($this->tempDir) as $item) {
            if (!$item->isDot() && $item->isFile()) {
                unlink($item->getPathname());
            }
        }
        rmdir($this->tempDir);
        putenv('ENV_PATH'); // unset
    }

    // ──────────────────────────────────────────────
    // fromEnv
    // ──────────────────────────────────────────────

    /** @return array<string, string> */
    private function validEnv(): array
    {
        return [
            'SKYWAY_APP_ID'               => 'app-id',
            'SKYWAY_SECRET'               => 'secret',
            'SKYWAY_UDONARIUM_LOBBY_SIZE' => '4',
            'ACCESS_CONTROL_ALLOW_ORIGIN' => 'https://example.com',
        ];
    }

    public function testFromEnvCreatesInstance(): void
    {
        $config = AppConfig::fromEnv($this->validEnv());
        $this->assertSame('app-id', $config->appId);
        $this->assertSame('secret', $config->secret);
        $this->assertSame(4, $config->lobbySize);
        $this->assertSame(['https://example.com'], $config->allowedOrigins);
    }

    public function testFromEnvDefaultsLobbySizeTo3(): void
    {
        $env = $this->validEnv();
        unset($env['SKYWAY_UDONARIUM_LOBBY_SIZE']);
        $config = AppConfig::fromEnv($env);
        $this->assertSame(3, $config->lobbySize);
    }

    public function testFromEnvThrowsWhenAppIdMissing(): void
    {
        $env = $this->validEnv();
        unset($env['SKYWAY_APP_ID']);
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnv($env);
    }

    public function testFromEnvThrowsWhenSecretMissing(): void
    {
        $env = $this->validEnv();
        unset($env['SKYWAY_SECRET']);
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnv($env);
    }

    public function testFromEnvParsesMultipleOrigins(): void
    {
        $env                              = $this->validEnv();
        $env['ACCESS_CONTROL_ALLOW_ORIGIN'] = 'https://a.com, https://b.com';
        $config = AppConfig::fromEnv($env);
        $this->assertContains('https://a.com', $config->allowedOrigins);
        $this->assertContains('https://b.com', $config->allowedOrigins);
    }

    public function testFromEnvThrowsWhenLobbySizeIsZero(): void
    {
        $env                              = $this->validEnv();
        $env['SKYWAY_UDONARIUM_LOBBY_SIZE'] = '0';
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnv($env);
    }

    public function testFromEnvThrowsWhenLobbySizeIsNegative(): void
    {
        $env                              = $this->validEnv();
        $env['SKYWAY_UDONARIUM_LOBBY_SIZE'] = '-1';
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnv($env);
    }

    public function testFromEnvThrowsWhenLobbySizeIsNonNumeric(): void
    {
        $env                              = $this->validEnv();
        $env['SKYWAY_UDONARIUM_LOBBY_SIZE'] = 'abc';
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnv($env);
    }

    // ──────────────────────────────────────────────
    // load
    // ──────────────────────────────────────────────

    public function testLoadThrowsWhenNoFileFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::load('/nonexistent/path/.env');
    }

    public function testLoadParsesValidEnvFile(): void
    {
        $envFile = $this->tempDir . '/.env';
        file_put_contents($envFile, "SKYWAY_APP_ID=my-app\nSKYWAY_SECRET=my-secret\n");

        $config = AppConfig::load($envFile);
        $this->assertSame('my-app', $config->appId);
        $this->assertSame('my-secret', $config->secret);
    }

    public function testLoadUsesFirstFoundCandidate(): void
    {
        $first  = $this->tempDir . '/first.env';
        $second = $this->tempDir . '/second.env';
        file_put_contents($first, "SKYWAY_APP_ID=first-app\nSKYWAY_SECRET=first-secret\n");
        file_put_contents($second, "SKYWAY_APP_ID=second-app\nSKYWAY_SECRET=second-secret\n");

        $config = AppConfig::load($first, $second);
        $this->assertSame('first-app', $config->appId);
    }

    public function testLoadSkipsNonExistentCandidates(): void
    {
        $envFile = $this->tempDir . '/.env';
        file_put_contents($envFile, "SKYWAY_APP_ID=my-app\nSKYWAY_SECRET=my-secret\n");

        $config = AppConfig::load('/nonexistent/.env', $envFile);
        $this->assertSame('my-app', $config->appId);
    }

    public function testLoadEnvPathTakesPriorityOverCandidates(): void
    {
        $priority = $this->tempDir . '/priority.env';
        $fallback = $this->tempDir . '/fallback.env';
        file_put_contents($priority, "SKYWAY_APP_ID=priority-app\nSKYWAY_SECRET=priority-secret\n");
        file_put_contents($fallback, "SKYWAY_APP_ID=fallback-app\nSKYWAY_SECRET=fallback-secret\n");

        putenv("ENV_PATH={$priority}");

        $config = AppConfig::load($fallback);
        $this->assertSame('priority-app', $config->appId);
    }

    public function testLoadPreservesStringValuesWithoutTypeConversion(): void
    {
        $envFile = $this->tempDir . '/.env';
        file_put_contents($envFile, "SKYWAY_APP_ID=app\nSKYWAY_SECRET=secret\nSKYWAY_UDONARIUM_LOBBY_SIZE=5\n");

        $config = AppConfig::load($envFile);
        $this->assertSame(5, $config->lobbySize);
    }
}
