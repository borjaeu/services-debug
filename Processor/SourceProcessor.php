<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class SourceProcessor
{
    /**
     * @var ConfigurationHelper
     */
    private $configuration;

    /**
     * @var DependenciesHolderHelper
     */
    private $dependenciesHolder;

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
        $this->dependenciesHolder = $dependenciesHolder;
        $this->logger = $logger;
    }

    public function loadImports()
    {
        $ignoreSingleClasses = $this->configuration->get('ignore_simple_classes', false);

        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->configuration->get('source'));
        foreach ($this->configuration->getArray('ignored_dirs') as $dir) {
            $finder->exclude($dir);
        }
        $total = $finder->count();
        $count = 0;
        /** @var File $file */
        foreach ($finder as $file) {
            $this->logger->info($count++ . '/' . $total . ' ' . $file->getRealPath());
            $fileInfo = new FileParser(file_get_contents($file->getRealPath()), 0, $this->logger);
            $metadata = $fileInfo->getMetadata();
            foreach ($metadata['import'] as $importedClass) {
                if (empty($importedClass)) {
                    $this->logger->warning(print_r($metadata, true));
                }
                $importedClass = ltrim($importedClass, '\\');
                if (empty($metadata['class'])) {
                    print_r($metadata);
                    throw new \UnexpectedValueException("Unexpected class for " . $file->getRealPath());
                }
                if ($ignoreSingleClasses && strpos($importedClass, '\\') === false) {
                    continue;
                }
                $this->dependenciesHolder->add(
                    $metadata['namespace'] . '\\' . $metadata['class'],
                    $importedClass
                );
            }
        }
    }
}
