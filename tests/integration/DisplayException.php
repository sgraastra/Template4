<?php declare(strict_types=1);

namespace StudyPortals\Template\Tests\Integration;

use Exception;
use StudyPortals\Template\Section;

class DisplayException extends Section
{
    public function display(): string
    {
        throw new Exception('Foo!');
    }
}
