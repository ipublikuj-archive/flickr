<?php
/**
 * CurlClient.php
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

use Tracy\Debugger;

use Kdyby\CurlCaBundle;

use IPub;
use IPub\Flickr;
use IPub\Flickr\Exceptions;

if (!defined('CURLE_SSL_CACERT_BADFILE')) {
	define('CURLE_SSL_CACERT_BADFILE', 77);
}

/**
 * @package		iPublikuj:Flickr!
 * @subpackage	Api
 *
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onRequest(Request $request, $options)
 * @method onError(Exceptions\IException $ex, Response $response)
 * @method onSuccess(Response $response)
 */
class CurlClient extends Nette\Object implements Flickr\HttpClient
{
	/**
	 * Default options for curl
	 *
	 * @var array
	 */
	public static $defaultCurlOptions = [
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT => 20,
		CURLOPT_USERAGENT => 'ipub-flickr-php',
		CURLOPT_HTTPHEADER => [
			'Accept' => 'application/json',
		],
		CURLINFO_HEADER_OUT => TRUE,
		CURLOPT_HEADER => TRUE,
		CURLOPT_AUTOREFERER => TRUE,
	];

	/**
	 * Options for curl
	 *
	 * @var array
	 */
	public $curlOptions = [];

	/**
	 * @var array of function(Request $request, $options)
	 */
	public $onRequest = [];

	/**
	 * @var array of function(Exceptions\IException $ex, Response $response)
	 */
	public $onError = [];

	/**
	 * @var array of function(Response $response)
	 */
	public $onSuccess = [];

	/**
	 * @var array
	 */
	private $memoryCache = [];

	public function __construct()
	{
		$this->curlOptions = self::$defaultCurlOptions;
	}

	/**
	 * Makes an HTTP request. This method can be overridden by subclasses if
	 * developers want to do fancier things or use something other than curl to
	 * make the request.
	 *
	 * @param Request $request
	 *
	 * @return Response
	 *
	 * @throws Exceptions\ApiException
	 */
	public function makeRequest(Request $request)
	{
		if (isset($this->memoryCache[$cacheKey = md5(serialize($request))])) {
			return $this->memoryCache[$cacheKey];
		}

		$ch = $this->buildCurlResource($request);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$result = curl_exec($ch);
		// provide certificate if needed
		if (curl_errno($ch) == CURLE_SSL_CACERT || curl_errno($ch) === CURLE_SSL_CACERT_BADFILE) {
			Debugger::log('Invalid or no certificate authority found, using bundled information', 'flickr');
			$this->curlOptions[CURLOPT_CAINFO] = CurlCaBundle\CertificateHelper::getCaInfoFile();
			curl_setopt($ch, CURLOPT_CAINFO, CurlCaBundle\CertificateHelper::getCaInfoFile());
			$result = curl_exec($ch);
		}

		// With dual stacked DNS responses, it's possible for a server to
		// have IPv6 enabled but not have IPv6 connectivity.  If this is
		// the case, curl will try IPv4 first and if that fails, then it will
		// fall back to IPv6 and the error EHOSTUNREACH is returned by the operating system.
		if ($result === FALSE && empty($opts[CURLOPT_IPRESOLVE])) {
			$matches = [];
			if (preg_match('/Failed to connect to ([^:].*): Network is unreachable/', curl_error($ch), $matches)) {
				if (strlen(@inet_pton($matches[1])) === 16) {
					Debugger::log('Invalid IPv6 configuration on server, Please disable or get native IPv6 on your server.', 'flickr');
					$this->curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
					curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
					$result = curl_exec($ch);
				}
			}
		}

		$info = curl_getinfo($ch);
		$info['http_code'] = (int) $info['http_code'];
		if (isset($info['request_header'])) {
			list($info['request_header']) = self::parseHeaders($info['request_header']);
		}
		$info['method'] = isset($post['method']) ? $post['method']: 'GET';
		$info['headers'] = self::parseHeaders(substr($result, 0, $info['header_size']));
		$info['error'] = $result === FALSE ? ['message' => curl_error($ch), 'code' => curl_errno($ch)] : [];

		if (isset($info['request_header'])) {
			$request->setHeaders($info['request_header']);
		}

		$response = new Response($request, substr($result, $info['header_size']), $info['http_code'], end($info['headers']), $info);
		Debugger::barDump($response);
		if (!$response->isOk()) {
			$e = $response->toException();
			curl_close($ch);
			$this->onError($e, $response);
			throw $e;
		}

		$this->onSuccess($response);
		curl_close($ch);

		return $this->memoryCache[$cacheKey] = $response;
	}

	/**
	 * @param Request $request
	 *
	 * @return resource
	 */
	protected function buildCurlResource(Request $request)
	{
		$ch = curl_init((string) $request->getUrl());

		$options = $this->curlOptions;
		$options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();

		// configuring a POST request
		if ($request->getPost()) {
			$options[CURLOPT_POSTFIELDS] = $request->getPost();
		}

		if ($request->isHead()) {
			$options[CURLOPT_NOBODY] = TRUE;

		} else if ($request->isGet()) {
			$options[CURLOPT_HTTPGET] = TRUE;
		}

		// disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
		// for 2 seconds if the server does not support this header.
		$options[CURLOPT_HTTPHEADER]['Expect'] = '';
		$tmp = [];
		foreach ($request->getHeaders() + $options[CURLOPT_HTTPHEADER] as $name => $value) {
			$tmp[] = trim("$name: $value");
		}
		$options[CURLOPT_HTTPHEADER] = $tmp;

		// execute request
		curl_setopt_array($ch, $options);
		$this->onRequest($request, $options);

		return $ch;
	}

	private static function parseHeaders($raw)
	{
		$headers = [];

		// Split the string on every "double" new line.
		foreach (explode("\r\n\r\n", $raw) as $index => $block) {
			// Loop of response headers. The "count() -1" is to
			//avoid an empty row for the extra line break before the body of the response.
			foreach (Utils\Strings::split(trim($block), '~[\r\n]+~') as $i => $line) {
				if (preg_match('~^([a-z-]+\\:)(.*)$~is', $line)) {
					list($key, $val) = explode(': ', $line, 2);
					$headers[$index][$key] = $val;

				} else if (!empty($line)) {
					$headers[$index][] = $line;
				}
			}
		}

		return $headers;
	}
}