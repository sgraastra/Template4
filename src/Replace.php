<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

class Replace extends Node
{

    /**
     * @var string $replace
     */

    protected $replace;

    /**
     * @var boolean $raw
     */

    protected $raw = false;

    /**
     * Construct a new Replace Node.
     *
     * The contents of this Node will be replaced by a value specified during
     * runtime. The {@link $replace} parameter is the name of the value which
     * will contain the replacement content.
     *
     * The optional {@link $raw} parameter indicates whether the value should
     * be treated as raw HTML. When enabled, the value will not
     * filtered to prevent accidental inclusion of HTML into the template
     * through the replace mechanism.
     *
     * @param NodeTree $Parent
     * @param string $replace
     * @param boolean $raw
     * @throws TemplateException
     * @see TemplateNodeTree::setValue()
     */

    public function __construct(
        NodeTree $Parent,
        $replace,
        $raw = null
    ) {

        if (!$this->isValidName($replace)) {
            throw new TemplateException(
                "Invalid name \"$replace\"
                specified for Replace node"
            );
        }

        parent::__construct($Parent);

        $this->replace = $replace;

        $this->raw = (is_null($raw) ? $this->raw : (bool) $raw);
    }

    /**
     * Display the Replace Node.
     *
     * This method searches the Template tree for a value matching
     * {@link $Replace::$_replace} and returns its contents if found. If not
     * found, an empty string is returned.
     *
     * @return string
     * @see Node::display()
     * @see TemplateNodeTree::getValue()
     */

    public function display(): string
    {

        if (!($this->Parent instanceof NodeTree)) {
            return '';
        }


        $value = $this->Parent->getValue($this->replace);

        if (!$this->raw) {
            $value = htmlspecialchars(
                (string) $value,
                ENT_COMPAT | ENT_HTML401,
                'UTF-8'
            );
        }

        return (string) $value;
    }
}
