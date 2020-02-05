<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

class Text extends Node
{

    /**
     * @var string $data
     */

    protected $data;

    /**
     * Construct a new Text Node.
     *
     * @param string $data
     * @param NodeTree $Parent
     * @throws TemplateException
     */

    public function __construct($data, NodeTree $Parent)
    {

        parent::__construct($Parent);

        $this->data = $data;
    }

    /**
     * Display the Text Node (c.q. return its contents).
     *
     * @return string
     * @see Node::display()
     */

    public function display(): string
    {

        return $this->data;
    }
}
