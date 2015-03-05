# Quickstart

This extension adds support for OAuth connection to Flickr, so you can seamlessly integrate your application with and provide login through Flickr. You can also communicate with Flickr's API through this extension.

## Installation

The best way to install ipub/flickr is using  [Composer](http://getcomposer.org/):

```json
{
	"require": {
		"ipub/flickr": "dev-master"
	}
}
```

or

```sh
$ composer require ipub/flickr:@dev
```

After that you have to register extension in config.neon.

```neon
extensions:
	flickr: IPub\Flickr\DI\FlickrExtension
```

## Usage

### Basic configuration

This extension creates a special section for configuration for your NEON configuration file. The absolute minimal configuration is appId and appSecret.

```neon
flickr
	appKey      : "123456789"
	appSecret   : "e807f1fcf82d132f9bb018ca6738a19f"
	permission  : "read"
```

There are three allowed permission settings:

* Read : authenticated user will give you the rights only to read his photos, galleries, etc.
* Write : you will be able to create or update authenticated user photos, galleries, etc.
* Delete : as the two previous rights, but with this, you will be able to delete records under use account

And thats all for the basic settings.

### Authentication

Authentication is done through several HTTP requests and redirects and is done through a component model for easy integration into application.

```php
use IPub\Flickr\UI\LoginDialog

class LoginPresenter extends BasePresenter
{
	
	/**
	 * @var \IPub\Flickr\Client
	 */
	private $flickr;

	/**
	 * @var UsersModel
	 */
	private $usersModel;

	/**
	 * You can use whatever way to inject the instance from DI Container,
	 * but let's just use constructor injection for simplicity.
	 *
	 * Class UsersModel is here only to show you how the process should work,
	 * you have to implement it yourself.
	 */
	public function __construct(\IPub\Flickr\Client $flickr, UsersModel $usersModel)
	{
		parent::__construct();

		$this->flickr = $flickr;
		$this->usersModel = $usersModel;
	}

	/**
	 * @return LoginDialog
	 */
	protected function createComponentFlickrLogin()
	{
		$dialog = new LoginDialog($this->flickr);
	
		$dialog->onResponse[] = function (LoginDialog $dialog) {
			$flickr = $dialog->getClient();

			if ( !$flickr->getUser()) {
				$this->flashMessage("Sorry bro, flickr authentication failed.");
				return;
			}

			/**
			 * If we get here, it means that the user was recognized
			 * and we can call the Flickr API
			 */

			try {
				$me = $flickr->getProfile();

				if (!$existing = $this->usersModel->findByFlickrId($flickr->getUser())) {
					/**
					 * Variable $me contains all the public information about the user
					 * including flickr id and name.
					 */
					$existing = $this->usersModel->registerFromFlickr($me);
				}

				/**
				 * You should save the access token to database for later usage.
				 *
				 * You will need it when you'll want to call Flickr API,
				 * when the user is not logged in to your website,
				 * with the access token in his session.
				 */
				$this->usersModel->updateFlickrAccessToken($flickr->getUser(), $flickr->getAccessToken());

				/**
				 * Nette\Security\User accepts not only textual credentials,
				 * but even an identity instance!
				 */
				$this->user->login(new \Nette\Security\Identity($existing->id, $existing->roles, $existing));

				/**
				 * You can celebrate now! The user is authenticated :)
				 */

			} catch (\IPub\OAuth\ApiException $ex) {
				/**
				 * You might wanna know what happened, so let's log the exception.
				 *
				 * Rendering entire bluescreen is kind of slow task,
				 * so might wanna log only $ex->getMessage(), it's up to you
				 */
				Debugger::log($ex, 'flickr');

				$this->flashMessage("Sorry bro, flickr authentication failed hard.");
			}

			$this->redirect('this');
		};

		return $dialog;
	}
}
```

And now whe your component is created, put a link into template

```html
{* By the way, this is how you do a link to signal of subcomponent. *}
<a n:href="flickrLogin-open!">Login using flickr</a>
```

When the user clicks on the link, he will be redirected to the Flickr authentication page where he can allow access for you page or decline it. Whe he confirm your application and requested permission, he will be redirected back to you website.
This authentication action was done in component, so the redirect back link is linked to component signal, that will invoke the event and your **onResponse** callback will be invoked. And from now is quite simple how to work with authenticated user.

## Using Flickr API

The Flickr [API documentation](https://www.flickr.com/services/api/) can be found on their pages. All request are done through api v1 and are configured to return a JSON object.

Some methods don't need authentication, but if your user is authenticated, all request will be done with this authentication.

Calling API's methods is really simple. You just need to include client services to where you want to use it:

```php
$photos = $flickr->api('flickr.galleries.getPhotos', array('gallery_id' => 123))
```

or

```php
$photos = $flickr->get('flickr.galleries.getPhotos', array('gallery_id' => 123))
```

In the output will be and array of photos from selected gallery.

## Best practices

Please keep in mind that the user can revoke the access to his account literary anytime he wants to. Therefore you must wrap every flickr API call with try catch.

```php
try {
	// ...
} catch (\IPub\OAuth\ApiException $ex) {
	// ...
}
```

and if it fails, try requesting the test login. This will tell you if the user revoked your application.

And if he revokes your application, drop the access token, it will never work again, you may only acquire a new one.