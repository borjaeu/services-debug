<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
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
            $this->addDependencies($className, $info);
        }
    }

    private function addDependencies($source, array $info)
    {
        if (!isset($info['dependencies'])) {
            return;
        }
        foreach ($info['dependencies'] as $className) {
            if (!isset($this->dependencies[$className]['group'])) {
                var_dump($className);
                exit;
            }
            if (!isset($this->dependencies[$className]['file'])) {
                var_dump($className);
                exit;
            }
            if (!isset($info['group'])) {
                var_dump($info);
                var_dump($source);
                exit;
            }
            if ($info['group'] != $this->dependencies[$className]['group']) {
                $sourceGroup = $info['group'];
                $target = $this->dependencies[$className]['group'];
                $this->reverseDependencies[$sourceGroup][$target][] = [
                    'source' => $source,
                    'target' => $className,
                    'file' => $info['file'],
                ];
            }
        }
    }

    public function writeToFile()
    {
        $html = <<<HTML
<!DOCTYPE>
<html>
<head>
<title>Dependencies reversed</title>
<style>
    * { font-family:courier,monospace; font-size:11px; }
    h2 { background-color: lightgrey; }
</style>
</head>
<body>
HTML;
        foreach ($this->reverseDependencies as $source => $targets) {
            $html .= <<<HTML
<h2>$source</h2>

HTML;
            foreach ($targets as $target => $dependencies) {
                $html .= <<<HTML
<h3>$target</h3>
<ul>

HTML;
                foreach ($dependencies as $dependency) {
                    $html .= <<<HTML
    <li>{$dependency['target']} in <a href="phpstorm://open?file=/Users/user/local-env/backend/unicorn/{$dependency['file']}">{$dependency['source']}</a></li>

HTML;
                }
                $html .= <<<HTML
</ul>

HTML;

            }
        }

        $html .= <<<HTML

</body>
</html>
HTML;


        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('dependencies_reversed.html', $html);
    }
}
