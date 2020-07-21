<?php declare(strict_types=1);

namespace StudyPortals\Template\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StudyPortals\Template\StrictException;
use StudyPortals\Template\Template;

class StrictTest extends TestCase
{
    /**
     * @var Template
     */
    protected $template;

    /**
     * @var bool
     */
    protected $throws;

    public function setUp(): void
    {
        Template::setTemplateCache('off');
        $this->template = Template::createStrict(
            __DIR__ . '/Resources/StrictTest.tp4'
        );

        $this->throws =
            (bool) (version_compare('7.4', (string) phpversion()) <= 0);
    }

    public function testStrictVarAndIf_Unset(): void
    {
        if ($this->throws) {
            $this->expectException(StrictException::class);
        }

        $output = (string) $this->template;

        if (!$this->throws) {
            $this->assertRegExp(
                '/^.*\\\StrictException in .*Condition.php:[0-9]+: Condition "testMe" .*StrictTest.tp4$/',
                $output
            );
        }
    }

    public function testStrictReplaceAndCondition_Set(): void
    {
        $this->template->testMe = true;
        $this->template->setMe = true;
        $output = (string) $this->template;

        $this->assertNotEmpty($output);
    }

    public function testStrictReplace_SetUnset(): void
    {
        if ($this->throws) {
            $this->expectException(StrictException::class);
        }

        $this->template->testMe = true;

        $this->template->setMe = true;
        $this->template->setMe = null;
        $output = (string) $this->template;

        if (!$this->throws) {
            $this->assertRegExp(
                '/^.*\\\StrictException in .*Replace.php:[0-9]+: Replace "setMe" .*StrictTest.tp4$/',
                $output
            );
        }
    }

    public function testStrictCondition_SetUnset(): void
    {
        if ($this->throws) {
            $this->expectException(StrictException::class);
        }

        $this->template->setMe = true;

        $this->template->testMe = true;
        $this->template->testMe = null;
        $output = (string) $this->template;

        if (!$this->throws) {
            $this->assertRegExp(
                '/^.*\\\StrictException in .*Condition.php:[0-9]+: Condition "testMe" .*StrictTest.tp4$/',
                $output
            );
        }
    }
}
