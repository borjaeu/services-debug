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
        $this->loadImports();
    }

    private function loadImports()
    {
        $ignoreSingleClasses = $this->configuration->get('ignore_simple_classes', false);

        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->rootDirectory . DIRECTORY_SEPARATOR . $this->configuration->get('source'));
        foreach ($this->configuration->getArray('ignored_dirs') as $dir) {
            $finder->exclude($dir);
        }
        $total = $finder->count();
        $count = 0;
        /** @var File $file */
        foreach ($finder as $file) {
            $fileInfo = new FileParser(file_get_contents($file->getRealPath()));
            echo $count++ . '/' . $total . ' ' . $file->getRealPath() . PHP_EOL;
            $metadata = $fileInfo->getMetadata();
            foreach ($metadata['import'] as $class) {
                if (empty($class)) {
                    print_r($metadata);
                }
                $class = ltrim($class, '\\');
                if (empty($metadata['class'])) {
                    print_r($metadata);
                    throw new \UnexpectedValueException("Unexpected class for " . $file->getRealPath());
                }
                if ($ignoreSingleClasses && strpos($class, '\\') === false) {
                    continue;
                }
                $this->dependenciesHolder->add(
                    $metadata['namespace'] . '\\' . $metadata['class'],
                    $class
                );
            }
        }
    }
}
