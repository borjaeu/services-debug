<?php
namespace Kizilare\ServicesDebug\Helper;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Definition;

class DefinitionHelper
{
    /**
     * @var string
     */
    private $vendorDirectory;

    /**
     * @param string $rootDir
     */
    public function __construct($rootDir)
    {
        $this->vendorDirectory = realpath($rootDir.'/../vendor/');
    }

    /**
     * @param Definition $definition
     * @return string
     */
    public function getDefinitionIdentifier(Definition $definition)
    {
        if ($vendorName = $this->getVendorName($definition)) {
            return $vendorName;
        } else {
            return $this->getBundleNamespace($definition);
        }
    }

    /**
     * @param Definition $definition
     * @return string
     */
    public function getVendorName(Definition $definition)
    {
        $className = $definition->getClass();
        $vendorDirectory = '';
        try {
            $reflector = new ReflectionClass($className);
            if (strpos($reflector->getFileName(), $this->vendorDirectory) === 0) {
                $regularExpression = str_replace('/', '\/', $this->vendorDirectory);
                $vendorDirectory = preg_replace("/^$regularExpression\/([^\/]+\/[^\/]+).*$/", '$1', $reflector->getFileName());
            }
        } catch (\ReflectionException $exception) {
            $vendorDirectory = '';
        }

        return $vendorDirectory;
    }

    /**
     * @param Definition $definition
     * @return string
     */
    public function getBundleNamespace(Definition $definition)
    {
        $namespace =  preg_replace('/(.*Bundle).*/', '$1', $definition->getClass());
        $namespace = str_replace('\\', '\\\\', $namespace);

        return $namespace;
    }
}
