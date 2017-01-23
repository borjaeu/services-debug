<?php
namespace Kizilare\ServicesDebug\Processor;

use Kizilare\ServicesDebug\Helper\ConfigurationHelper;
use Kizilare\ServicesDebug\Helper\DependenciesHolderHelper;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigurationHelper $configuration
     * @param DependenciesHolderHelper $dependenciesHolder
     * @param LoggerInterface $logger
     */
    public function __construct(ConfigurationHelper $configuration, DependenciesHolderHelper $dependenciesHolder, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $this->dependenciesHolder = $dependenciesHolder;
        $this->logger = $logger;
    }

    /**
     * Load the vendors dependencies
     */
    public function loadVendors()
    {
        $finder = new Finder();
        $finder->files()->name('composer.json')->in($this->configuration->get('vendor'));
        $total = $finder->count();
        $count = 0;
        /** @var File $file */
        foreach ($finder as $file) {
            $info = json_decode(file_get_contents($file->getRealPath()), true);
            if (!isset($info['name'])) {
                $this->logger->notice('   skipped');
                continue;
            }
            $vendorName = $info['name'];
            $autoloadNamespaces = $this->loadAutoload($vendorName, $info);
            $this->loadRequirements($vendorName, $info, 'require');
            $this->loadRequirements($vendorName, $info, 'require-dev');
            $this->logger->info(sprintf('%s/%s %d NS in %s ', $count++, $total, $autoloadNamespaces, $file->getRealPath()));
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
     * @return integer
     */
    private function loadAutoload($vendorName, $info)
    {
        $imported = 0;
        if (isset($info['autoload'])) {
            foreach ($info['autoload'] as $type => $namespaces) {
                foreach ($namespaces as $namespace => $source) {
                    if (!is_numeric($namespace)) {
                        $this->dependenciesHolder->addVendorNamespace($vendorName, $namespace);
                        $imported++;
                    }
                }
            }
        }

        return $imported;
    }
}
