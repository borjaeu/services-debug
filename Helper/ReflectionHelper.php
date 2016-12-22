<?php
namespace Kizilare\ServicesDebug\Helper;

use Kizilare\ServicesDebug\Exception\InvalidBundleForClassNameException;
use ReflectionClass;

class ReflectionHelper
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
     * @param string $className
     * @return string
     */
    public function getClassFilename($className)
    {
        try {
            $reflector = new ReflectionClass($className);
            $filename = $reflector->getFileName();
        } catch (\ReflectionException $exception) {
            $filename = false;
        }

        return $filename;
    }

    /**
     * @param string $className
     * @return string
     */
    public function getVendorName($className)
    {
        $vendorDirectory = '';
        $filename = $this->getClassFilename($className);
        if ($filename) {
            if (strpos($filename, $this->vendorDirectory) === 0) {
                $regularExpression = str_replace('/', '\/', $this->vendorDirectory);
                $vendorDirectory = preg_replace("/^$regularExpression\/([^\/]+\/[^\/]+).*$/", '$1', $filename);
            }
        } else {
            $vendorDirectory = '';
        }

        return $vendorDirectory;
    }


    /**
     * @param string $className
     * @return string
     * @throws \Exception
     */
    public function getBundleNamespace($className)
    {
        $namespace =  preg_replace('/(.*?Bundle).*/', '$1', $className);
        if ($className == $namespace) {
            throw new InvalidBundleForClassNameException("Invalid bundle for $className");
        }
        $namespace = str_replace('\\', '\\\\', $namespace);

        return $namespace;
    }
}
