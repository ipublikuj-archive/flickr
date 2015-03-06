<?php
/**
 * Profile.php
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
use Nette\Utils;

use IPub;
use IPub\Flickr\Exceptions;

/**
 * Flickr's user profile
 *
 * @package		iPublikuj:Flickr!
 * @subpackage	common
 *
 * @author Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Profile extends Nette\Object
{
	/**
	 * @var Client
	 */
	private $flickr;

	/**
	 * @var string
	 */
	private $profileId;

	/**
	 * @var Utils\ArrayHash
	 */
	private $details;

	/**
	 * @param Client $flickr
	 * @param string $profileId
	 *
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function __construct(Client $flickr, $profileId = NULL)
	{
		$this->flickr = $flickr;

		if (is_numeric($profileId)) {
			throw new Exceptions\InvalidArgumentException("ProfileId must be a username of the account you're trying to read or NULL, which means actually logged in user.");
		}

		$this->profileId = $profileId;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		if ($this->profileId === NULL) {
			return $this->flickr->getUser();
		}

		return $this->profileId;
	}

	/**
	 * @param string $key
	 *
	 * @return Utils\ArrayHash|NULL
	 */
	public function getDetails($key = NULL)
	{
		if ($this->details === NULL) {
			try {

				if ($this->profileId !== NULL) {
					if (($user = $this->flickr->get('flickr.people.findByUsername', ['username' => $this->profileId]))
						&& ($user instanceof Utils\ArrayHash)
						&& ($result = $this->flickr->get('flickr.people.getInfo', ['user_id' => $user->user->id]))
						&& ($result instanceof Utils\ArrayHash)
					) {
						$this->details = $result->person;
					}

				} else if ($user = $this->flickr->getUser()) {
					if (($result = $this->flickr->get('flickr.people.getInfo', ['user_id' => $user])) && ($result instanceof Utils\ArrayHash)) {
						$this->details = $result->person;
					}

				} else {
					$this->details = new Utils\ArrayHash;
				}

			} catch (\Exception $e) {
				// todo: log?
			}
		}

		if ($key !== NULL) {
			return isset($this->details[$key]) ? $this->details[$key] : NULL;
		}

		return $this->details;
	}
}