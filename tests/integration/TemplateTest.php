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
    function uniqid() {

		global $mockUniqid;

		if (isset($mockUniqid) && $mockUniqid === true) {
			// Simply pass the returned argument back
			return func_get_args()[0];
		}

        return call_user_func_array('\uniqid', func_get_args());
	}
}

namespace StudyPortals\TestSuites\PHPUnit\Integration\Template{

	use PHPUnit\Framework\TestCase;
	use StudyPortals\Template\Parser\Factory;
	use StudyPortals\Template\Repeater;
	use StudyPortals\Template\Template;

	class TemplateTest extends TestCase
	{
		/**
		 * Compare token-list against reference.
		 *
		 * @return void
		 * @SuppressWarnings(PHPMD.StaticAccess)
		 */

		public function testTokenList(){

			global $mockUniqid;
			$mockUniqid = true;

			$reference = file_get_contents(__DIR__ . '/Expected/TokenList.bin');
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
			 */

			$cache = file_get_contents(
				__DIR__ . '/Resources/TemplateTest.tp4-cache'
			);
			$cache = preg_replace(
				'/file_name";s:[0-9]{1,}:".*";/',
				'file_name";s:12:"Hello World!";',
				$cache
			);

			$this->assertStringEqualsFile(
				__DIR__ . '/Expected/Cache.bin', $cache
			);
		}

		/**
		 * Compare rendered output against reference.
		 *
		 * @return void
		 */

		public function testRender(){

			$Template = Template::create(
				__DIR__ . '/Resources/TemplateTest.tp4'
			);
			self::fillTemplate($Template);

			$this->assertStringEqualsFile(
				__DIR__ . '/Expected/Render.html', $Template->__toString()
			);

		}

		/**
		 * Fill template with test-data prior to rendering it.
		 *
		 * @param Template $Template
		 * @return void
		 */

		protected static function fillTemplate(Template $Template){

			// Global replaces

			$Template->header1 = 'Testing Testing';
			$Template->header2 =
				'Woei <strong style="color: #336699">Woei</strong> Woei';
			$Template->lipsum_bold = true;
			$Template->random_string = sha1('Hello World!');
			$Template->template_file = 'Test.tp4';

			// Condition sets

			$Template->in_set = 2;
			$Template->in_set2 = 'foo';

			$Template->ListWrapper->value = 'correct woei!';

			// Recursive repeater (correct approach)

			self::recursiveRepeat($Template->ListWrapper->MyList);

			// Nested sections

			$Template->Test->NogIets->Iets->lipsum_bold = false;

			$Template->Test->NogIets->Iets->WoeiTemplate->value1 = '1';
			$Template->Test->NogIets->Iets->WoeiTemplate->value2 = '12';
			$Template->Test->NogIets->Iets->WoeiTemplate->value3 = '123';
			$Template->Test->NogIets->Iets->WoeiTemplate->value4 = '1234';
			$Template->Test->NogIets->Iets->WoeiTemplate->value5 = '12345';

			// Repeater & Scope

			$Template->bullet_item = '<strong>Global</strong>';

			$Template->BulletList->bullet_item = 'One';
			$Template->BulletList->repeat();
			$Template->BulletList->bullet_item = 'Two';
			$Template->BulletList->repeat();
			$Template->BulletList->repeat();
			$Template->BulletList->bullet_item = 'Three';
			$Template->BulletList->repeat();

			$Template->BulletList->repeat();
			$Template->BulletList->repeat();
		}

		/**
		 * Helper for recursive repeater test.
		 *
		 * @param Repeater $Repeater
		 * @param int $level
		 * @return Repeater
		 */

		protected static function recursiveRepeat(
			Repeater $Repeater,
			int $level = 1
		){
			$EmptyList = clone $Repeater;
			$EmptyList->resetTemplate();

			for($i = 1; $i <= 2; $i++){

				$Repeater->level = "$level-$i";

				if($level <= 4){

					$Repeater->SubList = clone $EmptyList;
					self::recursiveRepeat($Repeater->SubList, $level + 1);
				}

				$Repeater->repeat();
			}

			return $Repeater;
		}
	}
}
