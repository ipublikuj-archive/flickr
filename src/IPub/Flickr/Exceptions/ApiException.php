<?php
/**
 * RequestFailedException.php
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
	 * @return ApiException|static
	 */
	public function bindResponse(Api\Request $request, Api\Response $response = NULL)
	{
		$this->request = $request;
		$this->response = $response;

		return $this;
	}
}