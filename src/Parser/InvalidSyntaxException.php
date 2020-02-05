<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template\Parser;

class InvalidSyntaxException extends FactoryException
{

    /**
     * Construct a new Syntax Exception.
     *
     * @param string $message
     * @param integer $line
     */

    public function __construct($message, $line = 0)
    {

        if ($line > 0) {
            $message .= " on line $line";
        }

        parent::__construct($message);
    }
}
