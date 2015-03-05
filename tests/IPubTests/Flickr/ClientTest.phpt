<?php
/**
 * Test: IPub\Flickr\Client
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

require_once __DIR__ . '/TestCase.php';

class ClientTest extends TestCase
{
	public function testUnauthorized()
	{
		$client = $this->buildClient();

		Assert::same(0, $client->getUser());
	}

	public function testAuthorized_savedInSession()
	{
		$client = $this->buildClient();

		$session = $client->getSession();
		$session->access_token = 'abcedf';
		$session->access_token_secret = 'ghijklmn';
		$session->user_id = 123321;

		Assert::same(123321, $client->getUser());
	}

	public function testAuthorized_readUserIdFromAccessToken()
	{
		$client = $this->buildClient();

		$client->setAccessToken([
			'access_token'          => 'abcedf',
			'access_token_secret'   => 'ghijklmn',
		]);

		$this->httpClient->fakeResponse('{"stat":"ok","user":{"id":"21207597%40N07","username":{"_content":"john.doe"}}}', 200, ['Content-Type' => 'application/json; charset=utf-8']);

		Assert::same('21207597%40N07', $client->getUser());
		Assert::count(1, $this->httpClient->requests);

		$secondRequest = $this->httpClient->requests[0];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.flickr.com/services/rest', $secondRequest->getUrl()->getHostUrl() . $secondRequest->getUrl()->getPath());
		Assert::same(['Accept' => 'application/json'], $secondRequest->getHeaders());
	}

	public function testAuthorized_authorizeFromVerifierAndToken()
	{
		$client = $this->buildClient(array('oauth_verifier' => 'abcedf', 'oauth_token' => 'ghijklmn'));

		$this->httpClient->fakeResponse('fullname=John%20Doe&oauth_token=72157626318069415-087bfc7b5816092c&oauth_token_secret=a202d1f853ec69de&user_nsid=21207597%40N07&username=john.doe', 200, ['Content-Type' => 'application/json; charset=utf-8']);
		$this->httpClient->fakeResponse('{"stat":"ok","user":{"id":"21207597%40N07","username":{"_content":"john.doe"}}}', 200, ['Content-Type' => 'application/json; charset=utf-8']);

//		Assert::same('21207597%40N07', $client->getUser());
		Assert::count(2, $this->httpClient->requests);

		$firstRequest = $this->httpClient->requests[0];

		Assert::same('GET', $firstRequest->getMethod());
		Assert::match('https://www.flickr.com/services/oauth/access_token', $firstRequest->getUrl()->getHostUrl() . $firstRequest->getUrl()->getPath());
		Assert::same(['Accept' => 'application/json'], $firstRequest->getHeaders());

		$secondRequest = $this->httpClient->requests[1];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.flickr.com/services/rest', $secondRequest->getUrl()->getHostUrl() . $secondRequest->getUrl()->getPath());
		Assert::same(['Accept' => 'application/json'], $secondRequest->getHeaders());
	}
}

\run(new ClientTest());
