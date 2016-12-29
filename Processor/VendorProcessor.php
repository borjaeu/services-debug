<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

class VendorProcessor
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
        $this->loadVendors();
    }

    public function loadVendors()
    {
        $finder = new Finder();
        $finder->files()->name('composer.json')->in($this->configuration->get('vendor'));
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
