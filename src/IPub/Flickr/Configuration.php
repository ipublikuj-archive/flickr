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

class Configuration extends Nette\Object
{
	/**
	 * @var string
	 */
	public $appKey;

	/**
	 * @var string
	 */
	public $appSecret;

	/**
	 * @var string
	 */
	public $permission;

	/**
	 * @var array
	 */
	public $domains = [
		'oauth' => 'https://www.flickr.com/services/oauth/',
		'api' => 'https://api.flickr.com/services/',
	];

	/**
	 * @param string $appKey
	 * @param string $appSecret
	 */
	public function __construct($appKey, $appSecret)
	{
		$this->appKey = $appKey;
		$this->appSecret = $appSecret;
	}

	/**
	 * Build the URL for given domain alias, path and parameters.
	 *
	 * @param string $name The name of the domain
	 * @param string $path Optional path (without a leading slash)
	 * @param array $params Optional query parameters
	 *
	 * @return Http\UrlScript The URL for the given parameters
	 */
	public function createUrl($name, $path = NULL, $params = [])
	{
		if (preg_match('~^https?://([^.]+\\.)?flickr\\.com/~', trim($path))) {
			$url = new Http\UrlScript($path);

		} else {
			$url = new Http\UrlScript($this->domains[$name]);
			$url->path .= ltrim($path, '/');
		}

		$url->appendQuery(array_map(function ($param) {
			return $param instanceof Http\UrlScript ? (string) $param : $param;
		}, $params));

		return $url;
	}
}