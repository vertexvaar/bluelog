<?php

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VerteXVaaR\BlueLog\DependencyInjection\InjectLoggerCompilerPass;

return static function (ContainerBuilder $container): void {
    $container->registerForAutoconfiguration(LoggerAwareInterface::class)
              ->addTag('vertexvaar.bluesprints.logger_aware');

    $container->addCompilerPass(new InjectLoggerCompilerPass('vertexvaar.bluesprints.logger_aware'));
};
