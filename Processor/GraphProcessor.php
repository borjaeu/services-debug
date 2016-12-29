<?php
namespace Kizilare\ServicesDebug\Processor;

use Crosslend\ProviderBundle\Entity\Biw\validateCredentialRequest;
use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Kizilare\ServicesDebug\Helper\Dot;
use Kizilare\ServicesDebug\Helper\Graph;
use Pablodip\ModuleTestBundle\Tests\Module\PagerfantaControllerTest;
use Symfony\Component\Filesystem\Filesystem;

class GraphProcessor
{
    /**
     * @var ConfigurationHelper
     */
    private $configuration;

    /**
     * @var Graph
     */
    private $graph;

    /**
     * @var Dot
     */
    private $dot;

    /**
     * @var array
     */
    private $dependencies;

    /**
     * @var array
     */
    private $edgesDebug = [];

    /**
     * @var bool
     */
    private $skipVendors;

    /**
     * @var string
     */
    private $outputFile;

    /**
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     * @return array
     */
    public function build(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder)
    {
        $this->configuration = $configuration;
        $this->dependencies = $dependenciesHolder->getDependencies();
        $this->skipVendors = false;
        $this->outputFile = 'dependencies_vendors';
        $this->buildGraph();
        $this->skipVendors = true;
        $this->outputFile = 'dependencies_bundles';
        $this->buildGraph();
    }

    /**
     * {@inheritdoc}
     */
    private function buildGraph()
    {
        $this->graph = new Graph();
        $this->dot = new Dot($this->graph);
        foreach ($this->dependencies['source'] as $className => $info) {
            $this->addFile($info);
        }
        /*if (!$this->skipVendors) {
            foreach ($this->dependencies['vendor'] as $vendor => $info) {
                $this->addVendor($vendor, $info);
            }
        }*/
        $this->clearIsolatedNodes();
        $this->writeGraph();
    }

    /**
     * @param string $sourceClass
     * @param array $info
     */
    private function addFile(array $info)
    {
        $this->graph->addNode($info['group']);
        $this->dot->setNodeOptions($info['group'], ['border' => 'red']);
        if (!empty($info['dependencies'])) {
            foreach ($info['dependencies'] as $targetGroup) {
                if ($this->isAllowedDefinition($targetGroup)) {
                    $this->addEdge($info['group'], $targetGroup);
                }
            }
        }
    }

    /**
     * @param string $vendor
     * @param array $info
     */
    private function addVendor($vendor, array $info)
    {
        $this->graph->addNode($vendor);
        if (!empty($info['requires'])) {
            foreach ($info['requires'] as $targetVendor) {
                $this->addEdge($vendor, $targetVendor);
            }
        }
    }

    /**
     * @param string $target
     * @return bool
     */
    private function isAllowedDefinition($target)
    {
        if ($this->skipVendors && isset($this->dependencies['vendor'][$target])) {
            return false;
        }

        return true;
    }

    /**
     * Write graph information to file
     */
    private function writeGraph()
    {
        $dotCode = $this->dot->getDotCode();
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($this->outputFile . '.dot', $dotCode);
        $command = "dot {$this->outputFile}.dot -Tpng -o {$this->outputFile}.png";
        shell_exec($command);
    }

    private function clearIsolatedNodes()
    {
        $emptyNodes = $this->graph->getEmptyNodes();
        foreach ($emptyNodes as $emptyNode) {
            echo "Empty " . $emptyNode . PHP_EOL;
            $this->graph->removeNode($emptyNode);
        }
    }

    /**
     * @param string $source
     * @param string $target
     */
    private function addEdge($source, $target)
    {
        $edgeId = $source . ' -> ' . $target;
        if (!isset($this->edgesDebug[$edgeId])) {
            $this->edgesDebug[$edgeId] = 0;
        }
        $this->edgesDebug[$edgeId]++;
        $this->graph->addEdge($source, $target);
        $this->dot->setEdgeOptions($source, $target, ['label' => 'x' . $this->edgesDebug[$edgeId]]);
    }
}
