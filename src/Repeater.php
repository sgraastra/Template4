<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

/**
 * Repeater
 *
 * This class allows its content to be repeated multiple times. This allows
 * for lists and tables of dynamic length to be build. By utilising the
 * {@link NodeTree::replaceChild()}. method to replace an internal
 * {@link Section} with the {@link Repeater} itself, it's possible to create
 * dynamic, multi-level, nested repeaters.
 */

class Repeater extends TemplateNodeTree
{

    /**
     * @var array<string> $output
     */

    protected $output = [];

    /**
     * Reset the repeater to its initial state.
     *
     * Calling this method removes all previously completed repetitions from
     * the repeater.
     *
     * @return void
     * @see TemplateNodeTree::resetTemplate()
     */

    public function resetTemplate()
    {

        $this->output = [];

        parent::resetTemplate();
    }

    /**
     * Store the current output of the repeater as one of its repetitions.
     *
     * This method stores the result of the repetition and resets the
     * repeater to its initial state, allowing a new repetition to be
     * started.
     *
     * @return void
     */

    public function repeat()
    {

        $this->output[] = parent::display();

        parent::resetTemplate();
    }

    /**
     * Display the repeater node.
     *
     * This method returns the output of all previously stored repetitions
     * in a single string.
     *
     * @return string
     */

    public function display(): string
    {

        $output = (string) implode('', $this->output);

        return $output;
    }
}
