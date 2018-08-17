<?php

namespace Os2Display\CampaignBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Os2Display\CoreBundle\DependencyInjection\Os2DisplayBaseExtension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\FileLocator;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class Os2DisplayCampaignExtension extends Os2DisplayBaseExtension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->dir = __DIR__;

        parent::load($configs, $container);

        // If test environment, inject mocks.
        if ($container->getParameter('kernel.environment') == 'acceptance') {
            $loader = new Loader\YamlFileLoader($container, new FileLocator($this->dir . '/../Resources/config'));

            $loader->load('services_acceptance.yml');
        }

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $def = $container->getDefinition('os2display.middleware.service');

        if (isset($config['cache_ttl'])) {
            $def->replaceArgument(3, $config['cache_ttl']);
        }
    }
}
