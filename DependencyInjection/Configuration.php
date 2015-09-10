<?php

namespace HTML2Canvas\ProxyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('html2_canvas_proxy');

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('exceptions')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('handler')
                            ->info('Define rather or not the handler must be active.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('config_proxy')
                    ->children()
                        ->scalarNode('image_path')
                            ->info('Define image path.')
                            ->isRequired()
                        ->end()
                        ->booleanNode('cross_domain')
                            ->info('Define if the cross domain is activated or not.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
