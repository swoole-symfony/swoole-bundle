<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test;

use Assert\Assertion;
use DateTimeImmutable;
use Exception;
use RuntimeException;
use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Common\Adapter\Swoole;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestAppKernel;
use SwooleBundle\SwooleBundle\Tests\Helper\SwooleFactoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class ServerTestCase extends KernelTestCase
{
    final public const FIXTURE_RESOURCES_DIR = __DIR__ . '/../../../resources';
    final public const SWOOLE_XDEBUG_CORO_WARNING_MESSAGE = 'go(): Using Xdebug in coroutines is extremely dangerous, '
        . 'please notice that it may lead to coredump!';
    private const COMMAND = './console';
    private const WORKING_DIRECTORY = __DIR__ . '/../../app';

    protected ?Swoole $swoole = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        // Make sure everything is stopped
        $this->killAllProcessesListeningOnPort(9999);
        sleep(self::coverageEnabled() ? 3 : 1);
    }

    public static function resolveEnvironment(?string $env = null): string
    {
        if (self::coverageEnabled()) {
            if ($env === 'test' || $env === null) {
                $env = 'cov';
            } elseif (mb_substr($env, -4, 4) !== '_cov') {
                $env .= '_cov';
            }
        }

        return $env ?? 'test';
    }

    public static function coverageEnabled(): bool
    {
        return getenv('COVERAGE') !== false;
    }

    public function runAsCoroutineAndWait(callable $callable): void
    {
        $coroutinePool = CoroutinePool::fromCoroutines($callable);

        try {
            $coroutinePool->run();
        } catch (RuntimeException $runtimeException) {
            if ($runtimeException->getMessage() !== self::SWOOLE_XDEBUG_CORO_WARNING_MESSAGE) {
                throw $runtimeException;
            }
        }
    }

    /**
     * Notice: This command requires running on os with "lsof" binary that supports "-i :PORT" option
     *         For example for alpine it is required to install it via: apk add lsof.
     */
    public function killAllProcessesListeningOnPort(int $port, int $timeout = 1): void
    {
        $listProcessesOnPort = new Process(['lsof', '-t', '-i', sprintf(':%d', $port)]);
        $listProcessesOnPort->setTimeout($timeout);
        $listProcessesOnPort->run();

        if (!$listProcessesOnPort->isSuccessful()) {
            return;
        }

        foreach (array_filter(explode(PHP_EOL, $listProcessesOnPort->getOutput())) as $processId) {
            $kill = new Process(['kill', '-9', $processId]);
            $kill->setTimeout($timeout);
            $kill->disableOutput();
            $kill->run();
        }
    }

    public function killProcessUsingSignal(int $pid, int $signal = SIGTERM, int $timeout = 1): void
    {
        $kill = new Process(['kill', sprintf('-%d', $signal), $pid]);
        $kill->setTimeout($timeout);
        $kill->disableOutput();
        $kill->run();
    }

    public function assertProcessSucceeded(Process $process): void
    {
        $status = $process->isSuccessful();
        if (!$status) {
            throw new ProcessFailedException($process);
        }

        self::assertTrue($status);
    }

    public function assertCommandTesterDisplayContainsString(string $expected, CommandTester $commandTester): void
    {
        self::assertStringContainsString(
            $expected,
            preg_replace('!\s+!', ' ', str_replace(PHP_EOL, '', $commandTester->getDisplay()))
        );
    }

    /**
     * @param array<string, string> $args
     * @param array<string, string> $envs
     */
    public function deferServerStop(array $args = [], array $envs = []): void
    {
        defer(function () use ($args, $envs): void {
            $this->serverStop($args, $envs);
        });
    }

    /**
     * @param array<string, string> $args
     * @param array<string, string> $envs
     */
    public function serverStop(array $args = [], array $envs = []): void
    {
        /** @var array<string, string> $processArgs */
        $processArgs = array_merge(['swoole:server:stop'], $args);
        $serverStop = $this->createConsoleProcess($processArgs, $envs);

        $serverStop->setTimeout(10);
        $serverStop->run();

        $this->assertProcessSucceeded($serverStop);
        self::assertStringContainsString('Swoole server shutdown successfully', $serverStop->getOutput());
    }

    /**
     * @param array<string, string> $args
     * @param array<string, string> $envs
     */
    public function createConsoleProcess(
        array $args,
        array $envs = [],
        mixed $input = null,
        ?float $timeout = 60.0,
    ): Process {
        $command = array_merge([self::COMMAND], $args);

        if (!array_key_exists('SWOOLE_TEST_XDEBUG_RESTART', $envs)) {
            if (self::coverageEnabled()) {
                $envs['COVERAGE'] = '1';
                $envs['APP_ENV'] = self::resolveEnvironment($envs['APP_ENV'] ?? null);

                if (!array_key_exists('APP_DEBUG', $envs) && $envs['APP_ENV'] === 'prod_cov') {
                    $envs['APP_DEBUG'] = '0';
                }
            }

            if (!array_key_exists('SWOOLE_ALLOW_XDEBUG', $envs)) {
                $envs['SWOOLE_ALLOW_XDEBUG'] = '1';
            }
        }

        return new Process($command, (string) realpath(self::WORKING_DIRECTORY), $envs, $input, $timeout);
    }

    public function assertHelloWorldRequestSucceeded(HttpClient $client): void
    {
        $response = $client->send('/')['response'];

        self::assertSame(200, $response['statusCode']);
        self::assertSame([
            'hello' => 'world!',
        ], $response['body']);
    }

    public function assertProcessFailed(Process $process): void
    {
        self::assertFalse($process->isSuccessful());
    }

    /**
     * @param array{
     *   environment?: string,
     *   debug?: bool,
     *   override_prod_env?: string,
     * } $options
     */
    // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
    protected static function createKernel(array $options = []): KernelInterface
    {
        if (static::$class === null) {
            static::$class = static::getKernelClass();
        }

        $options['environment'] = self::resolveEnvironment($options['environment'] ?? null);
        $env = $options['environment'];

        if (isset($options['debug'])) {
            $debug = $options['debug'];
        } elseif (isset($_ENV['APP_DEBUG'])) {
            $debug = $_ENV['APP_DEBUG'];
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $debug = $_SERVER['APP_DEBUG'];
        } else {
            $debug = true;
        }

        $overrideProdEnv = null;

        if (isset($options['override_prod_env'])) {
            $overrideProdEnv = $options['override_prod_env'];
        } elseif (isset($_SERVER['OVERRIDE_PROD_ENV'])) {
            $overrideProdEnv = $_SERVER['OVERRIDE_PROD_ENV'];
        }

        $kernel = new static::$class($env, $debug, $overrideProdEnv);

        Assertion::isInstanceOf($kernel, KernelInterface::class, 'Kernel class must implement KernelInterface');

        return $kernel;
    }

    protected static function getKernelClass(): string
    {
        return TestAppKernel::class;
    }

    protected function getSwoole(): Swoole
    {
        if ($this->swoole === null) {
            $this->swoole = SwooleFactoryFactory::newInstance();
        }

        return $this->swoole;
    }

    protected function markTestSkippedIfXdebugEnabled(): void
    {
        if (!extension_loaded('xdebug')) {
            return;
        }

        self::markTestSkipped(
            'Test is incompatible with Xdebug extension. Please disable it and try again. '
            . 'To generate code coverage use "pcov" extension.'
        );
    }

    protected function markTestSkippedIfInotifyDisabled(): void
    {
        if (extension_loaded('inotify')) {
            return;
        }

        self::markTestSkipped(
            'Swoole Bundle HMR requires "inotify" PHP extension present and installed on the system.'
        );
    }

    protected function markTestSkippedIfSymfonyVersionIsLoverThan(string $version): void
    {
        if (!version_compare(Kernel::VERSION, $version, 'lt')) {
            return;
        }

        self::markTestSkipped(sprintf('This test needs Symfony in version : %s.', $version));
    }

    /**
     * @param int<1, max> $factor
     */
    protected function generateUniqueHash(int $factor = 8): string
    {
        try {
            return bin2hex(random_bytes($factor));
        } catch (Exception) {
            $array = range(1, $factor * 2);
            shuffle($array);

            return implode('', $array);
        }
    }

    protected function currentUnixTimestamp(): int
    {
        return (new DateTimeImmutable())->getTimestamp();
    }

    protected function deleteVarDirectory(): void
    {
        $fs = new Filesystem();
        $fs->remove(self::WORKING_DIRECTORY . '/var');
    }

    protected function getVarDirectoryPath(): string
    {
        return self::WORKING_DIRECTORY . '/var';
    }
}
