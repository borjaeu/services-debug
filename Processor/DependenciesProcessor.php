<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Kizilare\ServicesDebug\Helper\Dot;
use Kizilare\ServicesDebug\Helper\Graph;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class DependenciesProcessor
{
    /**
     * @var ConfigurationHelper
     */
    private $configuration;

    /**
     * @var array
     */
    private $dependencies;

    /**
     * @var array
     */
    private $reverseDependencies;

    /**
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     * @return array
     */
    public function build(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder)
    {
        $this->configuration = $configuration;
        $this->dependencies = $dependenciesHolder->getDependencies();
        $this->processDependencies();
        $this->writeToFile();
    }

    /**
     * {@inheritdoc}
     */
    private function processDependencies()
    {
        foreach ($this->dependencies as $className => $info) {
            $this->addDependencies($className, $info, 'import');
            $this->addDependencies($className, $info, 'injection');
        }
    }

    private function addDependencies($source, array $info, $type)
    {
        if (!isset($info['dependencies'][$type])) {
            return;
        }

        foreach ($info['dependencies'][$type] as $className) {
            if (!isset($this->dependencies[$className]['group'])) {
                var_dump($className);
                exit;
            }
            if (!isset($info['group'])) {
                var_dump($info);
                var_dump($source);
                exit;
            }
            $id = $info['group'] . ' -> ' . $this->dependencies[$className]['group'];
            $this->reverseDependencies[$id][] = $source . ' by ' . $type . ' ' . $className;
        }
    }

    public function writeToFile()
    {
        $debug = Yaml::dump($this->reverseDependencies, 4);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('dependencies_reversed.yml', $debug);
    }
}
