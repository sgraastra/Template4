<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */

class Condition extends NodeTree
{

    /**
     * @var string $condition
     */
    protected $condition;

    /**
     * @var string $operator
     */

    protected $operator;

    /**
     * @var mixed $value
     */

    protected $value;

    /**
     * @var array<string> $value_set
     */

    protected $value_set = [];

    /**
     * @var boolean $local
     */

    protected $local = false;

    /**
     * Construct a new Condition Node.
     *
     * The contents of this Node will be displayed based upon a condition
     * evaluated at runtime. The {@link $condition}, {@link $operator} and
     * {@link value} parameters define the condition to be evaluated. The
     * {@link $condition} parameter contains the name of the value queried from
     * the Template tree.
     *
     * The optional {@link $local} parameter indicates whether the entire
     * Template tree should be searched for the condition value, or only the
     * local scope should be used.
     * Local scope in this case refers to the "virtual" Template tree as defined
     * in the description of the {@link NodeTree::getChildByName()} method.
     *
     * @param NodeTree $Parent
     * @param string $condition
     * @param string $operator
     * @param string|array<string> $value
     * @param boolean $local
     * @throws TemplateException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */

    public function __construct(
        NodeTree $Parent,
        string $condition,
        string $operator,
        $value,
        bool $local = false
    ) {
        /*
         * TODO: Refactor the boolean-property $local out by creating a
         * Condition and LocalCondition class. Too much hassle for now (hence
         * the suppressed PHPMD warning).
         */

        if (!$this->isValidName($condition)) {
            throw new TemplateException(
                "Invalid condition \"$condition\" specified for Condition node"
            );
        }

        parent::__construct($Parent);

        $this->condition = $condition;
        $this->operator = $operator;
        $this->local = $local;

        // Set-values

        if ($this->operator == 'in' || $this->operator == '!in') {
            if (!is_array($value)) {
                throw new TemplateException(
                    "Invalid set-value specified for Condition node \"$condition\""
                );
            }

            $this->value_set = $value;
            return;
        }

        // Scalar-values

        $this->value = $value;
    }

    /**
     * Execute the comparison stored in this Node on the provided value.
     *
     * @param mixed $value
     * @return boolean
     */

    public function compareValue($value)
    {

        switch ($this->operator) {
            // Scalar

            case '==':
                return $value == $this->value;
            case '!=':
                return $value != $this->value;
            case '<':
                return $value < $this->value;
            case '<=':
                return $value <= $this->value;
            case '>':
                return $value > $this->value;
            case '>=':
                return $value >= $this->value;

            // Set

            case 'in':
            case '!in':
                $match = ($this->operator == 'in' ? false : true);

                foreach ($this->value_set as $element) {
                    if ($value == $element) {
                        $match = ($this->operator == 'in' ? true : false);
                        break;
                    }
                }

                return $match;

            default:
                throw new TemplateException(
                    "Unknown comparison operator {$this->operator} encountered"
                );
        }
    }

    /**
     * Display the contents of the condition node.
     *
     * @return string
     * @see NodeTree::display()
     */

    public function display(): string
    {
        $value = null;

        if ($this->Parent instanceof NodeTree) {
            $value = $this->Parent->getLocalValue($this->condition);
        }

        if (
            !$this->local &&
            $value === null &&
            $this->Parent instanceof NodeTree
        ) {
            $value = $this->Parent->getValue($this->condition);
        }

        if (!$this->compareValue($value)) {
            return '';
        }

        return parent::display();
    }
}
