<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class SourceProcessor
{
    /**
     * @var string
     */
    private $rootDirectory;

    /**
     * @var ConfigurationHelper
     */
    private $configuration;

    /**
     * @var DependenciesHolderHelper
     */
    private $dependenciesHolder;

    /**
     * @param string $rootDir
     */
    public function __construct($rootDir)
    {
        $this->rootDirectory = realpath($rootDir.'/../');
    }

    /**
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     * @return array
     */
    public function processSource(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder)
    {
        $this->configuration = $configuration;
        $this->dependenciesHolder = $dependenciesHolder;
        $this->loadMethods();
    }

    private function loadMethods()
    {
        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->rootDirectory . DIRECTORY_SEPARATOR . $this->configuration->getSource());
        foreach ($this->configuration->getIgnoredDirs() as $dir) {
            $finder->exclude($dir);
        }
        /** @var File $file */
        foreach ($finder as $file) {
            $fileInfo = new FileParser(file_get_contents($file->getRealPath()));
            $metadata = $fileInfo->getMetadata();
            foreach ($metadata['import'] as $class) {
                if (empty($class)) {
                    print_r($metadata);
                }
                if (empty($metadata['class'])) {
                    print_r($metadata);
                    throw new \UnexpectedValueException("Unexpected class for " . $file->getRealPath());
                }
                $this->dependenciesHolder->add(
                    $metadata['namespace'] . '\\' . $metadata['class'],
                    $class,
                    DependenciesHolderHelper::IMPORT
                );
            }
        }
    }
}
