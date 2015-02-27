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
use Tracy\Debugger;

require_once __DIR__ . '/TestCase.php';

class ClientTest extends TestCase
{
	public function testUnauthorized()
	{
		$client = $this->buildClient();

		Assert::same(0, $client->getUser());
	}
/*
	public function testAuthorized_savedInSession()
	{
		$client = $this->buildClient();

		$this->session->access_token = 'abcedf';
		$this->session->access_token_secret = 'ghijklmn';
		$this->session->user_id = 123321;

		Assert::same(123321, $client->getUser());
	}

	public function testAuthorized_readUserIdFromAccessToken()
	{
		$client = $this->buildClient();

		$client->setAccessToken([
			'access_token'          => 'abcedf',
			'access_token_secret'   => 'ghijklmn',
		]);

		$this->httpClient->fakeResponse('{"stat":"ok","user":{"id":123321,"name":"Adam Kadlec"}}', 200, ['Content-Type' => 'application/json; charset=utf-8']);

		Assert::same(123321, $client->getUser());
		Assert::count(1, $this->httpClient->requests);

		$secondRequest = $this->httpClient->requests[0];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.flickr.com/services/', (string) $secondRequest->getUrl());
		Assert::same(['Authorization' => 'token abcedf', 'Accept' => 'application/json'], $secondRequest->getHeaders());
	}

	public function testAuthorized_authorizeFromCodeAndState()
	{
		$client = $this->buildClient(array('state' => 'abcdef123456', 'code' => '654321fedcba'));

		$this->session->state = 'abcdef123456'; // The method establishCSRFTokenState() is called in the LoginDialog

		$this->httpClient->fakeResponse('{"access_token":"6dc29d696930cb9b76914bd9d25c63c698957c60","token_type":"bearer","scope":"user:email"}', 200, array('Content-Type' => 'application/json; charset=utf-8'));
		$this->httpClient->fakeResponse('{"login":"fprochazka","id":158625,"type":"User","site_admin":false,"name":"Filip Procházka","email":"filip@prochazka.su"}', 200, array('Content-Type' => 'application/json; charset=utf-8'));

		Assert::same(158625, $client->getUser());
		Assert::count(2, $this->httpClient->requests);

		$firstRequest = $this->httpClient->requests[0];

		Assert::same('POST', $firstRequest->getMethod());
		Assert::match('https://github.com/login/oauth/access_token?client_id=' . $this->config->appId . '&client_secret=' . $this->config->appSecret . '&code=%a%&redirect_uri=%a%', (string) $firstRequest->getUrl());
		Assert::same(array('Accept' => 'application/json'), $firstRequest->getHeaders());

		$secondRequest = $this->httpClient->requests[1];

		Assert::same('GET', $secondRequest->getMethod());
		Assert::match('https://api.github.com/user', (string) $secondRequest->getUrl());
		Assert::same(array('Authorization' => 'token 6dc29d696930cb9b76914bd9d25c63c698957c60', 'Accept' => 'application/vnd.github.v3+json'), $secondRequest->getHeaders());
	}
*/
}

\run(new ClientTest());
