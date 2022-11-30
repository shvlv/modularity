<?php

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface;

interface ContainerExtensionInterface extends ContainerInterface
{
    public static function wrapContainer(ContainerInterface $container): self;
}
