<?php
/**
 * ApiException.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	Exceptions
 * @since		5.0
 *
 * @date		18.02.15
 */

namespace IPub\Flickr\Exceptions;

use IPub;
use IPub\Flickr\Api;

class ApiException extends \RuntimeException implements IException
{
	/**
	 * @var Api\Request|NULL
	 */
	public $request;

	/**
	 * @var Api\Response|NULL
	 */
	public $response;

	/**
	 * @param Api\Request $request
	 * @param Api\Response $response
	 *
	 * @return $this|static
	 */
	public function bindResponse(Api\Request $request, Api\Response $response = NULL)
	{
		$this->request = $request;
		$this->response = $response;

		return $this;
	}
}