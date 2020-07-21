<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use Throwable;

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
     * @return NodeTree
     */

    public function getRoot(): NodeTree
    {

        if (!($this->Parent instanceof NodeTree)) {
            if (!($this instanceof NodeTree)) {
                throw new TemplateException(
                    'Expected root-node to be a StudyPortals\Template\NodeTree,'
                    . ' got a ' . get_class($this)
                );
            }

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
     * Display the Node.
     *
     * @return string
     * @see Node::display()
     */

    public function __toString()
    {

        if (version_compare('7.4', (string) phpversion()) === 1) {
            return $this->__toStringWithoutException();
        }

        return $this->display();
    }

    /**
     * Display the Node (and *never* raise an exception).
     *
     * In PHP < 7.4, the __toString() magic-method is not allowed to throw an
     * exception. The implementation below ensures an exception is never raised.
     *
     * To aid in debugging, when an exception is caught a string with some
     * information about the exception is returned. As we have no way of knowing
     * whether it is appropriate to return detailed debugging information, only
     * minimal information is returned by default:
     *      "Namespace/Of/The/Exception in File.php:123"
     *
     * Only when the Template is set to "strict", additional in is enabled (which we assume would be used mainly in
     * development), some more information is added.
     * We assume "strict" is only used in development-environments.
     *
     *
     * @return string
     * @see https://wiki.php.net/rfc/tostring_exceptions
     * @see Template::createStrict()
     */

    public function __toStringWithoutException(): string
    {

        try {
            return $this->display();
        } catch (Throwable $e) {
            $class  = get_class($e);
            $file   = $e->getFile();
            $line   = $e->getLine();

            $root = $this->getRoot();

            if ($root instanceof Template && $root->isStrict()) {
                return "$class in $file:$line: {$e->getMessage()} in {$root->getFileName()}";
            }

            $file = basename($file);

            return "$class in $file:$line";
        }
    }
}
