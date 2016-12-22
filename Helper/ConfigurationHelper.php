<?php
namespace Kizilare\ServicesDebug\Helper;

use Kizilare\ServicesDebug\Exception\ConfigurationValueMissingException;
use Symfony\Component\Yaml\Yaml;

class ConfigurationHelper
{
    /**
     * @var array
     */
    private $configuration = [];

    /**
     * @param string $configurationFile
     */
    public function __construct($configurationFile)
    {
        if (!is_file($configurationFile)) {
            throw new \UnexpectedValueException("File not found '$configurationFile'");
        }
        $configuration = Yaml::parse(file_get_contents($configurationFile));
        $this->configuration = $configuration;
    }

    public function getArray($field, $default = null)
    {
        $values = $this->findValue($field);
        if (!is_array($values)) {
            $values = $this->handleDefault($field, $default);
        }

        return $values;
    }

    public function has($field, $value, $default = null)
    {
        $values = $this->findValue($field);
        if (!is_array($values)) {
            $this->handleDefault($field, $default === null ? null : []);

            return $default;
        }

        return in_array($value, $values);
    }

    /**
     * @param string $value Path to the value in the array.
     * @return mixed
     */
    private function findValue($value)
    {
        $path = explode('.', $value);
        $data = $this->configuration;
        while (!empty($path)) {
            $key = array_shift($path);
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /**
     * @param string $field
     * @param mixed $default
     * @return mixed
     * @throws ConfigurationValueMissingException
     */
    private function handleDefault($field, $default)
    {
        if ($default === null) {
            throw new ConfigurationValueMissingException("The field '$field' does not exist");
        }

        return $default;
    }
}
