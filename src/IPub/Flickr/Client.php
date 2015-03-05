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

use IPub;
use IPub\Flickr;
use IPub\Flickr\Api;

use IPub\OAuth;

class Client extends Nette\Object
{
	/**
	 * @var OAuth\Consumer
	 */
	protected $consumer;

	/**
	 * @var OAuth\HttpClient
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
	 * @var OAuth\Token
	 */
	protected $accessToken;

	/**
	 * @param OAuth\Consumer $consumer
	 * @param OAuth\HttpClient $httpClient
	 * @param Configuration $config
	 * @param SessionStorage $session
	 * @param Http\IRequest $httpRequest
	 */
	public function __construct(
		OAuth\Consumer $consumer,
		OAuth\HttpClient $httpClient,
		Configuration $config,
		SessionStorage $session,
		Nette\Http\IRequest $httpRequest
	){
		$this->consumer = $consumer;
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
	 * @return OAuth\HttpClient
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

		if (!isset($token['access_token']) || !isset($token['access_token_secret'])) {
			throw new Exceptions\InvalidArgumentException("It's required that the token has 'access_token' and 'access_token_secret' field.");
		}

		$this->accessToken = new OAuth\Token($token['access_token'], $token['access_token_secret']);

		return $this;
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
	public function getAccessToken()
	{
		if ($this->accessToken === NULL && ($accessToken = $this->getUserAccessToken())) {
			$this->setAccessToken($accessToken);
		}

		return $this->accessToken;
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
		if (($verifier = $this->getVerifier()) && ($token = $this->getToken())) {
			if ($this->obtainAccessToken($verifier, $token)) {
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
	 * @throws OAuth\Exceptions\ApiException
	 */
	public function get($path, array $params = [], array $headers = [])
	{
		return $this->api($path, OAuth\Api\Request::GET, $params, [], $headers);
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
		return $this->api($path, OAuth\Api\Request::HEAD, $params, [], $headers);
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
		return $this->api($path, OAuth\Api\Request::POST, $params, $post, $headers);
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
		return $this->api($path, OAuth\Api\Request::PATCH, $params, $post, $headers);
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
		return $this->api($path, OAuth\Api\Request::PUT, $params, $post, $headers);
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
		return $this->api($path, OAuth\Api\Request::DELETE, $params, [], $headers);
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
	public function api($path, $method = OAuth\Api\Request::GET, array $params = [], array $post = [], array $headers = [])
	{
		if (is_array($method)) {
			$headers = $post;
			$post = $params;
			$params = $method;
			$method = OAuth\Api\Request::GET;
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

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->consumer, $this->config->createUrl('upload', $method, $params), OAuth\Api\Request::POST, $post, [], $this->getAccessToken()),
			'HMAC-SHA1'
		);

		if ($response->isOk() && $response->isXml() && ($data = Utils\ArrayHash::from($response->toArray())) && $data->{'@attributes'}->stat == 'ok') {
			return $data;

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

			if ($user instanceof Utils\ArrayHash && $user->offsetExists('user')) {
				return $user->user->id;
			}

		// User could not be checked through API calls
		} catch (\Exception $ex) {
			// Is not necessary to throw exception
			// when call fails. This fail was already logged.
		}

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
		if (($accessToken = $this->getAccessToken()) && !($user && $this->session->access_token === $accessToken->getToken())) {
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
		$params = [
			'oauth_callback' => $callback,
		];

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->consumer, $this->config->createUrl('oauth', 'request_token', $params), OAuth\Api\Request::GET),
			'HMAC-SHA1'
		);

		if (!$response->isOk() || !$response->isQueryString() || (!$data = Utils\ArrayHash::from($response->toArray()))) {
			return FALSE;

		} else if ($data->offsetExists('oauth_callback_confirmed')
			&& Utils\Strings::lower($data->oauth_callback_confirmed) == 'true'
			&& $data->offsetExists('oauth_token')
			&& $data->offsetExists('oauth_token_secret')
		) {
			$this->session->request_token = $data->oauth_token;
			$this->session->request_token_secret = $data->oauth_token_secret;

			return TRUE;
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

		// Complete request params
		$params = [
			'oauth_token' =>  $token,
			'oauth_verifier' => $verifier,
		];

		$token = new OAuth\Token($this->session->request_token, $this->session->request_token_secret);

		$response = $this->httpClient->makeRequest(
			new Api\Request($this->consumer, $this->config->createUrl('oauth', 'access_token', $params), OAuth\Api\Request::GET, [], [], $token),
			'HMAC-SHA1'
		);

		if (!$response->isOk() || !$response->isQueryString() || (!$data = Utils\ArrayHash::from($response->toArray()))) {
			// most likely that user very recently revoked authorization.
			// In any event, we don't have an access token, so say so.
			return FALSE;

		} else if ($data->offsetExists('oauth_token') && $data->offsetExists('oauth_token_secret')) {
			// Clear unused variables
			$this->session->clearAll();

			// Store access token to session
			$this->session->access_token = $data->oauth_token;
			$this->session->access_token_secret = $data->oauth_token_secret;

			return TRUE;
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