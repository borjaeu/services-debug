<?php
namespace Kizilare\ServicesDebug\Command;

use Kizilare\ServicesDebug\Helper\Dot;
use Kizilare\ServicesDebug\Helper\Graph;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;

class ServicesGraphCommand extends ContainerAwareCommand
{
    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    /**
     * @var Graph
     */
    protected $graph;

    /**
     * @var Dot
     */
    protected $dot;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var string
     */
    private $vendorDirectory;

    /**
     * @var array
     */
    private $namespaces = [];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('graph:services:debug')
            ->setDescription('Services and classes dependencies path');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rootDir = $this->getContainer()->getParameter('kernel.root_dir');
        $this->vendorDirectory = realpath($rootDir.'/../src/');
        $this->output = $output;
        $this->input = $input;
        $this->graph = new Graph();
        $this->dot = new Dot($this->graph);
        $this->runCommand();
        $this->writeGraph();
    }

    /**
     * {@inheritdoc}
     */
    private function runCommand()
    {
        $this->getContainerBuilder();
        $definitions = $this->containerBuilder->getDefinitions();
        $total = 0;
        $processed = 0;
        foreach ($definitions as $id => $definition) {
            $total++;
            if (!$this->isVendorService($definition)) {
                $processed++;
                $this->addService($definition, $id);
            }
        }
        $this->output->writeln(sprintf('Total %d services found. %d services processed', $total, $processed));
    }

    /**
     * @param Definition $definition
     * @param string $id
     */
    private function addService($definition, $id)
    {
        $identifier = $this->getBundleNamespace($definition->getClass());
        if (!isset($this->namespaces[$identifier])) {
            $this->namespaces[$identifier] = [];
        }
        $this->namespaces[$identifier][] = [
            'id'        => $id,
            'class'     => $definition->getClass(),
        ];
        $this->output->writeln(sprintf('<info>%s</info> %s [%s]', $id, $definition->getClass(), $identifier));
        $this->graph->addNode($identifier);
        $arguments = $definition->getArguments();
        foreach ($arguments as $argument) {
            if ($argument instanceof Reference) {
                /** @var Reference $argument */
                $argumentDefinition = $this->containerBuilder->findDefinition((string) $argument);
                if (!$this->isVendorService($argumentDefinition)) {
                    $this->graph->addEdge($identifier, $this->getBundleNamespace($argumentDefinition->getClass()));
                }
            }
        }

        $emptyNodes = $this->graph->getEmptyNodes();
        foreach ($emptyNodes as $emptyNode) {
            $this->graph->removeNode($emptyNode);
        }
    }

    /**
     * @param Definition $definition
     * @return bool
     */
    private function isVendorService(Definition $definition)
    {
        $className = $definition->getClass();
        $isVendor = false;
        try {
            $reflector = new ReflectionClass($className);
            if (strpos($reflector->getFileName(), $this->vendorDirectory) !== 0) {
                $isVendor = true;
            }
        } catch (\ReflectionException $exception) {
            $this->output->writeln(sprintf('<error>Invalid class %s</error>', $className));
            $isVendor = true;
        }

        return $isVendor;
    }

    /**
     * @param string $namespace
     * @param int $level
     * @return string
     */
    private function getBundleNamespace($namespace, $level = 3)
    {
        $namespace =  preg_replace('/(.*Bundle).*/', '$1', $namespace);
        $namespace = str_replace('\\', '\\\\', $namespace);

        return $namespace;
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     *
     * @throws \LogicException
     */
    protected function getContainerBuilder()
    {
        if ($this->containerBuilder) {
            return $this->containerBuilder;
        }

        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $this->containerBuilder = $container;
    }

    /**
     * Write graph information to file
     */
    protected function writeGraph()
    {
        $dotCode = $this->dot->getDotCode();
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('services.dot', $dotCode);
        $this->output->writeln("Built run 'dot services.dot -Tpng -o services.png' to view");
        shell_exec("dot services.dot -Tpng -o services.png");
    }
}
