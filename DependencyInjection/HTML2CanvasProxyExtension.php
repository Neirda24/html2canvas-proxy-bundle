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

        $this->loadConfigApi($config, $container, 'api3a');
        $this->loadConfigApi($config, $container, 'cloud');

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('cache-warmer.xml');
        $loader->load('services.xml');
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     * @param string           $configName
     */
    protected function loadConfigApi(array $config, ContainerBuilder $container, $configName)
    {
        $container->setParameter('api_client.'.$configName.'.config.api_url', $config['config'][$configName]['base_url']);
        $container->setParameter('api_client.'.$configName.'.config.token_value', $config['config'][$configName]['token']);
        $container->setParameter('api_client.'.$configName.'.config.debug', $config['config'][$configName]['debug']);
        $container->setParameter('api_client.'.$configName.'.api_uris', $config['config'][$configName]['api_uris']);
        $container->setParameter('api_client.'.$configName.'.config.prefix_url', $config['config'][$configName]['prefix_url']);
    }
}
