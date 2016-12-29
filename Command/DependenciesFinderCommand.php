<?phpnamespace Kizilare\ServicesDebug\Command;use Kizilare\ServicesDebug\Helper\DefinitionHelper;use Kizilare\ServicesDebug\Helper\Dot;use Kizilare\ServicesDebug\Helper\Graph;use Kizilare\ServicesDebug\Processor\FileParser;use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Input\InputOption;use Symfony\Component\Console\Output\OutputInterface;use Symfony\Component\DependencyInjection\ContainerBuilder;use Symfony\Component\DependencyInjection\Definition;use Symfony\Component\DependencyInjection\Reference;use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;use Symfony\Component\Config\FileLocator;use Symfony\Component\Filesystem\Filesystem;use Symfony\Component\Yaml\Yaml;class DependenciesFinderCommand extends Command{    /**     * {@inheritdoc}     */    protected function configure()    {        $this            ->setName('kizilare:dependencies:finder')            ->addArgument('file', InputArgument::REQUIRED)            ->setDescription('Check dependencies for a given tree');    }    /**     * {@inheritdoc}     */    protected function execute(InputInterface $input, OutputInterface $output)    {        $file = $input->getArgument('file');        if (!is_file($file)) {            throw new \InvalidArgumentException($file);        }        $fileParser = new FileParser(file_get_contents($file));        print_r($fileParser->getMetadata());    }}