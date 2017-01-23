<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     */
    public function __construct(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $this->dependencies = $dependenciesHolder->getDependencies();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function processDependencies()
    {
        foreach ($this->dependencies['source'] as $className => $info) {
            $this->addDependencies($className, $info);
        }
        $this->writeToFile();
    }

    private function addDependencies($source, array $info)
    {
        if (!isset($info['dependencies'])) {
            return;
        }
        foreach ($info['dependencies'] as $targetGroup) {
            if (!isset($info['group'])) {
                var_dump($info);
                var_dump($source);
                exit;
            }
            if ($info['group'] != $targetGroup) {
                $sourceGroup = $info['group'];
                $this->reverseDependencies[$sourceGroup][$targetGroup][] = [
                    'source' => $source,
                    'target' => $targetGroup,
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
    h2 { background-color: lightgrey; clear: both; }
    h3 { margin: 0; }
    p { margin: 0; background-color: lightyellow; padding: 2px; }
</style>
</head>
<body>

HTML;
        foreach ($this->reverseDependencies as $source => $targets) {
            $html .= <<<HTML
<h2>$source dependencies</h2>
<div>

HTML;
            foreach ($targets as $target => $dependencies) {
                $html .= <<<HTML
    <h3>$target uses</h3>
    <ul>

HTML;
                foreach ($dependencies as $dependency) {
                    $html .= <<<HTML
        <li>{$dependency['target']} in {$dependency['source']}</li>

HTML;
                }
                $html .= <<<HTML
    </ul>

HTML;

            }
            $html .= <<<HTML
</div>

HTML;

        }

        $html .= <<<HTML

</body>
</html>
HTML;
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('dependencies_reversed.html', $html);
    }
}
