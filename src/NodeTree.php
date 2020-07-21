<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

/**
 * NodeTree
 *
 * Extends {@link Node} with the ability to contain child Nodes and
 * the ability to operate in a Template tree consisting of a mixed set of
 * {@link NodeTree}  and {@link TemplateNodeTree} objects.
 */

abstract class NodeTree extends Node
{

    /**
     * @var bool
     */

    protected $strict = false;

    /**
     * @var array<Node> $children
     */

    protected $children = [];

    /**
     * Create a "deep" clone of the NodeTree.
     *
     * By default PHP creates a "shallow" clone which means that only the
     * current object is cloned. All properties which reference other objects
     * keep their original reference. This is not what we want when we clone a
     * NodeTree.
     *
     * @return void
     * @see Node::__clone()
     */

    public function __clone()
    {

        foreach ($this->children as $index => $Child) {
            $Child = clone $Child;

            // Replace child with its clone and re-reference the parent Node

            $this->children[$index] = $Child;
            $Child->Parent = $this;
        }

        parent::__clone();
    }

    public function isStrict(): bool
    {

        return $this->strict;
    }

    /**
     * Get a value from the Node tree.
     *
     * This method serves as a wrapper for {@link TemplateNodeTree::getvalue()}.
     * Usually, a Template tree is a combination of {@link NodeTree} and
     * {@link TemplateNodeTree} classes. This method allows calls to
     * {@link TemplateNodeTree::getvalue()} to travel through the entire tree.
     *
     * In the context of a tree consisting purely of {@link NodeTree} classes
     * this method has no use and will simply iterate up the tree until the top
     * node is reached, at which point null will be returned.
     *
     * @param string $name
     * @return mixed
     * @see TemplateNodeTree::getValue()
     * @see TemplateNodeTree::getChildByName()
     */

    public function getValue($name)
    {

        if (is_null($this->Parent)) {
            return null;
        }

        return $this->Parent->getValue($name);
    }

    /**
     * Get a value from the Node tree (local-only).
     *
     * @param string $name
     * @return mixed
     * @see NodeTree::getValue()
     */

    public function getLocalValue($name)
    {

        if (is_null($this->Parent)) {
            return null;
        }

        return $this->Parent->getLocalValue($name);
    }

    /**
     * Return this Node's named descendant with a matching name.
     *
     * As only Nodes inheriting from {@link TemplateNodeTree} can have a name,
     * there can be several levels of {@link NodeTree} Nodes in between the
     * current Node and the first named child Node requested.
     * This method traverses a "virtual" Template tree only containing the Nodes
     * inheriting from {@link TemplateNodeTree}. While the specified named Node
     * is not found, but there are still {@link NodeTree} children, this
     * method will recurse. The "virtual" Template tree thus consists only of
     * {@link TemplatNodeTree} Nodes.
     *
     * As these "virtual child" lookups are extremely
     * expensive, the {@link TemplateNodeTree::getChildByName()} method
     * extends this base method by providing a virtual child cache.
     * Once a child is located, it is stored in the cache and no recursion
     * through the template tree is required anymore.
     *
     * @param string $name
     * @return TemplateNodeTree
     * @throws NodeNotFoundException
     * @see TemplateNodeTree::hasChildByName()
     */

    public function getChildByName($name)
    {

        if (
            isset($this->children[$name])
            && $this->children[$name] instanceof TemplateNodeTree
        ) {
            return $this->children[$name];
        }

        // Virtual child

        foreach ($this->children as $Node) {
            if (
                $Node instanceof NodeTree
                && !($Node instanceof TemplateNodeTree)
            ) {
                try {
                    return $Node->getChildByName($name);
                } catch (NodeNotFoundException $e) {
                    continue;
                }
            }
        }

        throw new NodeNotFoundException(
            "Unable to find node with name \"$name\""
        );
    }

    /**
     * Check if this Node has a *named* descendant with a matching name.
     *
     * This method provides some "syntactic sugar" around the {@link
     * NodeTree::getChildByName()} method. Instead of returning the Node
     * found or throwing an exception, this method only returns true
     * or false. This method thus also utilises the virtual child
     * cache.
     *
     * @param string $name
     *
     * @return boolean
     * @see NodeTree::getChildByName()
     */

    public function hasChildByName($name)
    {

        try {
            $this->getChildByName($name);

            return true;
        } catch (NodeNotFoundException $e) {
            return false;
        }
    }

    /**
     * Add a Node as a child to the current Node.
     *
     * Note: If you create a new Node (through its constructor),
     * it is automatically appended to the Parent specified in said constructor.
     *
     * See the description of {@link NodeTree::replaceChild()} for some
     * important remarks concerning the appending of nodes
     * which are already part of the current template tree.
     *
     * @param Node $Child
     * @return void
     * @throws TemplateException
     * @see NodeTree::replaceChild()
     */

    public function appendChild(Node $Child)
    {

        if ($Child instanceof TemplateNodeTree) {
            /*
             * Prevent nodes from being appended (c.q. referenced) into their
             * own template trees.
             */

            if ($this->getRoot() === $Child->getRoot()) {
                throw new TemplateException(
                    "Cannot append node \"{$Child->getName()}\",
                    already part of the same template tree"
                );
            }

            if ($this->hasChildByName($Child->getName())) {
                throw new TemplateException(
                    "Cannot append node \"{$Child->getName()}\",
                    a node with this name already exists"
                );
            }

            $this->children[$Child->getName()] = $Child;

            return;
        }

        $this->children[] = $Child;
    }

    /**
     * Replace a child Node with another Node.
     *
     * Note: If you intent to use this method (or the fact
     * that its automatically called from {@link TemplateNodeTree::__set()}) to
     * dynamically extend a template with Nodes from the same template,
     * clone the Node before passing it into this
     * method.
     * If you do not do this, you will most likely run into the recursion/memory
     * limits of PHP as there are going to be interactions between the
     * "replaced" node (which is now more or less referenced multiple times in
     * the same tree) that you cannot foresee easily.
     *
     * This method will attempt to warn you by throwing a
     * {@link TemplateException} when it  detects the {@link $ReplaceChilde} is
     * $this or one of its children. This prevents most problems, but
     * there are scenarios which are not caught.
     * If you ever run into the function recursion limit of PHP in the
     * {@link NodeTree::__clone()} method, the error is most likely as
     * described above.
     *
     * @param TemplateNodeTree $Child
     * @param TemplateNodeTree $ReplaceChild
     * @return void
     * @throws TemplateException
     * @see NodeTree::__clone()
     * @see TemplateNodeTree::__set()
     */

    public function replaceChild(
        TemplateNodeTree $Child,
        TemplateNodeTree $ReplaceChild
    ) {
        /*
         * Prevent nodes from being added (c.q. referenced) into their own
         * template trees.
         */

        if ($Child->getRoot() === $ReplaceChild->getRoot()) {
            throw new TemplateException(
                "Cannot replace node \"{$ReplaceChild->getName()}\",
                already part of the same template tree"
            );
        }

        foreach ($this->children as $index => $myChild) {
            if ($myChild === $Child) {
                $Child->Parent = null;

                $ReplaceChild->Parent = $this;
                $ReplaceChild->name = $Child->getName();

                $this->children[$index] = $ReplaceChild;

                return;
            }
        }

        throw new NodeNotFoundException(
            "Cannot replace node \"{$Child->getName()}\",
            not a child node"
        );
    }

    /**
     * Display the node tree.
     *
     * Calls Node::display() on all of its children.
     *
     * @return string
     * @see Node::display()
     */

    public function display(): string
    {
        $reduce = function (string $carry, Node $Child) {

            $carry .= $Child->display();

            return $carry;
        };

        return (string) array_reduce($this->children, $reduce, '');
    }


    /**
     * Reset the template to its initial state.
     *
     * This method clears all stored values from the Template (c.q. this Node
     * and all its {@link NodeTree} based descendants). This method does
     * not reset changes made to the structure of the Template,
     * by adding or removing Nodes.
     *
     * @return void
     */

    public function resetTemplate()
    {

        foreach ($this->children as $Child) {
            if ($Child instanceof NodeTree) {
                $Child->resetTemplate();
            }
        }
    }
}
