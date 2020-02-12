<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

/**
 * TemplateNodeTree
 *
 * Extends {@link NodeTree} with the ability to assign names to Nodes in
 * the tree. Furthermore, this class allows values to be assigned to Nodes in
 * the tree. These values are used by {@link Replace} and {@link Condition} and
 * as such are the basis of the actual replace-marker-with behaviour
 * offered by Template4.
 *
 * All classes that inherit from TemplateNodeTree can be used as root
 * elements inside a template tree.
 */

abstract class TemplateNodeTree extends NodeTree
{

    /**
     * @var string $name
     */
    protected $name;

    /**
     * @var array<mixed> $values
     */

    protected $values = [];

    /**
     * @var array<Node> $virtual_children
     */

    protected $virtual_children = [];

    /**
     * Construct a new template node tree.
     *
     * This method throws an exception if the provided {@link $name} argument
     * is invalid.
     *
     * @param string $name
     * @param NodeTree|null $Parent
     * @throws TemplateException
     */

    public function __construct($name, NodeTree $Parent = null)
    {
        // Set name before calling parent constructor

        if (!$this->isValidName($name)) {
            throw new TemplateException(
                "Unable to create Template node,
                the specified name \"$name\" is invalid"
            );
        }

        $this->name = $name;

        parent::__construct($Parent);
    }

    /**
     * Clear list of virtual children before serialisation.
     *
     * If left untouched, the list of virtual children will contain
     * non-circular references which are not serialised correctly. This leads
     * to duplicate objects after unserialisation, which really bad.
     *
     * @return array
     */

    public function __sleep()
    {
        $this->virtual_children = [];

        return [
            "\0*\0Parent",
            "\0*\0children",
            "\0*\0name",
            "\0*\0values",
            "\0*\0virtual_children",
        ];
    }

    /**
     * Create a "deep" clone of the TemplateNodeTree.
     *
     * @see NodeTree::__clone()
     */

    public function __clone()
    {
        $this->virtual_children = [];

        parent::__clone();
    }

    /**
     * Get an element from the "virtual" Template tree.
     *
     * This method first attempts to call {@link
     * TemplateNodeTree::getChildByName()} with the provided {@link $name}
     * argument. If this fails it calls {@link TemplateNodeTree::getValue()}.
     * When assertions are enabled, this method will assert the element
     * requested to be not null.
     *
     * This method thus allows access to a virtual Template tree
     * consisting of named Nodes and values available that are
     * named descendants of this Node. See
     * {@link NodeTree::getChildByName()} for a description on when a named
     * Node is considered to be a named descendant.
     *
     * @param string $name
     * @return mixed
     */

    public function __get($name)
    {
        try {
            $value = $this->getChildByName($name);
        } catch (NodeNotFoundException $e) {
            $value = $this->getLocalValue($name);
        }

        return $value;
    }

    /**
     * Add an element to the "virtual" Template tree.
     *
     * This method first attempts to replace the Node named {@link $name}
     * with the node provided in the {@link $value} argument. If this fails,
     * either because there exists no Node named {@link $name} or because
     * {@link $value} is not a valid Node, a value named {@link $name} is set
     * to value {@link $value} instead.
     *
     * Note: This method will never attempt to add a new Node to the Template
     * tree. This can only be done by explicitly callling
     * {@link NodeTree::appendChild()}.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @see TemplateNodeTree::__get()
     * @see TemplateNodeTree::setValue()
     * @see NodeTree::replaceChild()
     */

    public function __set($name, $value)
    {
        // Try to prevent the use of existing property names

        assert(!isset($this->$name));

        if ($value instanceof TemplateNodeTree) {
            try {
                $Node = $this->getChildByName($name);

                // Node is a virtual child

                if (
                    $Node->Parent !== $this
                    && $Node->Parent instanceof NodeTree
                ) {
                    $Node->Parent->replaceChild($Node, $value);

                    // Clear (the now invalidated) virtual child cache

                    $this->virtual_children = [];
                    return;
                }

                $this->replaceChild($Node, $value);
                return;
            } catch (NodeNotFoundException $e) {
                // Pass-through is intentional
            }
        }
        $this->setValue($name, $value);
        return;
    }

    /**
     * Return this Node's name.
     *
     * @return string
     */

    public function getName()
    {
        return $this->name;
    }

    /**
     * Return this node's named parent.
     *
     * In case the node doesn't have a named parent, c.q. it is the root
     * element in the tree, null is returned.
     *
     * Since this method always returns an instance of TemplateNodeTree (c.q.
     * a named node), getParent() should not be used in an attempt to retrieve
     * the root node of a template tree. Use {@link Node::getRoot()} instead.
     *
     * @return NodeTree|null
     * @see Node::getRoot()
     */

    public function getParent()
    {
        if ($this->Parent instanceof NodeTree) {
            if ($this->Parent instanceof TemplateNodeTree) {
                return $this->Parent;
            }

            $AnonymousParent = $this->Parent;

            while (true) {
                assert('++$i < 100');

                $Parent = $AnonymousParent->Parent;

                if (
                    is_null($Parent)
                    || $Parent instanceof TemplateNodeTree
                ) {
                    return $Parent;
                }

                $AnonymousParent = $Parent;
            }
        }

        return null;
    }

    /**
     * Return this Node's named descendant with a matching name.
     *
     * @param string $name
     * @return TemplateNodeTree
     * @throws NodeNotFoundException
     * @see NodeTree::getChildByName()
     */

    public function getChildByName($name)
    {
        // Direct child

        if (
            isset($this->children[$name]) &&
            $this->children[$name] instanceof TemplateNodeTree
        ) {
            return $this->children[$name];
        }

        // Virtual child

        if (
            isset($this->virtual_children[$name]) &&
            $this->virtual_children[$name] instanceof TemplateNodeTree
        ) {
            return $this->virtual_children[$name];
        }

        $VirtualChild = parent::getChildByName($name);

        $this->virtual_children[$name] = $VirtualChild;
        return $VirtualChild;
    }

    /**
     * Retrieve a value from this Node or one of its descendants.
     *
     * @param string $name
     * @return mixed
     * @see NodeTree::getValue()
     */

    public function getValue($name)
    {
        $value = $this->getLocalValue($name);

        if (!is_null($value)) {
            return $value;
        }

        if (!is_null($this->Parent)) {
            return $this->Parent->getValue($name);
        }

        return null;
    }

    /**
     * Retrieve a value local to this Node.
     *
     * @param string $name
     * @return mixed
     * @see NodeTree::getLocalValue()
     */

    public function getLocalValue($name)
    {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        }

        return null;
    }

    /**
     * Set a value in this Node.
     *
     * To unset a value, pass null as its {@link $value}.
     *
     * In case {@link $value} is an object, it is checked if the object
     * implements the {@link Template} interface. If this is not the case, the
     * object is searched for the existence of a __toString() method.
     * In case this method is found, the resulting string is added under
     * {@link $name}.
     * If neither the interface nor the method is not a TemplateException gets
     * thrown.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws TemplateException
     */

    public function setValue($name, $value)
    {
        if (!$this->isValidName($name)) {
            throw new TemplateException("Name \"$name\" is invalid");
        }

        // Scalar types

        if (
            is_string($value)
            || is_bool($value)
            || is_int($value)
            || is_float($value)
        ) {
            $this->values[$name] = $value;
            return;
        }

        // Null

        if ($value === null) {
            unset($this->values[$name]);
            return;
        }

        // Objects

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $this->values[$name] = (string) $value;
                return;
            }

            throw new TemplateException(
                "Unable to set value
                \"$name\", could not convert object to string"
            );
        }

        throw new TemplateException(
            "Unable to set value \"$name\", type \"" .
            gettype($value) . '" is not allowed'
        );
    }

    /**
     * Reset the template to its initial state.
     *
     * @return void
     * @see TemplateNodeTree::setValue()
     * @see NodeTree::resetTemplate()
     */

    public function resetTemplate()
    {
        $this->values = [];

        parent::resetTemplate();
    }
}
