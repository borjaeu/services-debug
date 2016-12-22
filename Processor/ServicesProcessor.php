<?phpnamespace Kizilare\ServicesDebug\Processor;use Kizilare\ServicesDebug\Helper\ConfigurationHelper;use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;use Kizilare\ServicesDebug\Helper\ReflectionHelper;use Kizilare\ServicesDebug\Exception\InvalidServiceArgumentException;use Symfony\Component\DependencyInjection\ContainerBuilder;use Symfony\Component\DependencyInjection\Definition;use Symfony\Component\DependencyInjection\Reference;use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;use Symfony\Component\Config\FileLocator;class ServicesProcessor{    /**     * @var ContainerBuilder     */    private $containerBuilder;    /**     * @var ConfigurationHelper     */    private $configuration;    /**     * @var DependenciesHolderHelper     */    private $dependencies;    /**     * @var ReflectionHelper     */    private $reflectionHelper;    /**     * ServicesProcessor constructor.     * @param string $cachedFile     * @param ReflectionHelper $reflectionHelper     */    public function __construct($cachedFile, ReflectionHelper $reflectionHelper)    {        $this->loadContainerBuilder($cachedFile);        $this->reflectionHelper = $reflectionHelper;    }    public function loadServices(ConfigurationHelper $configuration, DependenciesHolderHelper $dependencies)    {        $this->configuration = $configuration;        $this->dependencies = $dependencies;        $this->runCommand();    }    /**     * {@inheritdoc}     */    private function runCommand()    {        $definitions = $this->containerBuilder->getDefinitions();        $total = 0;        $processed = 0;        foreach ($definitions as $serviceId => $definition) {            $total++;            if ($this->isParsedService($serviceId, $definition)) {                $processed++;                $this->addService($definition);            }        }    }    /**     * @param Definition $definition     */    private function addService(Definition $definition)    {        $className = $definition->getClass();        $arguments = $definition->getArguments();        foreach ($arguments as $argument) {            if ($argument instanceof Reference) {                /** @var Reference $argument */                $serviceName = (string) $argument;                $argumentDefinition = $this->containerBuilder->findDefinition($serviceName);                if (!$this->isAllowedDefinition($argument, $argumentDefinition)) {                    continue;                }                if ($this->configuration->has("services.ignored_dependencies.$serviceName", $className, false)) {                    continue;                }                if (empty($argumentDefinition->getClass())) {                    throw new InvalidServiceArgumentException(                        "[ServicesProcessor] Class '$className' has an invalid dependency '$serviceName'. 'services.ignored_dependencies.$serviceName"                    );                }                $this->dependencies->add($className, $argumentDefinition->getClass(), DependenciesHolderHelper::INJECTION);            }        }    }    /**     * @param string $serviceId     * @param Definition $definition     * @return bool     */    private function isParsedService($serviceId, Definition $definition)    {        $vendorName = $this->reflectionHelper->getVendorName($definition->getClass());        if ($vendorName) {            return false;        }        return $this->isAllowedDefinition($serviceId, $definition);    }    /**     * @param string $serviceId     * @param Definition $definition     * @return bool     */    private function isAllowedDefinition($serviceId, Definition $definition)    {        if (in_array($serviceId, $this->configuration->getArray('services.ignored'))) {            return false;        }        return true;    }    /**     * Loads the ContainerBuilder from the cache.     *     * @param string $cachedFile     * @return ContainerBuilder     */    private function loadContainerBuilder($cachedFile)    {        $container = new ContainerBuilder();        $loader = new XmlFileLoader($container, new FileLocator());        $loader->load($cachedFile);        return $this->containerBuilder = $container;    }}