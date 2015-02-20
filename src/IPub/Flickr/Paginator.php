<?php
/**
 * Paginator.php
 *
 * @copyright	More in license.md
 * @license		http://www.ipublikuj.eu
 * @author		Adam Kadlec http://www.ipublikuj.eu
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 * @since		5.0
 *
 * @date		20.02.15
 */

namespace IPub\Flickr;

use Nette;
use Tracy\Debugger;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Paginator extends Nette\Object implements \Iterator
{
	const PER_PAGE_MAX = 100;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Api\CurlClient
	 */
	private $httpClient;

	/**
	 * @var int
	 */
	private $firstPage;

	/**
	 * @var int
	 */
	private $perPage;

	/**
	 * @var int|NULL
	 */
	private $maxResults;

	/**
	 * @var array
	 */
	private $resources = [];

	/**
	 * @var Api\Response[]
	 */
	private $responses = [];

	/**
	 * @var int
	 */
	private $itemCursor;

	/**
	 * @var int
	 */
	private $pageCursor;

	/**
	 * @param Client $client
	 * @param Api\Response $response
	 */
	public function __construct(Client $client, Api\Response $response)
	{
		$this->client = $client;

		$this->httpClient = $client->getHttpClient();
		$resource = $response->toArray();

		$params = $response->request->getParameters();
		$this->firstPage = isset($params['page']) ? (int) max($params['page'], 1) : 1;
		$this->perPage = isset($params['per_page']) ? (int) $params['per_page'] : count($resource);

		$this->responses[$this->firstPage] = $response;
		$this->resources[$this->firstPage] = $resource;
	}

	/**
	 * If you setup maximum number of results, the pagination will stop after fetching the desired number.
	 * If you have per_page=50 and wan't to fetch 200 results, it will make 4 requests in total.
	 *
	 * @param int $maxResults
	 *
	 * @return $this
	 */
	public function limitResults($maxResults)
	{
		$this->maxResults = (int)$maxResults;

		return $this;
	}

	public function rewind()
	{
		$this->itemCursor = 0;
		$this->pageCursor = $this->firstPage;
	}

	public function valid()
	{
		return isset($this->resources[$this->pageCursor][$this->itemCursor])
			&& ! $this->loadedMaxResults();
	}

	/**
	 * @return bool
	 */
	public function loadedMaxResults()
	{
		if ($this->maxResults === NULL) {
			return FALSE;
		}

		return $this->maxResults <= ($this->itemCursor + ($this->pageCursor - $this->firstPage) * $this->perPage);
	}

	public function current()
	{
		if (!$this->valid()) {
			return NULL;
		}

		return Nette\Utils\ArrayHash::from($this->resources[$this->pageCursor][$this->itemCursor]);
	}

	public function next()
	{
		$this->itemCursor++;

		// if cursor points at result of next page, try to load it
		if ($this->itemCursor < $this->perPage || $this->itemCursor % $this->perPage !== 0) {
			return;
		}

		if (isset($this->resources[$this->pageCursor + 1])) { // already loaded
			$this->itemCursor = 0;
			$this->pageCursor++;

			return;
		}

		if ($this->loadedMaxResults()) {
			return;
		}

		try {
			$prevRequest = $this->responses[$this->pageCursor]->getRequest();

			$params = $this->responses[$this->pageCursor]->request->getParameters();
			$params['page'] = isset($params['page']) ? (int) max($params['page'], 1) + 1 : 1;
			if (isset($params['oauth_signature'])) {
				unset($params['oauth_signature']);
			}
			$params['oauth_signature'] = $this->client->getSignature(
				$this->responses[$this->pageCursor]->request->getMethod(), $this->client->getConfig()->createUrl('api', 'rest'), $params
			);

			$response = $this->httpClient->makeRequest(
				$prevRequest->copyWithUrl($this->client->getConfig()->createUrl('api', 'rest', $params))
			);

			$this->itemCursor = 0;
			$this->pageCursor++;
			$this->responses[$this->pageCursor] = $response;
			$this->resources[$this->pageCursor] = $response->toArray();

		} catch (\Exception $e) {
			$this->itemCursor--; // revert back so the user can continue if needed
		}
	}

	public function key()
	{
		return $this->itemCursor + ($this->pageCursor - 1) * $this->perPage;
	}
}