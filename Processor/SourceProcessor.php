<?php
namespace Kizilare\ServicesDebug\Processor;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Yaml\Yaml;

class SourceProcessor
{
    /**
     * @var string
     */
    private $rootDirectory;

    private $methods;

    private $calls;

    /**
     * @param string $rootDir
     */
    public function __construct($rootDir)
    {
        $this->rootDirectory = realpath($rootDir.'/../');
    }

    /**
     * @param $source
     * @return array
     */
    public function processSource($source, $ignoreDirs)
    {
        $this->loadMethods($source, $ignoreDirs);
        $this->simplifyMethods();

        $this->writeDebug();
    }

    /**
     * @param $source
     */
    private function loadMethods($source, $ignoreDirs)
    {
        $finder = new Finder();
        $finder->files()->name('*.php')->in($this->rootDirectory . DIRECTORY_SEPARATOR . $source);
        foreach ($ignoreDirs as $dir) {
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
        if (strpos($method, 'test') === 0) {
            return false;
        }

        return true;
    }
}
