<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
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
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     */
    public function __construct(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder)
    {
        $this->configuration = $configuration;
        $this->dependenciesHolder = $dependenciesHolder;
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
            $fileInfo = new FileParser(file_get_contents($file->getRealPath()), 0);
            echo $count++ . '/' . $total . ' ' . $file->getRealPath() . PHP_EOL;
            $metadata = $fileInfo->getMetadata();
            foreach ($metadata['import'] as $importedClass) {
                if (empty($importedClass)) {
                    print_r($metadata);
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
