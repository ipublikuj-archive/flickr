<?php
/**
 * Configuration.php
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

class Client extends Nette\Object
{
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
	 * @var string
	 */
	protected $accessToken;

	/**
	 * @param Configuration $config
	 * @param SessionStorage $session
	 * @param Http\IRequest $httpRequest
	 */
	public function __construct(
		Configuration $config,
		SessionStorage $session,
		Nette\Http\IRequest $httpRequest
	){
		$this->config = $config;
		$this->session = $session;
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
	 * Sets the access token for api calls. Use this if you get
	 * your access token by other means and just want the SDK
	 * to use it.
	 *
	 * @param string $accessToken an access token.
	 *
	 * @return $this
	 */
	public function setAccessToken($accessToken)
	{
		$this->accessToken = $accessToken;

		return $this;
	}

	/**
	 * Determines the access token that should be used for API calls.
	 * The first time this is called, $this->accessToken is set equal
	 * to either a valid user access token, or it's set to the application
	 * access token if a valid user access token wasn't available.  Subsequent
	 * calls return whatever the first call returned.
	 *
	 * @return string The access token
	 */
	public function getAccessToken()
	{
		if ($this->accessToken !== NULL) {
			return $this->accessToken; // we've done this already and cached it. Just return.
		}

		if ($accessToken = $this->getUserAccessToken()) {
			$this->setAccessToken($accessToken);
		}

		return $this->accessToken;
	}

	/**
	 * Get the authorization code from the query parameters, if it exists,
	 * and otherwise return false to signal no authorization code was
	 * discoverable.
	 *
	 * @return mixed The authorization code, or false if the authorization code could not be determined.
	 */
	protected function getCode()
	{
		$state = $this->getRequest('state');

		if (($code = $this->getRequest('code')) && $state && $this->session->state === $state) {
			$this->session->state = NULL; // CSRF state has done its job, so clear it
			return $code;
		}

		return FALSE;
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
	 * Retrieves the UID with the understanding that $this->accessToken has already been set and is seemingly legitimate
	 * It relies on Flicker's API to retrieve user information and then extract the user ID.
	 *
	 * @return integer Returns the UID of the Flicker user, or 0 if the Flicker user could not be determined
	 */
	protected function getUserFromAccessToken()
	{
		try {
			$user = $this->get('/user');

			return isset($user['id']) ? $user['id'] : 0;

		} catch (\Exception $e) { }

		return 0;
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
		if (($code = $this->getCode()) && $code != $this->session->code) {
			if ($accessToken = $this->getAccessTokenFromCode($code)) {
				$this->session->code = $code;
				return $this->session->access_token = $accessToken;
			}

			// code was bogus, so everything based on it should be invalidated.
			$this->session->clearAll();

			return FALSE;
		}

		// as a fallback, just return whatever is in the persistent
		// store, knowing nothing explicit (signed request, authorization
		// code, etc.) was present to shadow it (or we saw a code in $_REQUEST,
		// but it's the same as what's in the persistent store)
		return $this->session->access_token;
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






	/**
	 * Get a request token from Flickr
	 *
	 * @return bool
	 */
	private function obtainRequestToken()
	{
		$params = $this->getOauthParams();
		$params['oauth_callback'] = $this->callback;
		$this->sign(self::REQUEST_TOKEN_ENDPOINT, $params);
		$response = $this->httpRequest(self::REQUEST_TOKEN_ENDPOINT, $params);

		if ((bool) $response['oauth_callback_confirmed']) {
			$this->session->request_token = $response['oauth_token'];
			$this->session->request_token_secret = $response['oauth_token_secret'];

			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Sign an array of parameters with an OAuth signature
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $parameters
	 *
	 * @return string
	 */
	private function sign($method, $url, $parameters)
	{
		$baseString = $this->getBaseString($method, $url, $parameters);

		$keyPart1 = $this->config->appSecret;
		$keyPart2 = $this->session->access_token;

		if (empty($keyPart2)) {
			$keyPart2 = $this->session->request_token_secret;
		}

		if (empty($keyPart2)) {
			$keyPart2 = '';
		}

		$key = "$keyPart1&$keyPart2";

		return base64_encode(hash_hmac('sha1', $baseString, $key, TRUE));
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
		$components = [
			rawurlencode($method),
			rawurlencode($url),
			rawurlencode(http_build_query($parameters))
		];

		$baseString = implode('&', $components);

		return $baseString;
	}

	/**
	 * Get the standard OAuth parameters
	 *
	 * @return array
	 */
	private function getOauthParams()
	{
		$params = array (
			'oauth_nonce' => $this->makeNonce(),
			'oauth_timestamp' => time(),
			'oauth_consumer_key' => $this->config->appSecret,
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_version' => '1.0',
		);

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
}