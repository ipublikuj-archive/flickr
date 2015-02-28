# Photos uploading and replacing

For [uploading](https://www.flickr.com/services/api/upload.api.html) and [replacing](https://www.flickr.com/services/api/replace.api.html) photos is used different api, than for other edit/update calls.

This extension brings you two methods which could handle all this stuff.

## Uploading photos

For successful upload user have to be authenticated and your app must have *write* permission. Upload is simple done with this call:

```php
class YourAppSomePresenter extends BasePresenter
{
	/**
	 * @var \IPub\Flickr\Client
	 */
	protected $flickr;

	public function actionUpload()
	{
		try {
			$photoId = $this->flickr->uploadPhoto('full/absolute/path/to/your/image.jpg', [
				'title' => 'Here could be some image title',
				'description' => 'And here you can place some description'
			]);

		} catch (\IPub\Flickr\ApiException $ex) {
			// something went wrong
		}
	}
}
```

If upload is successful an photo ID is returned, in other case an exception will be thrown.

All additional params are optional, so you can omit the second argument of the *uploadPhoto* method, but if you want, you can define this parameters:

* **title** - The title of the photo
* **description** - A description of the photo. May contain some limited HTML
* **tags** - A space-seperated list of tags to apply to the photo
* **is_public**, **is_friend**, **is_family** - Set to 0 for no, 1 for yes. Specifies who can view the photo
* **safety_level** - Set to 1 for Safe, 2 for Moderate, or 3 for Restricted
* **content_type** - Set to 1 for Photo, 2 for Screenshot, or 3 for Other
* **hidden** - Set to 1 to keep the photo in global search results, 2 to hide from public searches

## Replacing photos

Already uploaded photos can be replaced with others. All what you have to do is provide path to your new image and ID of a existing image.

```php
class YourAppSomePresenter extends BasePresenter
{
	/**
	 * @var \IPub\Flickr\Client
	 */
	protected $flickr;

	public function actionUpload()
	{
		try {
			$photoId = $this->flickr->replacePhoto('full/absolute/path/to/your/image.jpg', 123456789);

		} catch (\IPub\Flickr\ApiException $ex) {
			// something went wrong
		}
	}
}
```

If upload is successful an photo ID is returned, and original photo in the Flickr is replaced with new one. In other case an exception will be thrown.
