<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class VendorProcessor
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
        $this->loadVendors();
    }

    private function loadVendors()
    {
        $finder = new Finder();
        $finder->files()->name('composer.json')->in($this->rootDirectory . DIRECTORY_SEPARATOR . 'vendor');
        $total = $finder->count();
        $count = 0;
        /** @var File $file */
        foreach ($finder as $file) {
            $info = json_decode(file_get_contents($file->getRealPath()), true);
            echo $count++ . '/' . $total . ' ' . $file->getRealPath() . PHP_EOL;
            if (!isset($info['name'])) {
                echo '   skipped' . PHP_EOL;
                continue;
            }

            $vendorName = $info['name'];
            $this->loadAutoload($vendorName, $info);
            $this->loadRequirements($vendorName, $info, 'require');
            $this->loadRequirements($vendorName, $info, 'require-dev');
        }
    }

    /**
     * @param string $vendorName
     * @param array $info
     * @param string $type
     */
    private function loadRequirements($vendorName, array $info, $type)
    {
        if (isset($info[$type])) {
            foreach ($info[$type] as $requiredVendor => $version) {
                $this->dependenciesHolder->addVendorRequirement($vendorName, $requiredVendor);
            }
        }
    }

    /**
     * @param string $vendorName
     * @param array $info
     */
    private function loadAutoload($vendorName, $info)
    {
        if (isset($info['autoload'])) {
            foreach ($info['autoload'] as $type => $namespaces) {
                foreach ($namespaces as $namespace => $source) {
                    if (!is_numeric($namespace)) {
                        $this->dependenciesHolder->addVendorNamespace($vendorName, $namespace);
                    }
                }
            }
        }
    }
}
