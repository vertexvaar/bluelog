<?php

declare(strict_types=1);

namespace VerteXVaaR\BlueLog\DependencyInjection;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use VerteXVaaR\BlueLog\LoggerFactory;

use function array_keys;
use function str_replace;

readonly class InjectLoggerCompilerPass implements CompilerPassInterface
{
    public function __construct(
        protected string $tagName,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $services = $container->findTaggedServiceIds($this->tagName);
        foreach (array_keys($services) as $service) {
            $definition = $container->findDefinition($service);

            if (!$definition->hasMethodCall('setLogger')) {
                $loggerDefinition = new Definition(LoggerInterface::class, []);
                $loggerDefinition->setFactory([new Reference(LoggerFactory::class), 'create']);
                $loggerDefinition->setArguments([str_replace(['\\', '/'], '.', $service)]);
                $definition->addMethodCall('setLogger', [$loggerDefinition]);
            }
        }
    }
}
