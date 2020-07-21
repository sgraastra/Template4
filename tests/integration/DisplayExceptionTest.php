<?php declare(strict_types=1);

namespace StudyPortals\Template\Tests\Integration;

use Exception;
use PHPUnit\Framework\TestCase;
use StudyPortals\Template\Template;

class DisplayExceptionTest extends TestCase
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
        $this->template = Template::create(
            __DIR__ . '/Resources/StrictTest.tp4'
        );

        $this->throws =
            (bool) (version_compare('7.4', (string) phpversion()) <= 0);
    }

    public function testDisplayException(): void
    {
        if ($this->throws) {
            $this->expectException(Exception::class);
        }

        $this->template->testMe = true;
        $this->template->setMe = new DisplayException('Foo');

        $output = (string) $this->template;

        if (!$this->throws) {
            $this->assertRegExp(
                '/^Exception in DisplayException.php:[0-9]+$/',
                $output
            );

            return;
        }

        $this->assertEmpty($output);
    }
}
