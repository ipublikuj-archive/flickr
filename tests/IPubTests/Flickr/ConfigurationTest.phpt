<?php
/**
 * Test: IPub\Flickr\Configuration
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

require_once __DIR__ . '/../bootstrap.php';

class ConfigurationTest extends Tester\TestCase
{
	/**
	 * @var Flickr\Configuration
	 */
	private $config;

	protected function setUp()
	{
		$this->config = new Flickr\Configuration('123', 'abc');
	}

	public function testCreateUrl()
	{
		Assert::match('https://api.flickr.com/services/flickr.test.login', (string) $this->config->createUrl('api', 'flickr.test.login'));

		Assert::match('https://www.flickr.com/services/oauth/access_token?oauth_consumer_key=123&oauth_signature_method=HMAC-SHA1', (string) $this->config->createUrl('oauth', 'access_token', array(
			'oauth_consumer_key' => $this->config->consumerKey,
			'oauth_signature_method' => 'HMAC-SHA1'
		)));

		Assert::match('https://www.flickr.com/services/oauth/request_token?oauth_consumer_key=123&oauth_signature_method=HMAC-SHA1', (string) $this->config->createUrl('oauth', 'request_token', array(
			'oauth_consumer_key' => $this->config->consumerKey,
			'oauth_signature_method' => 'HMAC-SHA1'
		)));
	}
}

\run(new ConfigurationTest());