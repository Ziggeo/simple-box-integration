# simple-box-integration
A Box Content Api Integration to upload files. It does not take taker of the Auth part. For that,
please use [this](https://github.com/stevenmaguire/oauth2-box).

It contains the base to use simple operations with files and folders:
* Files: upload and delete.
* Folders: create and delete.

Next on we will add functionality to manage the different types of entities that the Box Content API uses.

## Installation

To install, use composer:

```
composer require ziggeo/simple-box-integration
```

## Usage

The wrapper can be used with the magic methods for files or folders:

```php
use Ziggeo\BoxContent\Base\BoxApp;
use Ziggeo\BoxContent\Base\BoxMain;
use Ziggeo\BoxContent\Content\BoxFile;
use Ziggeo\BoxContent\Base\Exceptions\BoxClientException;


$boxApp = new BoxApp("clientID", "clientSecret", "accessToken");

$boxMain = new BoxMain($boxApp);

$boxFile = new BoxFile("/path/to/file.txt");

$folder = $boxMain->createFolder("FolderName");

try {
$file = $boxMain->upload($boxFile, array("parent" => array("id" => $folder->getId()), "name" => "file_name_in_box.txt"));
$resp = $boxMain->deleteFile($file->getId());
} catch (BoxClientException $exception) {
    echo $exception->getMessage();
}


```
or using the sendRequest method with the correct params:

```php
$boxApp = new BoxApp("clientID", "clientSecret", "accessToken");

$boxMain = new BoxMain($boxApp);
$resp = $boxMain->sendRequest("/users/me", "api", array(), "accessToken");
```
where:
- The first param is the proper endpoint. Check the list [here](https://docs.box.com/reference).
- The second is the endpoint type: api or upload. This depends on the endpoint. (Optional)
- The third is an array with options. (Optional)
- The last is the accessToken provided by Box. (Optional)


## Main contributors

- [Pablo Iglesias](https://github.com/iglesiaspablo)
