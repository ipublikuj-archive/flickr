<?php
/**
 * Test: IPub\Flickr\ConfigurationTest
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
use Nette\Http;

use Tester;

use IPub;
use IPub\Flickr;
use IPub\Flickr\Exceptions;

use IPub\OAuth;
use IPub\OAuth\Api;

require_once __DIR__ . '/../bootstrap.php';

class TestCase extends Tester\TestCase
{
	/**
	 * @var ApiClientMock
	 */
	protected $httpClient;

	/**
	 * @var Flickr\SessionStorage
	 */
	protected $session;

	/**
	 * @var Flickr\Configuration
	 */
	protected $config;

	protected function buildClient($query = [])
	{
		// Please do not abuse this
		$this->config = new IPub\Flickr\Configuration('123', 'abc');

		$url = new Http\UrlScript('http://www.ipublikuj.eu');
		$url->setQuery($query);

		$httpRequest = new Http\Request($url);

		$session = new Http\Session($httpRequest, new Http\Response());
		$session->setStorage(new ArraySessionStorage($session));
		$this->session = new IPub\Flickr\SessionStorage($session, $this->config);

		$this->httpClient = new ApiClientMock();

		$consumer = new OAuth\Consumer('123', 'abc');

		return new IPub\Flickr\Client($consumer, $this->config, $this->session, $this->httpClient, $httpRequest);
	}
}

class ApiClientMock extends Nette\Object implements IPub\OAuth\HttpClient
{
	/**
	 * @var Api\Request[]
	 */
	public $requests = [];

	/**
	 * @var array
	 */
	public $responses = [];

	/**
	 * @param Api\Request $request
	 *
	 * @return Api\Response
	 *
	 * @throws Exceptions\InvalidStateException
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function makeRequest(Api\Request $request)
	{
		if (empty($this->responses)) {
			throw new Exceptions\InvalidStateException("Unexpected request");
		}

		$this->requests[] = $request;
		$request->setHeaders($request->getHeaders() + ['Accept' => 'application/json']); // the CurlClient is setting this as a default

		list($content, $httpCode, $headers, $info) = array_shift($this->responses);
		return new Api\Response($request, $content, $httpCode, $headers, $info);
	}

	public function fakeResponse($content, $httpCode, $headers = [], $info = [])
	{
		$this->responses[] = [$content, $httpCode, $headers, $info];
	}
}

class ArraySessionStorage extends Nette\Object implements Http\ISessionStorage
{
	/**
	 * @var array
	 */
	private $session;

	public function __construct(Http\Session $session = NULL)
	{
		if ($session->isStarted()) {
			$session->destroy();
		}

		$session->setOptions(['cookie_disabled' => TRUE]);
	}

	public function open($savePath, $sessionName)
	{
		$this->session = [];

		return TRUE;
	}

	public function close()
	{
		$this->session = [];

		return TRUE;
	}

	public function read($id)
	{
		return isset($this->session[$id]) ? $this->session[$id] : NULL;
	}

	public function write($id, $data)
	{
		$this->session[$id] = $data;

		return TRUE;
	}

	public function remove($id)
	{
		unset($this->session[$id]);

		return TRUE;
	}

	public function clean($maxlifetime)
	{
		return TRUE;
	}
}
