<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Yaml\Yaml;

class MethodsProcessor
{
    /**
     * @var string
     */
    private $rootDirectory;

    /**
     * @var array
     */
    private $methods;

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
    public function processSource(ConfigurationHelper $configuration)
    {
        $this->configuration = $configuration;
        $this->dependenciesHolder = $configuration;
        $this->loadMethods();
        $this->simplifyMethods();
        $this->writeDebug();
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
            if (isset($metadata['methods']['public'])) {
                foreach ($metadata['methods']['public'] as $method) {
                    $this->methods[$method]['owned_by'][] = $file->getRelativePath() . DIRECTORY_SEPARATOR . $file->getFilename();
                }
                foreach ($metadata['calls'] as $methodInfo) {
                    $method = $methodInfo['method'];
                    $this->methods[$method]['used_by'][] = $file->getRelativePath() . DIRECTORY_SEPARATOR . $file->getFilename();
                }
            }
        }
    }

    private function simplifyMethods()
    {
        foreach ($this->methods as $method => & $methodInfo) {
            if (!$this->isValidMethod($method)) {
                unset($this->methods[$method]);
            } elseif (isset($methodInfo['used_by']) && !isset($methodInfo['owned_by'])) {
                unset($this->methods[$method]);
            } elseif (isset($methodInfo['used_by']) && isset($methodInfo['owned_by'])) {
                unset($this->methods[$method]);
            } elseif (isset($methodInfo['used_by'])) {
                $methodInfo['used_by'] = array_unique($methodInfo['used_by']);
            }
        }
    }

    private function writeDebug()
    {
        $debug = Yaml::dump($this->methods, 4);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('methods_debug.yml', $debug);
    }

    private function isValidMethod($method)
    {
        if (in_array($method, $this->configuration->getAllowedMethods())) {
            return false;
        }
        foreach ($this->configuration->getAllowedMethodsRegExp() as $regExp) {
            if (preg_match( '/' .  $regExp .'/', $method)) {
                return false;
            }
        }

        return true;
    }
}
