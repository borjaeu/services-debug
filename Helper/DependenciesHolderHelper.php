<?php
namespace Kizilare\ServicesDebug\Helper;

use Kizilare\ServicesDebug\Exception\InvalidBundleForClassNameException;
use Kizilare\ServicesDebug\Exception\InvalidGroupForClassNameException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class DependenciesHolderHelper
{
    const INJECTION = 'injection';
    const IMPORT = 'import';

    const TYPE_VENDOR = 'vendor';
    const TYPE_BUNDLE = 'bundle';

    private $symfonyCachedClasses = [
        'Symfony\Component\DependencyInjection\ContainerInterface',
        'Symfony\Component\HttpFoundation\Request',
        'Symfony\Component\HttpFoundation\Response',
        'Symfony\Component\HttpKernel\Bundle\Bundle',
        'Symfony\Component\DependencyInjection\ContainerAwareInterface',
        'Symfony\Component\HttpKernel\HttpKernelInterface',
        'Symfony\Component\DependencyInjection\Container',
        'Symfony\Component\HttpKernel\HttpKernel',
    ];

    private $ignoredImports = [
        'Exception',
        '\Exception',
        'InvalidArgumentException',
        'DateTime',
        'ReflectionClass',
        'ReflectionMethod',
        'ArrayAccess',
        '\ArrayAccess',
    ];

    /**
     * @var array
     */
    private $dependencies;

    /**
     * @var string
     */
    private $vendorDirectory;

    /**
     * @var array
     */
    private $missingGroups = [];

    /**
     * @var array
     */
    private $missingFiles = [];

    /**
     * @param string $rootDir
     */
    public function __construct($rootDir, ReflectionHelper $reflectionHelper)
    {
        $this->vendorDirectory = realpath($rootDir.'/../vendor/');
        $this->reflectionHelper = $reflectionHelper;
    }

    public function load($file)
    {
        $this->dependencies = Yaml::parse(file_get_contents($file));
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $dependencyType
     */
    public function add($source, $target, $dependencyType)
    {
        if (in_array($target, $this->ignoredImports)) {
            return;
        }
        $this->dependencies[$source]['file'] = $this->trimFile($this->reflectionHelper->getClassFilename($source));
        try {
            list($sourceType, $sourceGroup) = $this->getGroup($source);
            $this->dependencies[$source]['group'] = $sourceGroup;
            $this->dependencies[$source]['type'] = $sourceType;
        } catch (\Exception $exception) {
            echo "[Dependencies] Source $source" . PHP_EOL;
        }
        try {
            list($targetType, $targetGroup) = $this->getGroup($target);
            $file = $this->reflectionHelper->getClassFilename($target);
            if (empty($file) && !in_array($target, $this->missingFiles)) {
                $this->missingFiles[] = $target;
                throw new \UnexpectedValueException("[Dependencies] File not found $target for $source");
            } else {
                $this->dependencies[$source]['dependencies'][$dependencyType][] = $target;
                $this->dependencies[$target]['file'] = $this->trimFile($file);
                $this->dependencies[$target]['type'] = $targetType;
                $this->dependencies[$target]['group'] = $targetGroup;
            }
        } catch (\Exception $exception) {
            if (!in_array($target, $this->missingGroups)) {
                throw new \UnexpectedValueException("[Dependencies] Group not found for $target in $source", 0, $exception);
                $this->missingGroups[] = $target;
            }
        }
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

    private function trimFile($file)
    {
        return str_replace(getcwd(), '', $file);
    }

    /**
     * @param string $className
     * @return string
     */
    public function getGroup($className)
    {
        if ($vendorName = $this->getVendorName($className)) {
            $identifier = $vendorName;
            $type = self::TYPE_VENDOR;
        } else {
            try {
                $identifier = $this->reflectionHelper->getBundleNamespace($className);
            } catch (InvalidBundleForClassNameException $exception) {
                throw new InvalidGroupForClassNameException("Can not determine group for $className", 0, $exception);
            }
            $type = self::TYPE_BUNDLE;
        }

        return [$type, $identifier];
    }

    /**
     * @param string $className
     * @return string
     */
    private function getVendorName($className)
    {
        if (in_array($className, $this->symfonyCachedClasses)) {
            return 'symfony/symfony';
        }

        return $this->reflectionHelper->getVendorName($className);
    }
}
