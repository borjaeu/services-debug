<?php
namespace Kizilare\ServicesDebug\Helper;

class Graph
{
    /**
     * @var array
     */
    protected $nodes = [];

    /**
     * @var array
     */
    protected $edges = [];

    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @param string $name
     */
    public function addNode($name)
    {
        if (!isset($this->nodes[$name])) {
            $this->index++;
            $this->nodes[$name] = 0;
        }
    }

    /**
     * @param string $name
     */
    public function removeNode($name)
    {
        unset($this->nodes[$name]);
        foreach ($this->edges as $id => $edge) {
            if ($edge[0] == $name || $edge[0] == $name) {
                unset($this->edges[$id]);
            }
        }
    }

    /**
     * @param string $source
     * @param string $target
     */
    public function addEdge($source, $target)
    {
        $this->addNode($source);
        $this->addNode($target);
        $id = $source . '-' . $target;
        if (!isset($this->edges[$id])) {
            $this->edges[$id] = [$source, $target];
            $this->nodes[$source]++;
            $this->nodes[$target]++;
        }
    }

    /**
     * @return array
     */
    public function getEmptyNodes()
    {
        $nodes = [];

        foreach ($this->nodes as $node => $uses) {
            if ($uses == 0) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param string $node
     * @return bool
     */
    public function isLeaf($node)
    {
        foreach($this->edges as $edge) {
            if ($edge[0] == $node) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $node
     * @return bool
     */
    public function isRoot($node)
    {
        foreach($this->edges as $edge) {
            if ($edge[1] == $node) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $source
     * @param string $target
     * @return bool
     */
    public function isReachable($source, $target)
    {
        return $this->checkPath($source, $target, $source);
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $initialNode
     * @param string $ignoreTarget
     * @return bool
     */
    private function checkPath($source, $target, $initialNode = '', $skipDirect = false)
    {
        if ($initialNode == $target) {
            return false;
        }
        foreach ($this->edges as $nodes) {
            if ($nodes[0] == $source && $nodes[1] == $target && !$skipDirect) {
                return true;
            }
            if ($nodes[0] == $source ) {
                if ($this->isReachable($nodes[1], $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $parent
     * @param callable $callback
     */
    public function tree($parent, $callback)
    {
        $this->depthTraversal($parent, $parent, $callback);
    }

    /**
     * @param string $parent
     * @param string $first
     * @param callable $callback
     */
    public function depthTraversal($parent, $first, $callback)
    {
        foreach ($this->edges as $nodes) {
            if ($nodes[0] == $parent && $nodes[1] !== $first) {
                $callback($parent, $nodes[1]);
                $this->depthTraversal($nodes[1], $first, $callback);
            }
        }
    }

    /**
     * @return bool
     */
    public function reduce()
    {
        $reduced = false;
        foreach ($this->edges as $id => $nodes) {
            if ($this->checkPath($nodes[0], $nodes[1], $nodes[0], true)) {
                unset($this->edges[$id]);
                $reduced = true;
            }
        }

        return $reduced;
    }

    /**
     * @return array
     */
    public function getNodes()
    {
        return array_keys($this->nodes);
    }

    /**
     * @return array
     */
    public function getEdges()
    {
        return array_values($this->edges);
    }

}
