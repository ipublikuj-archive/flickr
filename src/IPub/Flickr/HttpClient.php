<?php
/**
 * HttpClient.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 * @since		5.0
 *
 * @date		19.02.15
 */

namespace IPub\Flickr;

use Nette;

use IPub;
use IPub\Flickr\Exceptions;

interface HttpClient
{
	/**
	 * @param Api\Request $request
	 *
	 * @return Api\Response
	 *
	 * @throws Exceptions\ApiException
	 */
	function makeRequest(Api\Request $request);
}