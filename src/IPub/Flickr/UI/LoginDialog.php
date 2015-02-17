<?php
/**
 * FlickrExtension.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	UI
 * @since		5.0
 *
 * @date		17.02.15
 */

namespace IPub\Flickr\UI;

use Nette;
use Nette\Application;
use Nette\Http;

use IPub;
use IPub\Flickr;

/**
 * Component that you can connect to presenter
 * and use as public mediator for Flickr OAuth redirects communication
 *
 * @package		iPublikuj:Flickr!
 * @subpackage	UI
 *
 * @method onResponse(LoginDialog $dialog)
 */
class LoginDialog extends Application\UI\Control
{
	/**
	 * @var array of function(LoginDialog $dialog)
	 */
	public $onResponse = [];

	/**
	 * @var Flickr\Client
	 */
	protected $client;

	/**
	 * @var Flickr\Configuration
	 */
	protected $config;

	/**
	 * @var Flickr\SessionStorage
	 */
	protected $session;

	/**
	 * @var Http\UrlScript
	 */
	protected $currentUrl;

	/**
	 * @param Flickr\Client $flickr
	 */
	public function __construct(Flickr\Client $flickr)
	{
		$this->client = $flickr;
		$this->config = $flickr->getConfig();
		$this->session = $flickr->getSession();
		$this->currentUrl = $flickr->getCurrentUrl();

		parent::__construct();

		$this->monitor('Nette\Application\IPresenter');
	}

	/**
	 * @return Flickr\Client
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * @param Nette\ComponentModel\Container $obj
	 */
	protected function attached($obj)
	{
		parent::attached($obj);

		if ($obj instanceof Nette\Application\IPresenter) {
			$this->currentUrl = new Http\UrlScript($this->link('//response!'));
		}
	}

	public function handleCallback()
	{

	}

	/**
	 * Checks, if there is a user in storage and if not, it redirects to login dialog.
	 * If the user is already in session storage, it will behave, as if were redirected from flickr right now,
	 * this means, it will directly call onResponse event.
	 *
	 * @throws Nette\Application\AbortException
	 */
	public function handleOpen()
	{
		if (!$this->client->getUser()) { // no user
			$this->open();
		}

		$this->onResponse($this);
		$this->presenter->redirect('this');
	}

	/**
	 * @throws Nette\Application\AbortException
	 */
	public function open()
	{
		if ($this->client->obtainRequestToken()) {
			$this->presenter->redirectUrl($this->getUrl());

		} else {

		}
	}

	/**
	 * @return array
	 */
	public function getQueryParams()
	{
		// CSRF
		$this->client->getSession()->establishCSRFTokenState();

		$params = [
			'oauth_token' => $this->session->request_token
		];

		return $params;
	}

	/**
	 * @return string
	 */
	public function getUrl()
	{
		return (string) $this->config->createUrl('oauth', 'authorize', $this->getQueryParams());
	}

	public function handleResponse()
	{
		$this->client->getUser(); // check the received parameters and save user
		$this->onResponse($this);
		$this->presenter->redirect('this', ['state' => NULL, 'code' => NULL]);
	}
}