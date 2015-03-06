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

/**
 * Flicker's API and OAuth client
 *
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 *
 * @author Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Client extends ApiCall
{
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
	private $user;

	/**
	 * The OAuth access token received in exchange for a valid authorization code
	 * null means the access token has yet to be determined
	 *
	 * @var OAuth\Token
	 */
	private $accessToken;

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
		parent::__construct($consumer, $httpClient, $config);

		$this->session = $session;
		$this->httpRequest = $httpRequest;

		$this->consumer->setCallbackUrl($this->getCurrentUrl());
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
	 * @param string|null $callback
	 *
	 * @return bool
	 */
	public function obtainRequestToken($callback = NULL)
	{
		// Before first handshake, session have to cleared
		$this->session->clearAll();

		// Complete request params
		$params = [
			'oauth_callback' => $callback ?:$this->consumer->getCallbackUrl(),
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