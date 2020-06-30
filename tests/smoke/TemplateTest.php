<?php declare(strict_types=1);

namespace {
    // Enable/disable the uniqid()-mock
    $mockUniqid = false;
}

namespace StudyPortals\Template\Parser{

    /**
     * uniqid()-Mock
     *
     * Overwrites {@see uniqid()} in the {@link StudyPortals\Template\Parser}
     * namespace. Only works when {@see uniqid()} is called _without_ a leading
     * backslash (otherwise it uses the global namespace).
     * The {@link $mockUniqid} global variable is used to toggle the overwrite
     * on and off. This is used as part of the test-suite and also to prevent
     * the accidental use of the mock in production (which shouldn't happen as
     * this code doesn't get loaded in production, but still...).
     *
     * When the mock is enabled, {@see uniqid()} returns the first argument
     * passed into it ($prefix = '') unmodified. This effectively disables the
     * "random" nature of the function and ensures a predictable output.
     * The mock is used to ensure {@link Factory::parseDefContentElements()}
     * creates predictable output which allows us to compare against a
     * reference token-list.
     *
     * @return mixed
     */

    // @phpstan-ignore-next-line
    function uniqid()
    {

        global $mockUniqid;

        if (isset($mockUniqid) && $mockUniqid === true) {
            // Simply pass the returned argument back
            return func_get_args()[0];
        }

        return call_user_func_array('\uniqid', func_get_args());
    }
}

namespace StudyPortals\Template\Tests\Smoke{

    use PHPUnit\Framework\TestCase;
    use StudyPortals\Template\Parser\Factory;
    use StudyPortals\Template\Repeater;
    use StudyPortals\Template\Template;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */

    class TemplateTest extends TestCase
    {
        /**
         * Compare token-list against reference.
         *
         * @return void
         */

        public function testTokenList()
        {

            global $mockUniqid;
            $mockUniqid = true;

            $reference = (string) file_get_contents(
                __DIR__ . '/Expected/TokenList.bin'
            );
            $reference = unserialize($reference);

            $tokenList = Factory::parseTemplate(
                __DIR__ . '/Resources/TemplateTest.tp4'
            );

            $this->assertEquals($reference, $tokenList);

            $mockUniqid = false;

            $tokenList = Factory::parseTemplate(
                __DIR__ . '/Resources/TemplateTest.tp4'
            );

            $this->assertNotEquals($reference, $tokenList);
        }

        /**
         * Compare cache-file (i.e. fully parsed template) against reference.
         *
         * @return void
         */

        public function testCache()
        {
            @unlink(__DIR__ . '/Resources/TemplateTest.tp4-cache');
            $this->assertFileNotExists(
                __DIR__ . '/Resources/TemplateTest.tp4-cache'
            );

            Template::create(__DIR__ . '/Resources/TemplateTest.tp4');
            $this->assertFileExists(
                __DIR__ . '/Resources/TemplateTest.tp4-cache'
            );

            /*
             * This is terrible, but necessary :(
             *
             * The only environment specific part of the template-cache is the
             * location of the template-file. Without fully reworking Template
             * (or doing some crazy Mocking) there's no way to get rid off this
             * property.
             * So, simple and dirty: Replace the property in the serialised
             * representation with "Hello World!" (which was also done in the
             * reference file).
             *
             * If you start getting unexpected failures of this test, ensure
             * that "Resources/IncludeStatic.html" is checked out with CRLF
             * line-endings (Windows), not with LF line-endings.
             */

            $cache = (string) file_get_contents(
                __DIR__ . '/Resources/TemplateTest.tp4-cache'
            );
            $cache = (string) preg_replace(
                '/file_name";s:[0-9]{1,}:".*";/',
                'file_name";s:12:"Hello World!";',
                $cache
            );

            $this->assertStringEqualsFile(
                __DIR__ . '/Expected/Cache.bin',
                $cache
            );
        }

        /**
         * Compare rendered output against reference.
         *
         * @return void
         */

        public function testRender()
        {

            $template = Template::create(
                __DIR__ . '/Resources/TemplateTest.tp4'
            );
            self::fillTemplate($template);

            /*
             * If you start getting unexpected failures of this test, ensure
             * that "Expected/Render.html" is checked out with LF line-endings.
             */

            $this->assertStringEqualsFile(
                __DIR__ . '/Expected/Render.html',
                $template->__toString()
            );
        }

        /**
         * Fill template with test-data prior to rendering it.
         *
         * @param Template $template
         * @return void
         */

        protected static function fillTemplate(Template $template)
        {

            // Global replaces

            $template->header1 = 'Testing Testing';
            $template->header2 =
                'Hello <strong style="color: #336699">World!</strong>';
            $template->lipsum_bold = true;
            $template->random_string = sha1('Hello World!');

            // Condition sets

            $template->in_set = 2;
            $template->in_set2 = 'foo';

            $template->ListWrapper->value = 'correct foo!';

            // Recursive repeater

            self::recursiveRepeat($template->ListWrapper->MyList);

            // Nested sections

            $template->Test->test = 'Hello World!';

            $template->Test->SomethingElse->Something->lipsum_bold = false;

            $template->Test->SomethingElse->Something->FooTemplate->value1 = '1';
            $template->Test->SomethingElse->Something->FooTemplate->value2 = '12';
            $template->Test->SomethingElse->Something->FooTemplate->value3 = '123';
            $template->Test->SomethingElse->Something->FooTemplate->value4 = '1234';
            $template->Test->SomethingElse->Something->FooTemplate->value5 = '12345';

            // Repeater & Scope

            $template->bullet_item = '<strong>Global</strong>';

            $template->BulletList->bullet_item = 'One';
            $template->BulletList->repeat();
            $template->BulletList->bullet_item = 'Two';
            $template->BulletList->repeat();
            $template->BulletList->repeat();
            $template->BulletList->bullet_item = 'Three';
            $template->BulletList->repeat();

            $template->BulletList->repeat();
            $template->BulletList->repeat();
        }

        /**
         * Helper for recursive repeater test.
         *
         * @param Repeater $repeater
         * @param int $level
         * @return Repeater
         */

        protected static function recursiveRepeat(
            Repeater $repeater,
            int $level = 1
        ) {
            $emptyList = clone $repeater;
            $emptyList->resetTemplate();

            for ($i = 1; $i <= 2; $i++) {
                $repeater->level = "$level-$i";

                if ($level <= 4) {
                    $repeater->SubList = clone $emptyList;
                    self::recursiveRepeat($repeater->SubList, $level + 1);
                }

                $repeater->repeat();
            }

            return $repeater;
        }

        /**
         * Render the expected smoke-test outcomes
         *
         * @return void
         * @SuppressWarnings(PHPMD.StaticAccess)
         */

        public static function renderExpected()
        {
            global $mockUniqid;
            $mockUniqid = true;

            $tokenList = Factory::parseTemplate(
                __DIR__ . '/Resources/TemplateTest.tp4'
            );

            file_put_contents(
                __DIR__ . '/Expected/TokenList.bin',
                serialize($tokenList)
            );

            $mockUniqid = false;

            @unlink(__DIR__ . '/Resources/TemplateTest.tp4-cache');
            $template = Template::create(
                __DIR__ . '/Resources/TemplateTest.tp4'
            );

            $cache = (string) file_get_contents(
                __DIR__ . '/Resources/TemplateTest.tp4-cache'
            );
            $cache = (string) preg_replace(
                '/file_name";s:[0-9]{1,}:".*";/',
                'file_name";s:12:"Hello World!";',
                $cache
            );

            file_put_contents(__DIR__ . '/Expected/Cache.bin', $cache);

            TemplateTest::fillTemplate($template);

            file_put_contents(
                __DIR__ . '/Expected/Render.html',
                $template->__toString()
            );
        }
    }

}
