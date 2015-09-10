<?php

namespace HTML2Canvas\ProxyBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class HTML2CanvasProxyExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadConfigApi($config, $container);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        if (true === $config['exceptions']['handler']) {
            $loader->load('exception_handlers.xml');
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadConfigApi(array $config, ContainerBuilder $container)
    {
        $container->setParameter('html2canvas_proxy.config_proxy.images_path', $config['config_proxy']['images_path']);
        $container->setParameter('html2canvas_proxy.config_proxy.cross_domain', $config['config_proxy']['cross_domain']);
        $container->setParameter('html2canvas_proxy.config_proxy.screen_path', $config['config_proxy']['screen_path']);
    }
}
