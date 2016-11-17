<?php
namespace Kizilare\ServicesDebug\Helper;

class Dot
{
    /**
     * @var Graph
     */
    protected $graph;

    /**
     * @var array
     */
    protected $edges = [];

    /**
     * @var array
     */
    protected $nodes = [];

    /**
     * @var bool
     */
    protected $debug;

    /**
     * @param Graph $graph
     */
    public function __construct(Graph $graph)
    {
        $this->graph = $graph;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param string $source
     * @param string $target
     * @param array $options
     */
    public function setEdgeOptions($source, $target, array $options)
    {
        $id = $source . '-' . $target;
        if (isset($this->edges[$id])) {
            $options = array_merge($this->edges[$id], $options);
        }
        $this->edges[$id] = $options;
    }

    /**
     * @param string $name
     * @param array $options
     */
    public function setNodeOptions($name, array $options)
    {
        if (isset($this->nodes[$name])) {
            $options = array_merge($this->nodes[$name], $options);
        }
        if (empty($options['label'])) {
            $options['label'] = $name;
        }
        $this->nodes[$name] = $options;
    }

    /**
     * @return string
     */
    public function getDotCode()
    {
        $nodes = $this->graph->getNodes();
        $index = 0;
        $identifiers = [];

        $graph = <<<HEREDOC
digraph {

HEREDOC;
        foreach ($nodes as $node) {
            $options = $this->buildOptions($this->getNodesOptions($node));
            $identifiers[$node] = ++$index;
            $graph .= "   Node{$index} $options;\n";
        }
        $edges = $this->graph->getEdges();
        foreach ($edges as $edge) {
            $options = $this->buildOptions($this->getEdgeOptions($edge[0], $edge[1]));
            $graph .= "   Node{$identifiers[$edge[0]]} -> Node{$identifiers[$edge[1]]} $options;\n";
        }
        $graph .= <<<HEREDOC
}
HEREDOC;

        return $graph;
    }

    /**
     * @param $node
     * @return array
     */
    private function getNodesOptions($node)
    {
        $options = isset($this->nodes[$node]) ? $this->nodes[$node] : [];
        if (!isset($options['label'])) {
            $options['label'] = $node;
        }

        return $options;
    }

    /**
     * @param $source
     * @param $target
     * @return array
     */
    private function getEdgeOptions($source, $target)
    {
        $id = $source . '-' . $target;

        return isset($this->edges[$id]) ? $this->edges[$id] : [];
    }

    /**
     * @param array $options
     * @return string
     */
    private function buildOptions(array $options)
    {
        $optionsLabels = '';
        if (!empty($options)) {
            $optionsLabels = "[";
            foreach ($options as $option => $value) {
                $optionsLabels .= sprintf('%s = "%s" ', $option, $value);
            }
            $optionsLabels .= "]";
        }

        return $optionsLabels;
    }
}
