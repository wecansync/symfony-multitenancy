<?php

namespace WeCanSync\MultiTenancyBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Fouad Salkini <fouad@wecansync.com>
 */
class WeCanSyncMultiTenancyBundle extends Bundle
{
    /**
     * Returns the bundle's container extension.
     *
     * @throws \LogicException
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new DependencyInjection\WeCanSyncMultiTenancyExtension();
        }
        return $this->extension ?: null;
    }
}