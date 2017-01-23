<?phpnamespace Kizilare\ServicesDebug\Command;use Kizilare\ServicesDebug\Helper\ConfigurationHelper;use Kizilare\ServicesDebug\Processor\FileParser;use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;use Symfony\Component\Console\Command\Command;use Symfony\Component\Console\Input\InputArgument;use Symfony\Component\Console\Input\InputInterface;use Symfony\Component\Console\Logger\ConsoleLogger;use Symfony\Component\Console\Output\OutputInterface;class FileParseCommand extends Command{    /**     * {@inheritdoc}     */    protected function configure()    {        $this            ->setName('file:parse')            ->addArgument('file', InputArgument::REQUIRED, 'File to parse')            ->setDescription('Services and classes dependencies path');    }    /**     * {@inheritdoc}     */    protected function execute(InputInterface $input, OutputInterface $output)    {        $logger = new ConsoleLogger($output);        $output->writeln('Parsing file');        $fileInfo = new FileParser(file_get_contents($input->getArgument('file')), 0, $logger);        print_r($fileInfo->getMetadata());    }}