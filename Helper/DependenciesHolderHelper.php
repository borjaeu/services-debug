<?php
namespace Kizilare\ServicesDebug\Helper;

use Kizilare\ServicesDebug\Exception\InvalidBundleForClassNameException;
use Kizilare\ServicesDebug\Exception\InvalidGroupForClassNameException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class DependenciesHolderHelper
{
    /**
     * @var array
     */
    private $dependencies = [
        'source' => [],
        'vendor' => [],
    ];

    /**
     * @var ConfigurationHelper
     */
    private $configuration;

    /**
     * @var array
     */
    private $namespaceToVendor = [];

    /**
     * @param ConfigurationHelper $configuration
     */
    public function __construct(ConfigurationHelper $configuration)
    {
        $this->configuration = $configuration;
    }

    public function load($file)
    {
        $this->dependencies = Yaml::parse(file_get_contents($file));
    }

    /**
     * @param string $source
     * @param string $target
     */
    public function add($source, $target)
    {
        try {
            $sourceGroup = $this->getGroup($source);
            $this->dependencies['source'][$source]['group'] = $sourceGroup;
        } catch (\Exception $exception) {
            echo "[Dependencies] Source $source" . PHP_EOL;
        }
        try {
            $targetGroup = $this->getGroup($target);
            $this->dependencies['source'][$source]['dependencies'][] = $targetGroup;
            $this->dependencies['source'][$source]['dependencies'] = array_values(array_unique($this->dependencies['source'][$source]['dependencies']));
        } catch (\Exception $exception) {
            throw new \UnexpectedValueException("[Dependencies] Group not found for $target in $source", 0, $exception);
        }
    }

    /**
     * @param string $source
     * @param string $target
     */
    public function addVendorRequirement($source, $target)
    {
        if (!in_array($target, ['php'])) {
            $this->dependencies['vendor'][$source]['requires'][] = $target;
        }
    }

    /**
     * @param string $source
     * @param string $namespace
     */
    public function addVendorNamespace($source, $namespace)
    {
        $namespace = ltrim($namespace, '\\');
        $this->dependencies['vendor'][$source]['namespaces'][] = $namespace;
        $this->namespaceToVendor[$namespace] = $source;
        $this->dependencies['vendor'][$source]['namespaces'] = array_unique($this->dependencies['vendor'][$source]['namespaces']);
    }

    /**
     * @return array
     */
    public function getDependencies()
    {
        return $this->dependencies;
    }

    public function writeDebug()
    {
        $debug = Yaml::dump($this->dependencies, 4);
        $fileSystem = new Filesystem();
        $fileSystem->dumpFile('dependencies.yml', $debug);
    }

    /**
     * @param string $className
     * @return array
     * @throws InvalidGroupForClassNameException
     */
    public function getGroup($className)
    {
        if ($vendorName = $this->getVendorName($className)) {
            $identifier = $vendorName;
        } else {
            try {
                $identifier = $this->getBundleNamespace($className);
            } catch (InvalidBundleForClassNameException $exception) {
                throw new InvalidGroupForClassNameException("Can not determine group for $className", 0, $exception);
            }
        }

        return $identifier;
    }

    /**
     * @param string $className
     * @return string
     */
    private function getVendorName($className)
    {
        $vendorName = $this->configuration->get('services.alias.' . $className, false);
        if ($vendorName) {
            return $vendorName;
        }

        foreach ($this->namespaceToVendor as $namespace => $vendor) {
            if (strpos($className, $namespace) === 0) {
                return $vendor;
            }
        }

        return false;
    }

    /**
     * @param string $className
     * @return string
     * @throws \Exception
     */
    private function getBundleNamespace($className)
    {
        $namespace =  preg_replace('/(.*?Bundle).*/', '$1', $className);
        if ($className == $namespace) {
            throw new InvalidBundleForClassNameException("Invalid bundle for $className");
        }
        $namespace = str_replace('\\', '\\\\', $namespace);

        return $namespace;
    }
}
