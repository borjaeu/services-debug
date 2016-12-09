<?php
namespace Kizilare\ServicesDebug\Processor;

class FileParser
{
    const TOKEN = 0;
    const CODE = 1;
    const LINE = 2;

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @param string $code
     */
    public function __construct($code)
    {
        $this->metadata = [
            'namespace' => '',
            'class' => '',
            'import' => [],
            'methods' => [],
        ];
        $this->status = 'File';
        $tokens = token_get_all($code);
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $this->processToken($token);
            }
        }
    }

    /**
     * @param array $token
     */
    private function processToken(array $token)
    {
        switch ($this->status) {
            case 'File':
                $this->processFileToken($token);
                break;
            case 'Namespace':
                $this->processNamespaceToken($token);
                break;
            case 'Import':
                $this->processImportToken($token);
                break;
            case 'Class':
                $this->processClassToken($token);
                break;
            case 'Method':
                $this->processMethodToken($token);
                break;
            default:
                throw new \UnexpectedValueException("Invalid '{$this->status}' value found for status");
        }
    }

    /**
     * @param array $token
     */
    private function processFileToken(array $token)
    {
        switch ($token[self::TOKEN]) {
            case T_NAMESPACE:
                $this->moveTo('Namespace');
                break;
            case T_USE:
                $this->moveTo('Import');
                break;
            case T_CLASS:
                $this->moveTo('Class');
                break;
            case T_FUNCTION:
                $this->moveTo('Method');
                break;
            case T_PUBLIC:
                $this->moveTo('Method');
                $this->type = 'Public';
                break;
            default:
                $this->debugToken($token, 'File, nothing to do');
        }
    }

    /**
     * @param array $token
     */
    private function processNamespaceToken(array $token)
    {
        if (in_array($token[self::TOKEN], [T_STRING, T_NS_SEPARATOR])) {
            $this->buffer .= $token[self::CODE];
        } elseif ($token[self::TOKEN] !== T_WHITESPACE) {
            $this->metadata['namespace'] = $this->getBuffer();
            $this->status = 'File';
        }
    }

    /**
     * @param array $token
     */
    private function processImportToken(array $token)
    {
        if (in_array($token[self::TOKEN], [T_STRING, T_NS_SEPARATOR])) {
            $this->buffer .= $token[self::CODE];
        } elseif (in_array($token[self::TOKEN], [T_WHITESPACE, T_USE]) && $this->buffer) {
            $this->metadata['import'][] = $this->getBuffer();
        } elseif (!in_array($token[self::TOKEN], [T_WHITESPACE, T_USE])) {
            $this->debugToken($token, 'Finished import');
            $this->moveTo('File');
            $this->processFileToken($token);
        }
    }

    /**
     * @param array $token
     */
    private function processClassToken(array $token)
    {
        if ($token[self::TOKEN] !== T_STRING && $this->buffer) {
            $this->metadata['class'] = $this->getBuffer();
            $this->debugToken($token, 'Finished class');
            $this->moveTo('File');
        } elseif ($token[self::TOKEN] == T_STRING) {
            $this->buffer .= $token[self::CODE];
        } elseif ($token[self::TOKEN] !== T_WHITESPACE) {
            $this->debugToken($token, 'Finished class');
            $this->moveTo('File');
        }
    }
    /**
     * @param array $token
     */
    private function processMethodToken(array $token)
    {
        if ($token[self::TOKEN] !== T_STRING && $this->buffer) {
            $this->metadata['methods'][$this->type][] = $this->getBuffer();
            $this->debugToken($token, 'Finished method');
            $this->moveTo('File');
        } elseif ($token[self::TOKEN] == T_STRING) {
            $this->buffer .= $token[self::CODE];
        } elseif ($token[self::TOKEN] !== T_WHITESPACE) {
            $this->debugToken($token, 'Finished method no buffer');
            $this->status = 'File';
            $this->processFileToken($token);
        }
    }

    private function debugToken(array $token, $message)
    {
        if ($token[self::LINE] > 203 && $token[self::LINE] < 220) {
            printf('%s: %s [%s] %s %s', $token[self::LINE], $message, token_name($token[self::TOKEN]), $token[self::CODE], PHP_EOL);
        }
    }

    /**
     * @return string
     */
    private function getBuffer()
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }

    private function moveTo($status)
    {
        printf('Moving to %s%s', $status, PHP_EOL);
        $this->status = $status;
    }
}
