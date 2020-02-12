<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template;

use Exception;
use Throwable;

class TemplateException extends Exception
{

    public function __construct(
        string $message = '',
        int $code = 0,
        Throwable $previous = null
    ) {
        // Remove all superfluous white-spaces for increased readability

        $message = (string) preg_replace('/\s+/', ' ', $message);

        parent::__construct($message, $code, $previous);
    }
}
