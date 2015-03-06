<?php
/**
 * FlickrExtension.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	DI
 * @since		5.0
 *
 * @date		17.02.15
 */

namespace IPub\Flickr\DI;

use Nette;
use Nette\DI;
use Nette\Utils;
use Nette\PhpGenerator as Code;

use Tracy;

use IPub;
use IPub\Flickr;

class FlickrExtension extends DI\CompilerExtension
{
	/**
	 * Extension default configuration
	 *
	 * @var array
	 */
	protected $defaults = [
		'consumerKey' => NULL,
		'consumerSecret' => NULL,
		'permission' => 'read',          // read/write/delete
		'clearAllWithLogout' => TRUE
	];

	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		Utils\Validators::assert($config['consumerKey'], 'string', 'Application key');
		Utils\Validators::assert($config['consumerSecret'], 'string', 'Application secret');
		Utils\Validators::assert($config['permission'], 'string', 'Application permission');

		// Create oAuth consumer
		$consumer = new IPub\OAuth\Consumer($config['consumerKey'], $config['consumerSecret']);

		$builder->addDefinition($this->prefix('client'))
			->setClass('IPub\Flickr\Client', [$consumer]);

		$builder->addDefinition($this->prefix('config'))
			->setClass('IPub\Flickr\Configuration', [
				$config['consumerKey'],
				$config['consumerSecret'],
			])
			->addSetup('$permission', [$config['permission']]);

		$builder->addDefinition($this->prefix('session'))
			->setClass('IPub\Flickr\SessionStorage');

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->clearAll(); }', array(
					'@container', '@self', $this->prefix('session')
				));
		}
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 */
	public static function register(Nette\Configurator $config, $extensionName = 'flickr')
	{
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new FlickrExtension());
		};
	}
}