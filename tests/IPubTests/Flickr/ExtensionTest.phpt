<?php
/**
 * Test: IPub\Flickr\Extension
 * @testCase
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	Tests
 * @since		5.0
 *
 * @date		26.02.15
 */

namespace IPubTests\Flickr;

use Nette;

use Tester;
use Tester\Assert;

use IPub;
use IPub\Flickr;

require __DIR__ . '/../bootstrap.php';

class ExtensionTest extends Tester\TestCase
{
	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		Flickr\DI\FlickrExtension::register($config);

		$config->addConfig(__DIR__ . '/files/config.neon', $config::NONE);

		return $config->createContainer();
	}

	public function testCompilersServices()
	{
		$dic = $this->createContainer();

		Assert::true($dic->getService('flickr.client') instanceof IPub\Flickr\Client);
		Assert::true($dic->getService('flickr.config') instanceof IPub\Flickr\Configuration);
		Assert::true($dic->getService('flickr.session') instanceof IPub\Flickr\SessionStorage);
	}
}

\run(new ExtensionTest());