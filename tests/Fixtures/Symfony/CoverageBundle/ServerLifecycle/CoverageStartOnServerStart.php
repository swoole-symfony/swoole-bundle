<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\ServerLifecycle;

use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\LifecycleHandler\ServerStartHandlerInterface;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\CodeCoverageManager;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\CoverageBundle\Coverage\NameGenerator;

final class CoverageStartOnServerStart implements ServerStartHandlerInterface
{
    public function __construct(
        private readonly CodeCoverageManager $codeCoverageManager,
        private readonly ?ServerStartHandlerInterface $decorated = null
    ) {
    }

    public function handle(Server $server): void
    {
        $this->codeCoverageManager->start(NameGenerator::nameForUseCase('test_server'));

        if ($this->decorated instanceof ServerStartHandlerInterface) {
            $this->decorated->handle($server);
        }
    }
}
