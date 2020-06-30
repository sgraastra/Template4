<?php declare(strict_types=1);

namespace StudyPortals\Template\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use StudyPortals\Template\Handlebars;

class HandlebarsTest extends TestCase
{

    public function testRender(): void
    {

        @unlink(__DIR__ . '/Resources/HandlebarsTest-handlebars.tp4-cache');
        $this->assertFileNotExists(
            __DIR__ . '/Resources/HandlebarsTest-handlebars.tp4-cache'
        );

        $template = Handlebars::templateFactory(
            __DIR__ . '/Resources/HandlebarsTest.tp4'
        );

        /*
         * If you start getting unexpected failures of this test, ensure
         * that "Expected/Render.hbs" is checked out with LF line-endings.
         */

        $this->assertStringEqualsFile(
            __DIR__ . '/Expected/Render.hbs',
            $template->__toString()
        );
    }

    /**
     * Render the expected smoke-test outcomes
     *
     * @return void
     * @SuppressWarnings(PHPMD.StaticAccess)
     */

    public static function renderExpected()
    {

        @unlink(__DIR__ . '/Resources/HandlebarsTest-handlebars.tp4-cache');

        $template = Handlebars::templateFactory(
            __DIR__ . '/Resources/HandlebarsTest.tp4'
        );

        file_put_contents(
            __DIR__ . '/Expected/Render.hbs',
            $template->__toString()
        );
    }
}
