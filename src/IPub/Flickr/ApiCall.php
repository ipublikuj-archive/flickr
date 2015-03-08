<?php
/**
 * ApiCall.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 * @since		5.0
 *
 * @date		06.03.15
 */

namespace IPub\Flickr;

use Nette;
use Nette\Utils;

use IPub;
use IPub\Flickr;
use IPub\Flickr\Api;

use IPub\OAuth;

/**
 * Abstract API calls definition
 *
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 *
 * @author Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class ApiCall extends Nette\Object
{
	/**
	 * @var OAuth\Consumer
	 */
	protected $consumer;

	/**
	 * @var OAuth\HttpClient
	 */
	protected $httpClient;

	/**
	 * @var Configuration
	 */
	protected $config;

	/**
	 * @param OAuth\Consumer $consumer
	 * @param OAuth\HttpClient $httpClient
	 * @param Configuration $config
	 */
	public function __construct(
		OAuth\Consumer $consumer,
		OAuth\HttpClient $httpClient,
		Configuration $config
	){
		$this->consumer = $consumer;
		$this->httpClient = $httpClient;
		$this->config = $config;
	}

	/**
	 * @internal
	 *
	 * @return OAuth\HttpClient
	 */
	public function getHttpClient()
	{
		return $this->httpClient;
	}

	/**
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * @return OAuth\Consumer
	 */
	public function getConsumer()
	{
		return $this->consumer;
	}

	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return OAuth\Token The access token
	 */
	abstract public function getAccessToken();

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function get($path, array $params = [], array $headers = [])
	{
		return $this->api($path, Api\Request::GET, $params, [], $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function head($path, array $params = [], array $headers = [])
	{
		return $this->api($path, Api\Request::HEAD, $params, [], $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function post($path, array $params = [], array $post = [], array $headers = [])
	{
		return $this->api($path, Api\Request::POST, $params, $post, $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function patch($path, array $params = [], array $post = [], array $headers = [])
	{
		return $this->api($path, Api\Request::PATCH, $params, $post, $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function put($path, array $params = [], array $post = [], array $headers = [])
	{
		return $this->api($path, Api\Request::PUT, $params, $post, $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function delete($path, array $params = [], array $headers = [])
	{
		return $this->api($path, Api\Request::DELETE, $params, [], $headers);
	}

	/**
	 * Simply pass anything starting with a slash and it will call the Api, for example
	 * <code>
	 * $details = $flickr->api('flick.people.info');
	 * </code>
	 *
	 * @param string $path
	 * @param string $method The argument is optional
	 * @param array $params Query parameters
	 * @param array $post Post request parameters or body to send
	 * @param array $headers Http request headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function api($path, $method = Api\Request::GET, array $params = [], array $post = [], array $headers = [])
	{
		if (is_array($method)) {
			$headers = $post;
			$post = $params;
			$params = $method;
			$method = Api\Request::GET;
		}

		$params = array_merge($params, [
			'method'            => $path,
			'format'            => 'json',
			'nojsoncallback'    => 1,
		]);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->consumer, $this->config->createUrl('api', 'rest', $params), $method, $post, $headers, $this->getAccessToken()),
			'HMAC-SHA1'
		);

		if (!$response->isJson() || (!$data = Utils\ArrayHash::from($response->toArray())) || Utils\Strings::lower($data->stat) != 'ok') {
			$ex = $response->toException();
			throw $ex;
		}

		if ($response->isPaginated()) {
			return new Paginator($this, $response);
		}

		return Utils\ArrayHash::from($response->toArray());
	}

	/**
	 * Upload photo to the Flickr
	 *
	 * @param string $photo
	 * @param array $params
	 *
	 * @return int
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws OAuth\Exceptions\ApiException|static
	 */
	public function uploadPhoto($photo, array $params = [])
	{
		$data = $this->processImage('upload', $photo, $params);

		return (string) $data->photoid;
	}

	/**
	 * Replace photo in Flickr
	 *
	 * @param string $photo
	 * @param int $photoId
	 * @param bool $async
	 *
	 * @return int
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws OAuth\Exceptions\ApiException|static
	 */
	public function replacePhoto($photo, $photoId, $async = FALSE)
	{
		// Complete request params
		$params = [
			'photo_id' => $photoId,
			'async' => $async ? 1 : 0
		];

		$data = $this->processImage('replace', $photo, $params);

		return (string) $data->photoid;
	}

	/**
	 * @param string $method
	 * @param string $photo Path to image
	 * @param array $params
	 *
	 * @return Utils\ArrayHash
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws OAuth\Exceptions\ApiException|static
	 */
	private function processImage($method, $photo, array $params = [])
	{
		if (!file_exists($photo)) {
			throw new Exceptions\InvalidArgumentException("File '$photo' does not exists. Please provide valid path to file.");
		}

		// Add file to post params
		$post = [
			'photo' => new \CURLFile($photo),
		];

		$post = array_merge($post, $params);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->consumer, $this->config->createUrl('upload', $method), Api\Request::POST, $post, [], $this->getAccessToken()),
			'HMAC-SHA1'
		);

		if ($response->isOk() && $response->isXml() && ($data = Utils\ArrayHash::from($response->toArray())) && $data->{'@attributes'}->stat == 'ok') {
			return $data;

		} else {
			$ex = $response->toException();
			throw $ex;
		}
	}
}