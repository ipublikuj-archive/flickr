<?php
/**
 * Response.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	Api
 * @since		5.0
 *
 * @date		19.02.15
 */

namespace IPub\Flickr\Api;

use Nette;
use Nette\Utils;

use IPub;
use IPub\Flickr;
use IPub\Flickr\Exceptions;

/**
 * @package		iPublikuj:Flickr!
 * @subpackage	Api
 *
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property-read Request $request
 * @property-read string $content
 * @property-read int $httpCode
 * @property-read array $headers
 * @property-read array $debugInfo
 */
class Response extends Nette\Object
{
	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var string|array
	 */
	private $content;

	/**
	 * @var string|array
	 */
	private $arrayContent;

	/**
	 * @var int
	 */
	private $httpCode;

	/**
	 * @var array
	 */
	private $headers;

	/**
	 * @var array
	 */
	private $info;

	/**
	 * @param Request $request
	 * @param string $content
	 * @param string $httpCode
	 * @param array $headers
	 * @param array $info
	 */
	public function __construct(Request $request, $content, $httpCode, $headers = [], $info = [])
	{
		$this->request = $request;
		$this->content = $content;
		$this->httpCode = (int) $httpCode;
		$this->headers = $headers;
		$this->info = $info;
	}

	/**
	 * @return Request
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * @return array|string
	 */
	public function getContent()
	{
		return $this->content;
	}

	/**
	 * @return bool
	 */
	public function isJson()
	{
		return isset($this->headers['Content-Type'])
			&& preg_match('~^application/json*~is', $this->headers['Content-Type']);
	}

	/**
	 * @return array
	 *
	 * @throws Exceptions\ApiException
	 */
	public function toArray()
	{
		if ($this->arrayContent !== NULL) {
			return $this->arrayContent;
		}

		if (!$this->isJson()) {
			return NULL;
		}

		try {
			return $this->arrayContent = Utils\Json::decode($this->content, Utils\Json::FORCE_ARRAY);

		} catch (Utils\JsonException $jsonException) {
			$e = new Exceptions\ApiException($jsonException->getMessage() . ($this->content ? "\n\n" . $this->content : ''), $this->httpCode, $jsonException);
			$e->bindResponse($this->request, $this);
			throw $e;
		}
	}

	/**
	 * @return bool
	 */
	public function isPaginated()
	{
		return $this->request->isPaginated();
	}

	/**
	 * @return int
	 */
	public function getHttpCode()
	{
		return $this->httpCode;
	}

	/**
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->headers;
	}

	/**
	 * @return bool
	 */
	public function isOk()
	{
		return $this->httpCode >= 200 && $this->httpCode < 300;
	}

	/**
	 * @return Exceptions\ApiException|static
	 */
	public function toException()
	{
		$error = isset($this->info['error']) ? $this->info['error'] : NULL;
		$ex = new Exceptions\RequestFailedException(
			$error ? $error['message'] : '',
			$error ? (int) $error['code'] : 0
		);

		if ($this->content && $this->isJson()) {
			$response = $this->toArray();

			if (isset($response['message'])) {
				$ex = new Exceptions\ApiException($response['message'], $response['code'], $ex);
			}
		}

		return $ex->bindResponse($this->request, $this);
	}

	/**
	 * @internal
	 *
	 * @return array
	 */
	public function getDebugInfo()
	{
		return $this->info;
	}
}