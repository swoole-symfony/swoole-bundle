<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Upscale\Blackfire\Monitoring;

use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Configurator\ConfiguratorInterface;

final class WithApm implements ConfiguratorInterface
{
    public function __construct(private readonly Apm $apm)
    {
    }

    public function configure(Server $server): void
    {
        $this->apm->instrument($server);
    }
}
