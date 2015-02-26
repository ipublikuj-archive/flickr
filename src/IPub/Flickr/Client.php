<?php
/**
 * Client.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 * @since		5.0
 *
 * @date		17.02.15
 */

namespace IPub\Flickr;

use Nette;
use Nette\Http;
use Nette\Utils;

use Kdyby\Curl;

use IPub;
use IPub\Flickr;
use IPub\Flickr\Api;

class Client extends Nette\Object
{
	/**
	 * OAuth version
	 */
	const VERSION = '1.0';

	/**
	 * @var Api\CurlClient
	 */
	private $httpClient;

	/**
	 * @var Configuration
	 */
	private $config;

	/**
	 * @var SessionStorage
	 */
	private $session;

	/**
	 * @var Http\IRequest
	 */
	private $httpRequest;

	/**
	 * The ID of the Flickr user, or 0 if the user is logged out
	 *
	 * @var integer
	 */
	protected $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization code
	 * null means the access token has yet to be determined
	 *
	 * @var array|null
	 */
	protected $accessToken;

	/**
	 * @param Configuration $config
	 * @param SessionStorage $session
	 * @param Api\CurlClient $httpClient
	 * @param Http\IRequest $httpRequest
	 */
	public function __construct(
		Configuration $config,
		SessionStorage $session,
		Api\CurlClient $httpClient,
		Nette\Http\IRequest $httpRequest
	){
		$this->config = $config;
		$this->session = $session;
		$this->httpClient = $httpClient;
		$this->httpRequest = $httpRequest;
	}

	/**
	 * @return Configuration
	 */
	public function getConfig()
	{
		return $this->config;
	}

	/**
	 * @return SessionStorage
	 */
	public function getSession()
	{
		return $this->session;
	}

	/**
	 * @return Http\UrlScript
	 */
	public function getCurrentUrl()
	{
		return clone $this->httpRequest->getUrl();
	}

	/**
	 * @internal
	 *
	 * @return Api\CurlClient
	 */
	public function getHttpClient()
	{
		return $this->httpClient;
	}

	/**
	 * Sets the access token for api calls. Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param array|string $token an access token.
	 *
	 * @return $this
	 *
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function setAccessToken($token)
	{
		if (!is_array($token)) {
			try {
				$token = Utils\Json::decode($token, Utils\Json::FORCE_ARRAY);

			} catch (Utils\JsonException $ex) {
				throw new Exceptions\InvalidArgumentException($ex->getMessage(), 0, $ex);
			}
		}

		if (!isset($token['access_token'])) {
			throw new Exceptions\InvalidArgumentException("It's required that the token has 'access_token' or 'refresh_token' field.");
		}

		if (isset($token['access_token_secret'])) {
			$this->setAccessTokenSecret($token['access_token_secret']);
		}

		$this->accessToken = $token;

		return $this;
	}

	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @param string $key
	 *
	 * @return array|string The access token
	 */
	public function getAccessToken($key = NULL)
	{
		if ($this->accessToken === NULL && ($accessToken = $this->getUserAccessToken())) {
			$this->setAccessToken($accessToken);
		}

		if ($key !== NULL) {
			return array_key_exists($key, $this->accessToken) ? $this->accessToken[$key] : NULL;
		}

		return $this->accessToken;
	}

	/**
	 * @param string $secret
	 *
	 * @return $this
	 */
	public function setAccessTokenSecret($secret)
	{
		$this->session->access_token_secret = $secret;

		return $this;
	}

	/**
	 * Determines and returns the user access token, first using
	 * the signed request if present, and then falling back on
	 * the authorization code if present.  The intent is to
	 * return a valid user access token, or false if one is determined
	 * to not be available.
	 *
	 * @return string A valid user access token, or false if one could not be determined.
	 */
	protected function getUserAccessToken()
	{
		if (($verifier = $this->getVerifier()) && $verifier != $this->session->verifier && ($token = $this->getToken()) && $token != $this->session->token) {
			if ($this->obtainAccessToken($verifier, $token)) {
				$this->session->verifier = $verifier;
				$this->session->token = $token;

				return [
					'access_token'          => $this->session->access_token,
					'access_token_secret'   => $this->session->access_token_secret
				];
			}

			// verifier was bogus, so everything based on it should be invalidated.
			$this->session->clearAll();

			return FALSE;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return [
			'access_token'          => $this->session->access_token,
			'access_token_secret'   => $this->session->access_token_secret
		];
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws Exceptions\ApiException
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
	 * @throws Exceptions\ApiException
	 */
	public function head($path, array $params = [], array $headers = [])
	{
		return $this->api($path, Api\Request::HEAD, $params, [], $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws Exceptions\ApiException
	 */
	public function post($path, array $params = [], $post = [], array $headers = [])
	{
		return $this->api($path, Api\Request::POST, $params, $post, $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws Exceptions\ApiException
	 */
	public function patch($path, array $params = [], $post = [], array $headers = [])
	{
		return $this->api($path, Api\Request::PATCH, $params, $post, $headers);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array|string $post
	 * @param array $headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|Utils\ArrayHash[]
	 *
	 * @throws Exceptions\ApiException
	 */
	public function put($path, array $params = [], $post = [], array $headers = [])
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
	 * @throws Exceptions\ApiException
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
	 * @param array|string $post Post request parameters or body to send
	 * @param array $headers Http request headers
	 *
	 * @return Utils\ArrayHash|string|Paginator|ArrayHash[]
	 *
	 * @throws Exceptions\ApiException
	 */
	public function api($path, $method = Api\Request::GET, array $params = [], $post = [], array $headers = [])
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

		$params = array_merge($params, $this->getOauthParams());

		$params['oauth_token'] = $this->getAccessToken('access_token');

		$params['oauth_signature'] = $this->getSignature($method, $this->config->createUrl('api', 'rest'), array_merge($params, $post));

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->config->createUrl('api', 'rest', $params), $method, $post, $headers)
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
	 * @throws Exceptions\ApiException|static
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function uploadPhoto($photo, array $params = [])
	{
		return $this->processImage('upload', $photo, $params);
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
	 * @throws Exceptions\ApiException|static
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function replacePhoto($photo, $photoId, $async = FALSE)
	{
		// Complete request params
		$params = [
			'photo_id' => $photoId,
			'async' => $async ? 1 : 0
		];

		return $this->processImage('replace', $photo, $params);
	}

	/**
	 * @param string $method
	 * @param string $photo Path to image
	 * @param array $params
	 *
	 * @return string
	 *
	 * @throws Exceptions\ApiException|static
	 * @throws Exceptions\InvalidArgumentException
	 */
	private function processImage($method, $photo, array $params = [])
	{
		if (!file_exists($photo)) {
			throw new Flickr\Exceptions\InvalidArgumentException("File '$photo' does not exists. Please provide valid path to file.");
		}

		// Complete request params
		$params = array_merge($params, $this->getOauthParams());
		$params['oauth_token'] = $this->getAccessToken('access_token');

		$params['oauth_signature'] = $this->getSignature(Api\Request::POST, $this->config->createUrl('upload', $method), $params);

		// Add file to post params
		$params['photo'] = new \CURLFile($photo);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->config->createUrl('upload', $method), Api\Request::POST, $params)
		);

		// Parse REST xml response
		$xmlContent = simplexml_load_string($response->getContent());

		if ($response->isOk() && Utils\Strings::lower((string) $xmlContent['stat']) == 'ok' && $photoId = (string) $xmlContent->photoid[0]) {
			return $photoId;

		} else {
			$ex = $response->toException();
			throw $ex;
		}
	}

	/**
	 * Get the UID of the connected user, or 0 if the Flickr user is not connected.
	 *
	 * @return string the UID if available.
	 */
	public function getUser()
	{
		if ($this->user === NULL) {
			$this->user = $this->getUserFromAvailableData();
		}

		return $this->user;
	}

	/**
	 * @param int|string $profileId
	 *
	 * @return Profile
	 */
	public function getProfile($profileId = NULL)
	{
		return new Profile($this, $profileId);
	}

	/**
	 * Retrieves the UID with the understanding that $this->accessToken has already been set and is seemingly legitimate
	 * It relies on Flicker's API to retrieve user information and then extract the user ID.
	 *
	 * @return integer Returns the UID of the Flicker user, or 0 if the Flicker user could not be determined
	 */
	protected function getUserFromAccessToken()
	{
		try {
			$user = $this->get('flickr.test.login');

			return $user->offsetExists('user') ? $user->user->id : 0;

		} catch (\Exception $e) { }

		return 0;
	}

	/**
	 * Determines the connected user by first examining any signed
	 * requests, then considering an authorization code, and then
	 * falling back to any persistent store storing the user.
	 *
	 * @return integer The id of the connected Flickr user, or 0 if no such user exists
	 */
	protected function getUserFromAvailableData()
	{
		$user = $this->session->get('user_id', 0);

		// use access_token to fetch user id if we have a user access_token, or if
		// the cached access token has changed
		if (($accessToken = $this->getAccessToken()) && !($user && $this->session->access_token === $accessToken)) {
			if (!$user = $this->getUserFromAccessToken()) {
				$this->session->clearAll();

			} else {
				$this->session->user_id = $user;
			}
		}

		return $user;
	}

	/**
	 * Get a request token from Flickr
	 *
	 * @param string $callback
	 *
	 * @return bool
	 */
	public function obtainRequestToken($callback)
	{
		// Before first handshake, session have to cleared
		$this->session->clearAll();

		// Complete request params
		$params = $this->getOauthParams();
		$params['oauth_callback'] = $callback;
		$params['oauth_signature'] = $this->getSignature(Api\Request::GET, $this->config->createUrl('oauth', 'request_token'), $params);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->config->createUrl('oauth', 'request_token', $params), Api\Request::GET)
		);

		if ($response->isOk()) {
			$token = [];
			parse_str($response->getContent(), $token);

			if (isset($token['oauth_callback_confirmed']) && Utils\Strings::lower($token['oauth_callback_confirmed']) == 'true') {
				if (isset($token['oauth_token'])) {
					$this->session->request_token = $token['oauth_token'];

				} else {
					return FALSE;
				}

				if (isset($token['oauth_token_secret'])) {
					$this->session->request_token_secret = $token['oauth_token_secret'];

				} else {
					return FALSE;
				}

				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	 * Retrieves an access token and access token secret for the given authorization verifier and token
	 * (previously generated from www.flickr.com on behalf of a specific user).
	 * The authorization verifier and token is sent to www.flickr.com/services/oauth
	 * and a legitimate access token is generated provided the access token
	 * and the user for which it was generated all match, and the user is
	 * either logged in to Flickr or has granted an offline access permission
	 *
	 * @param string $verifier
	 * @param string $token
	 *
	 * @return bool
	 */
	protected function obtainAccessToken($verifier, $token)
	{
		if (empty($verifier) || empty($token)) {
			return FALSE;
		}

		$params = $this->getOauthParams();
		$params['oauth_token'] =  $token;
		$params['oauth_verifier'] = $verifier;
		$params['oauth_signature'] = $this->getSignature(Api\Request::GET, $this->config->createUrl('oauth', 'access_token'), $params);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->config->createUrl('oauth', 'access_token', $params), Api\Request::GET)
		);

		if ($response->isOk()) {
			$token = [];
			parse_str($response->getContent(), $token);

			if (isset($token['oauth_token'])) {
				$this->session->access_token = $token['oauth_token'];

			} else {
				return FALSE;
			}

			if (isset($token['oauth_token_secret'])) {
				$this->session->access_token_secret = $token['oauth_token_secret'];

			} else {
				return FALSE;
			}

		} else {
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Sign an array of parameters with an OAuth signature
	 *
	 * @internal
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $parameters
	 *
	 * @return string
	 */
	public function getSignature($method, $url, $parameters)
	{
		$baseString = $this->getBaseString($method, $url, $parameters);

		$keyPart1 = $this->config->appSecret;
		$keyPart2 = $this->session->access_token_secret;

		if (empty($keyPart2)) {
			$keyPart2 = $this->session->request_token_secret;
		}

		if (empty($keyPart2)) {
			$keyPart2 = '';
		}

		$key = "$keyPart1&$keyPart2";

		return base64_encode($this->hmac('sha1', $baseString, $key));
	}

	/**
	 * Get the base string for creating an OAuth signature
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $parameters
	 * @return string
	 */
	private function getBaseString($method, $url, $parameters)
	{
		ksort($parameters, SORT_STRING);

		$components = [
			rawurlencode($method),
			rawurlencode($url),
			rawurlencode($this->joinParameters($parameters))
		];

		$baseString = implode('&', $components);

		return $baseString;
	}

	/**
	 * Join an array of parameters together into a URL-encoded string
	 *
	 * @param array $parameters
	 *
	 * @return string
	 */
	private function joinParameters($parameters)
	{
		$keyValuePairs = [];

		foreach ($parameters as $key=>$value)  {
			array_push($keyValuePairs, rawurlencode($key) . "=" . rawurlencode($value));
		}

		return implode('&', $keyValuePairs);
	}

	/**
	 * Get the standard OAuth parameters
	 *
	 * @return array
	 */
	private function getOauthParams()
	{
		$params = [
			'oauth_nonce' => $this->makeNonce(),
			'oauth_timestamp' => time(),
			'oauth_consumer_key' => $this->config->appKey,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version' => self::VERSION,
		];

		return $params;
	}

	/**
	 * Create a nonce
	 *
	 * @return string
	 */
	private function makeNonce()
	{
		// Create a string that will be unique for this app and this user at this time
		$reasonablyDistinctiveString = implode(':',
			[
				$this->config->appSecret,
				$this->session->user_id,
				microtime()
			]
		);

		return md5($reasonablyDistinctiveString);
	}

	/**
	 * @param string $function
	 * @param string $data
	 * @param string $key
	 *
	 * @return string
	 */
	private function hmac($function, $data, $key)
	{
		switch($function)
		{
			case 'sha1':
				$pack = 'H40';
				break;

			default:
				return '';
		}

		if (strlen($key) > 64) {
			$key = pack($pack, $function($key));
		}

		if (strlen($key) < 64) {
			$key = str_pad($key, 64, "\0");
		}

		return (pack($pack, $function((str_repeat("\x5c", 64) ^ $key) . pack($pack, $function((str_repeat("\x36", 64) ^ $key) . $data)))));
	}

	/**
	 * Get the authorization verifier from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization verifier was
	 * discoverable.
	 *
	 * @return mixed The authorization verifier, or false if the authorization verifier could not be determined.
	 */
	protected function getVerifier()
	{
		if ($verifier = $this->getRequest('oauth_verifier')) {
			return $verifier;
		}

		return FALSE;
	}

	/**
	 * Get the authorization verifier from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization verifier was
	 * discoverable.
	 *
	 * @return mixed The authorization verifier, or false if the authorization verifier could not be determined.
	 */
	protected function getToken()
	{
		if ($token = $this->getRequest('oauth_token')) {
			return $token;
		}

		return FALSE;
	}

	/**
	 * Destroy the current session
	 *
	 * @return $this
	 */
	public function destroySession()
	{
		$this->accessToken = NULL;
		$this->user = NULL;
		$this->session->clearAll();

		return $this;
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed|null
	 */
	protected function getRequest($key, $default = NULL)
	{
		if ($value = $this->httpRequest->getPost($key)) {
			return $value;
		}

		if ($value = $this->httpRequest->getQuery($key)) {
			return $value;
		}

		return $default;
	}
}