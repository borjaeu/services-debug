<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Kizilare\ServicesDebug\Helper\Dot;
use Kizilare\ServicesDebug\Helper\Graph;
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
        $total = 0;
        $processed = 0;
        foreach ($this->dependencies as $className => $info) {
            $total++;
            if ($this->isAllowedDefinition($info)) {
                $processed++;
                $this->addFile($info);
            }
        }
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
        $this->addDependencies($info, 'import');
        $this->addDependencies($info, 'injection');
    }

    /**
     * @param array $info
     * @param string $type
     */
    private function addDependencies(array $info, $type)
    {
        if (!empty($info['dependencies'][$type])) {
            foreach ($info['dependencies'][$type] as $targetClass) {
                if (!isset($this->dependencies[$targetClass])) {
                    throw new \Exception("Can not find related class for $targetClass");
                } else {
                    if ($this->isAllowedDefinition($this->dependencies[$targetClass])) {
                        $this->addEdge($info['group'], $this->dependencies[$targetClass]['group']);
                    }
                }
            }
        }
    }

    /**
     * @param array $info
     * @return bool
     */
    private function isAllowedDefinition(array $info)
    {
        if ($this->skipVendors && $info['type'] == DependenciesHolderHelper::TYPE_VENDOR) {
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
