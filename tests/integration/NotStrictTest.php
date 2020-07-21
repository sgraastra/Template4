<?php declare(strict_types=1);

namespace StudyPortals\Template\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StudyPortals\Template\Template;

class NotStrictTest extends TestCase
{
    /**
     * @var Template
     */
    protected $template;

    public function setUp(): void
    {
        Template::setTemplateCache('off');
        $this->template = Template::create(
            __DIR__ . '/Resources/StrictTest.tp4'
        );
    }

    public function testNotStrictVarAndIf_Unset(): void
    {
        $output = (string) $this->template;

        $this->assertEmpty($output);
    }

    public function testNotStrictVarAndIf_Set(): void
    {
        $this->template->testMe = true;
        $this->template->setMe = true;
        $output = (string) $this->template;

        $this->assertNotEmpty($output);
    }
}
