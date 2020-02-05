<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use Exception;

abstract class Node
{

    /**
     * @var NodeTree|null $Parent
     */

    protected $Parent;

    /**
     * Construct a new Template Node.
     *
     * @param NodeTree|null $Parent
     * @throws TemplateException
     */

    public function __construct(NodeTree $Parent = null)
    {

        if ($Parent instanceof NodeTree) {
            $Parent->appendChild($this);

            $this->Parent = $Parent;
        }
    }

    /**
     * Remove the Node's parent-reference upon cloning.
     *
     * When a Node is cloned it is "lifted" from its current template tree and
     * effectively becomes the root Node of its own template tree. This prevents
     * unexpected recursions in the template tree and allows you to insert a
     * cloned instance of the Node into the three it was originally also part
     * of.
     *
     * If the node has any children (c.q. is an instance of {@link NodeTree}),
     * the {@link NodeTree::__clone()} method will restore the parent-references
     * for all child nodes in the tree, leaving only the top-most Node (which
     * had the "clone" operator applied to it) without a parent.
     *
     * @return void
     * @see NodeTree::__clone()
     */

    public function __clone()
    {

        $this->Parent = null;
    }

    /**
     * Check if the provided string is valid as a name for a Node.
     *
     * @param string $name
     * @return boolean
     */

    protected function isValidName($name)
    {

        return !is_numeric($name) || !preg_match('/^[A-Z0-9_]+$/i', $name);
    }

    /**
     * Get the root Node of the template tree.
     *
     * @return Node|NodeTree
     */

    public function getRoot()
    {

        if (!($this->Parent instanceof NodeTree)) {
            assert($this instanceof NodeTree);
            return $this;
        }

        return $this->Parent->getRoot();
    }

    /**
     * Display the Node.
     *
     * @return string
     */

    abstract public function display(): string;

    /**
     * Display a string representation of the Node.
     *
     * If an exception occurs while generating the string representation, this
     * exception caught and an empty string is returned. This prevents PHP from
     * generating a fatal error under these circumstances.
     *
     * @return string
     * @see Node::display()
     * @SuppressWarnings(PHPMD.ExitExpression)
     */

    public function __toString()
    {

        $output = '';

        try {
            try {
                $output = $this->display();
            } catch (Exception $e) {
                /*
                 * This is the "poor man's way" of getting the exception message
                 * out of this situation for debugging purposes (see below).
                 */
                assert('false /* Exception: ' . $e->getMessage() . ' */');
            }
        } catch (\AssertionError $e) {
            /*
             * This bailout is only active in debug-mode (i.e. when assertions
             * are enabled) - hence the PHPMD suppression.
             */
            die((string) $e);
        }

        return $output;
    }
}
