<?php
namespace Kizilare\ServicesDebug\Processor;

class FileParser
{
    const TOKEN = 0;
    const CODE = 1;
    const LINE = 2;

    /**
     * @var array
     */
    private $metadata;

    /**
     * @var array
     */
    private $tokens;

    /**
     * @var int
     */
    private $index = 0;

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
        $this->tokens = [];
        $tokens = token_get_all($code);
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $this->tokens[] = $token;
            }
        }
        while ($this->next()) {
            $this->processToken();
        }
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    private function next($step = 1)
    {
        $this->index += $step;

        return $this->index >= count($this->tokens) ? false : true;
    }

    private function prev()
    {
        $this->index--;
    }

    private function getTokenType()
    {
        return $this->tokens[$this->index][self::TOKEN];
    }

    private function getTokenName()
    {
        return token_name($this->tokens[$this->index][self::TOKEN]);
    }

    private function getTokenLine()
    {
        return $this->tokens[$this->index][self::LINE];
    }

    private function getTokenCode()
    {
        return $this->tokens[$this->index][self::CODE];
    }

    private function processToken()
    {
        switch ($this->getTokenType()) {
            case T_NAMESPACE:
                $this->metadata['namespace'] = $this->getInfo([T_STRING, T_NS_SEPARATOR], [T_WHITESPACE]);
                break;
            case T_USE:
                $this->metadata['import'][] = $this->getInfo([T_STRING, T_NS_SEPARATOR], [T_WHITESPACE]);
                break;
            case T_CLASS:
                $this->metadata['class'] = $this->getInfo([T_STRING], [T_WHITESPACE]);
                break;
            case T_OBJECT_OPERATOR:
                $this->processCall();
                break;
            case T_FUNCTION:
            case T_PUBLIC:
                $this->processMethod();
                break;
            case T_WHITESPACE:
                break;
            default:
                $this->debugToken('File, nothing to do');
        }
    }

    /**
     * @param array $infoTokens
     * @param array $allowedTokens
     * @return string
     */
    private function getInfo(array $infoTokens, array $allowedTokens)
    {
        $info = '';
        while ($this->next()) {
            if (in_array($this->getTokenType(), $infoTokens)) {
                $info .= $this->getTokenCode();
            } elseif (!in_array($this->getTokenType(), $infoTokens) && $info) {
                $this->prev();
                break;
            } elseif (!in_array($this->getTokenType(), $allowedTokens)) {
                break;
            }
        }

        return $info;
    }

    private function processMethod()
    {
        $visibility = 'public';
        $name = '';
        $finished = false;
        $this->prev();
        while ($this->next() && !$finished) {
            switch ($this->getTokenType()) {
                case T_STRING:
                    $name = $this->getTokenCode();
                    $finished = true;
                    break;
                case T_FUNCTION:
                case T_WHITESPACE:
                    break;
                case T_PUBLIC:
                    $visibility = 'public';
                    break;
                default:
                    $this->debugToken('Processing method name');
            }
        }

        $this->metadata['methods'][$visibility][] = $name;
    }

    private function processCall()
    {
        $this->prev();
        if ($this->getTokenCode() !== '$this') {
            $call = [
                'line' => $this->getTokenLine(),
                'variable' => $this->getTokenCode(),
            ];
            $this->debugToken('From this...');
            $this->next(2);
            $this->debugToken(' ...this is called');
            $call['method'] = $this->getTokenCode();
            $this->metadata['calls'][] = $call;
        } else {
            $this->next(2);
        }
    }

    private function debugToken($message)
    {
        if ($this->getTokenLine() > 195 && $this->getTokenLine() < 208) {
            printf('%s: %s [%s] %s %s', $this->getTokenLine(), $message, $this->getTokenName(), $this->getTokenCode(), PHP_EOL);
        }
    }
}
