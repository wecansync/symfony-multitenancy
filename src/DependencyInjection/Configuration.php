<?php


 namespace WeCanSync\MultiTenancyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Fouad Salkini <fouadsa91@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wecansync_multi_tenancy');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('tenant_entity')->defaultValue("App\Entity\Tenant")->end()
            ->end();

        return $treeBuilder;
    }

    
   
}
