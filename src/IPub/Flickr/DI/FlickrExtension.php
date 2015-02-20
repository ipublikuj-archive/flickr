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
		'appKey' => NULL,
		'appSecret' => NULL,
		'permission' => 'read',          // read/write/delete
		'clearAllWithLogout' => TRUE,
		'curlOptions' => [],
		'debugger' => '%debugMode%',
	];

	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		Utils\Validators::assert($config['appKey'], 'string', 'Application key');
		Utils\Validators::assert($config['appSecret'], 'string', 'Application secret');
		Utils\Validators::assert($config['permission'], 'string', 'Application permission');

		$builder->addDefinition($this->prefix('client'))
			->setClass('IPub\Flickr\Client');

		$builder->addDefinition($this->prefix('config'))
			->setClass('IPub\Flickr\Configuration', [
				$config['appKey'],
				$config['appSecret'],
			])
			->addSetup('$permission', [$config['permission']]);

		foreach ($config['curlOptions'] as $option => $value) {
			if (defined($option)) {
				unset($config['curlOptions'][$option]);
				$config['curlOptions'][constant($option)] = $value;
			}
		}

		$builder->addDefinition($this->prefix('httpClient'))
			->setClass('IPub\Flickr\Api\CurlClient');
			//->addSetup('$service->curlOptions = ?;', [$config['curlOptions']]);

		$builder->addDefinition($this->prefix('session'))
			->setClass('IPub\Flickr\SessionStorage');
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