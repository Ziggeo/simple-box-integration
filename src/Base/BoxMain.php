<?php
namespace Ziggeo\BoxContent\Base;
use Ziggeo\BoxContent\Content\BoxFile;
use Ziggeo\BoxContent\Content\BoxFileMetadata;
use Ziggeo\BoxContent\Content\FolderMetadata;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7\Request;
use Stevenmaguire\OAuth2\Client\Provider\Box;

/**
 * BoxMain
 */
class BoxMain
{
    /**
     * The BoxMain App
     *
     * @var BoxApp
     */
    protected $app;

    /**
     * OAuth2 Access Token
     *
     * @var string
     */
    protected $accessToken;

    /**
     * BoxMain Client
     *
     * @var BoxClient
     */
    protected $client;

    /**
     * OAuth2 Client
     *
     * @var Box
     */
    protected $oAuth2Client;

    /**
     * Random String Generator
     *
     * @var Security\RandomStringGeneratorInterface
     */
    protected $randomStringGenerator;

    /**
     * Persistent Data Store
     *
     * @var Store\PersistentDataStoreInterface
     */
    protected $persistentDataStore;

    /**
     * Uploading a file with the 'uploadFile' method, with the file's
     * size less than this value (~8 MB), the simple `upload` method will be
     * used, if the file size exceed this value (~8 MB), the `startUploadSession`,
     * `appendUploadSession` & `finishUploadSession` methods will be used
     * to upload the file in chunks.
     *
     * @const int
     */
    const AUTO_CHUNKED_UPLOAD_THRESHOLD = 8000000;

    /**
     * The Chunk Size the file will be
     * split into and uploaded (~4 MB)
     *
     * @const int
     */
    const DEFAULT_CHUNK_SIZE = 4000000;

    /**
     * Response header containing file metadata
     *
     * @const string
     */
    const METADATA_HEADER = 'box-api-result';

    /**
     * Create a new BoxMain instance
     *
     * @param BoxApp
     * @param array $config Configuration Array
     */
    public function __construct(BoxApp $app, array $config = [])
    {
        //Configuration
        $config = array_merge([
            'http_client_handler' => null,
            'random_string_generator' => null,
            'persistent_data_store' => null
        ], $config);

        //Set the app
        $this->app = $app;

        //Set the access token
        $this->setAccessToken($app->getAccessToken());

        //Make the HTTP Client
        $httpClient = BoxHttpClientFactory::make($config['http_client_handler']);

        //Make and Set the BoxClient
        $this->client = new BoxClient($httpClient);

    }

    /**
     * Get the Client
     *
     * @return BoxClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the Access Token.
     *
     * @return string Access Token
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Get the BoxMain App.
     *
     * @return BoxApp BoxMain App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Get the Random String Generator
     *
     * @return \Box\Security\RandomStringGeneratorInterface
     */
    public function getRandomStringGenerator()
    {
        return $this->randomStringGenerator;
    }

    /**
     * Get Persistent Data Store
     *
     * @return \Box\Store\PersistentDataStoreInterface
     */
    public function getPersistentDataStore()
    {
        return $this->persistentDataStore;
    }

    /**
     * Set the Access Token.
     *
     * @param string $accessToken Access Token
     *
     * @return BoxMain BoxMain Client
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Make Request to the API
     *
     * @param  string $method       HTTP Request Method
     * @param  string $endpoint     API Endpoint to send Request to
     * @param  string $endpointType Endpoint type ['api'|'content']
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return BoxResponse
     *
     * @throws Exceptions\BoxClientException
     */
    public function sendRequest($method, $endpoint, $endpointType = 'api', array $params = [], $accessToken = null)
    {
        //Access Token
        $accessToken = $this->getAccessToken() ? $this->getAccessToken() : $accessToken;

        //Make a BoxRequest object
        $request = new BoxRequest($method, $endpoint, $accessToken, $endpointType, $params);

        //Send Request through the BoxClient
        //Fetch and return the Response
        return $this->getClient()->sendRequest($request);
    }

    /**
     * Make a HTTP POST Request to the API endpoint type
     *
     * @param  string $endpoint     API Endpoint to send Request to
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return BoxResponse
     */
    public function postToAPI($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("POST", $endpoint, 'api', $params, $accessToken);
    }

    /**
     * Make a HTTP DELETE Request to the API endpoint type
     *
     * @param  string $endpoint     API Endpoint to send Request to
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return BoxResponse
     */
    public function deleteToAPI($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("DELETE", $endpoint, 'api', $params, $accessToken);
    }

    /**
     * Make a HTTP POST Request to the Content endpoint type
     *
     * @param  string $endpoint     Content Endpoint to send Request to
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return BoxResponse
     */
    public function postToContent($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("POST", $endpoint, 'upload', $params, $accessToken);
    }

    /**
     * Make a HTTP DELETE Request to the Content endpoint type
     *
     * @param  string $endpoint     Content Endpoint to send Request to
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return BoxResponse
     */
    public function deleteToContent($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("DELETE", $endpoint, 'upload', $params, $accessToken);
    }

    /**
     * Make Model from BoxResponse
     *
     * @param  BoxResponse $response
     *
     * @return \Ziggeo\BoxContent\Content\ModelInterface
     */
    public function makeModelFromResponse(BoxResponse $response)
    {
        //Get the Decoded Body
        $body = $response->getDecodedBody();

        //Make and Return the Model
        return ModelFactory::make($body);
    }

    /**
     * Make BoxFile Object
     *
     * @param  string|BoxFile $boxFile BoxFile object or Path to file
     * @param int $maxLength   Max Bytes to read from the file
     * @param int $offset      Seek to specified offset before reading
     *
     * @return \Box\BoxFile
     */
    public function makeBoxFile($boxFile, $maxLength = -1, $offset = -1)
    {
        //Uploading file by file path
        if (!$boxFile instanceof BoxFile) {
            //File is valid
            if (is_file($boxFile)) {
                //Create a BoxFile Object
                $boxFile = new BoxFile($boxFile, $maxLength, $offset);
            } else {
                //File invalid/doesn't exist
                throw new Exceptions\BoxClientException("File '{$boxFile}' is invalid.");
            }
        }

        $boxFile->setOffset($offset);
        $boxFile->setMaxLength($maxLength);

        //Return the BoxFile Object
        return $boxFile;
    }

    /**
     * Get the Metadata for a file or folder
     *
     * @param  string $path   Path of the file or folder
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-get_metadata
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata|\Ziggeo\BoxContent\Content\FolderMetadata
     */
    public function getMetadata($path, array $params = [])
    {
        //Root folder is unsupported
        if ($path === '/') {
            throw new Exceptions\BoxClientException("Metadata for the root folder is unsupported.");
        }

        //Set the path
        $params['path'] = $path;

        //Get File Metadata
        $response = $this->postToAPI('/files/get_metadata', $params);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Get the contents of a Folder
     *
     * @param  string $path   Path to the folder. Defaults to root.
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-list_folder
     *
     * @return \Ziggeo\BoxContent\Content\MetadataCollection
     */
    public function listFolder($path = null, array $params = [])
    {
        //Specify the root folder as an
        //empty string rather than as "/"
        if ($path === '/') {
            $path = "";
        }

        //Set the path
        $params['path'] = $path;

        //Get File Metadata
        $response = $this->postToAPI('/files/list_folder', $params);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Paginate through all files and retrieve updates to the folder,
     * using the cursor retrieved from listFolder or listFolderContinue
     *
     * @param  string $cursor The cursor returned by your
     * last call to listFolder or listFolderContinue
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-list_folder-continue
     *
     * @return \Ziggeo\BoxContent\Content\MetadataCollection
     */
    public function listFolderContinue($cursor)
    {
        $response = $this->postToAPI('/files/list_folder/continue', ['cursor' => $cursor]);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Get a cursor for the folder's state.
     *
     * @param  string $path   Path to the folder. Defaults to root.
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-list_folder-get_latest_cursor
     *
     * @return string The Cursor for the folder's state
     */
    public function listFolderLatestCursor($path, array $params = [])
    {
        //Specify the root folder as an
        //empty string rather than as "/"
        if ($path === '/') {
            $path = "";
        }

        //Set the path
        $params['path'] = $path;

        //Fetch the cursor
        $response = $this->postToAPI('/files/list_folder/get_latest_cursor', $params);

        //Retrieve the cursor
        $body = $response->getDecodedBody();
        $cursor = isset($body['cursor']) ? $body['cursor'] : false;

        //No cursor returned
        if (!$cursor) {
            throw new Exceptions\BoxClientException("Could not retrieve cursor. Something went wrong.");
        }

        //Return the cursor
        return $cursor;
    }

    /**
     * Get Revisions of a File
     *
     * @param  string $path   Path to the file
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-list_revisions
     *
     * @return \Ziggeo\BoxContent\Content\ModelCollection
     */
    public function listRevisions($path, array $params = [])
    {
        //Set the Path
        $params['path'] = $path;

        //Fetch the Revisions
        $response = $this->postToAPI('/files/list_revisions', $params);

        //The file metadata of the entries, returned by this
        //endpoint doesn't include a '.tag' attribute, which
        //is used by the ModelFactory to resolve the correct
        //model. But since we know that revisions returned
        //are file metadata objects, we can explicitly cast
        //them as \Ziggeo\BoxContent\Content\BoxFileMetadata manually.
        $body = $response->getDecodedBody();
        $entries = isset($body['entries']) ? $body['entries'] : [];
        $processedEntries = [];

        foreach ($entries as $entry) {
            $processedEntries[] = new BoxFileMetadata($entry);
        }

        return new ModelCollection($processedEntries);
    }

    /**
     * Search a folder for files/folders
     *
     * @param  string $path   Path to search
     * @param  string $query  Search Query
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-search
     *
     * @return \Ziggeo\BoxContent\Content\SearchResults
     */
    public function search($path, $query, array $params = [])
    {
        //Specify the root folder as an
        //empty string rather than as "/"
        if ($path === '/') {
            $path = "";
        }

        //Set the path and query
        $params['path'] = $path;
        $params['query'] = $query;

        //Fetch Search Results
        $response = $this->postToAPI('/files/search', $params);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Create a folder at the given path
     *
     * @param  string   $name       Path to create
     * @param  boolean  $autorename Auto Rename File
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-create_folder
     *
     * @return \Ziggeo\BoxContent\Content\FolderMetadata
     */
    public function createFolder($name, $autorename = false)
    {
        //Path cannot be null
        if (is_null($name)) {
            throw new Exceptions\BoxClientException("Name cannot be null.");
        }

        //Create Folder
        $response = $this->postToAPI('/folders', ['name' => $name, 'autorename' => $autorename, 'parent' => array("id" => 0)]);

        //Fetch the Metadata
        if ($response->getHttpStatusCode() === 409) {
            $body = $response->getDecodedBody();
            $body = $body["context_info"]["conflicts"][0];
        } else{
            $body = $response->getDecodedBody();
        }

        //Make and Return the Model

        return new FolderMetadata($body);
    }

    /**
     * Delete a file or folder at the given path
     *
     * @param  string $path Path to file/folder to delete
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-delete
     *
     * @return \Ziggeo\BoxContent\Content\DeletedMetadata|BoxFileMetadata|FolderMetadata
     */
    public function deleteFile($fileId)
    {
        //Path cannot be null
        if (is_null($fileId)) {
            throw new Exceptions\BoxClientException("Id cannot be null.");
        }

        //Delete
        $response = $this->deleteToAPI('/files/' . $fileId);

        return $response;
    }

    /**
     * Delete a file or folder at the given path
     *
     * @param  string $path Path to file/folder to delete
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-delete
     *
     * @return \Ziggeo\BoxContent\Content\DeletedMetadata|BoxFileMetadata|FolderMetadata
     */
    public function deleteFolder($folderId)
    {
        //Path cannot be null
        if (is_null($folderId)) {
            throw new Exceptions\BoxClientException("Id cannot be null.");
        }

        //Delete
        $response = $this->deleteToAPI('/folders/' . $folderId);

        return $response;
    }

    /**
     * Move a file or folder to a different location
     *
     * @param  string $fromPath Path to be moved
     * @param  string $toPath   Path to be moved to
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-move
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata|BoxFileMetadata|DeletedMetadata
     */
    public function move($fromPath, $toPath)
    {
        //From and To paths cannot be null
        if (is_null($fromPath) || is_null($toPath)) {
            throw new Exceptions\BoxClientException("From and To paths cannot be null.");
        }

        //Response
        $response = $this->postToAPI('/files/move', ['from_path' => $fromPath, 'to_path' => $toPath]);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Copy a file or folder to a different location
     *
     * @param  string $fromPath Path to be copied
     * @param  string $toPath   Path to be copied to
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-copy
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata|BoxFileMetadata|DeletedMetadata
     */
    public function copy($fromPath, $toPath)
    {
        //From and To paths cannot be null
        if (is_null($fromPath) || is_null($toPath)) {
            throw new Exceptions\BoxClientException("From and To paths cannot be null.");
        }

        //Response
        $response = $this->postToAPI('/files/copy', ['from_path' => $fromPath, 'to_path' => $toPath]);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Restore a file to the specific version
     *
     * @param  string $path Path to the file to restore
     * @param  string $rev  Revision to store for the file
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-restore
     *
     * @return \Ziggeo\BoxContent\Content\DeletedMetadata|BoxFileMetadata|FolderMetadata
     */
    public function restore($path, $rev)
    {
        //Path and Revision cannot be null
        if (is_null($path) || is_null($rev)) {
            throw new Exceptions\BoxClientException("Path and Revision cannot be null.");
        }

        //Response
        $response = $this->postToAPI('/files/restore', ['path' => $path, 'rev' => $rev]);

        //Fetch the Metadata
        $body = $response->getDecodedBody();

        //Make and Return the Model
        return new BoxFileMetadata($body);
    }

    /**
     * Get Copy Reference
     *
     * @param  string $path Path to the file or folder to get a copy reference to
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-copy_reference-get
     *
     * @return \Ziggeo\BoxContent\Content\CopyReference
     */
    public function getCopyReference($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new Exceptions\BoxClientException("Path cannot be null.");
        }

        //Get Copy Reference
        $response = $this->postToAPI('/files/copy_reference/get', ['path' => $path]);
        $body = $response->getDecodedBody();

        //Make and Return the Model
        return new CopyReference($body);
    }

    /**
     * Save Copy Reference
     *
     * @param  string $path          Path to the file or folder to get a copy reference to
     * @param  string $copyReference Copy reference returned by getCopyReference
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-copy_reference-save
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata|\Ziggeo\BoxContent\Content\FolderMetadata
     */
    public function saveCopyReference($path, $copyReference)
    {
        //Path and Copy Reference cannot be null
        if (is_null($path) || is_null($copyReference)) {
            throw new Exceptions\BoxClientException("Path and Copy Reference cannot be null.");
        }

        //Save Copy Reference
        $response = $this->postToAPI('/files/copy_reference/save', ['path' => $path, 'copy_reference' => $copyReference]);
        $body = $response->getDecodedBody();

        //Response doesn't have Metadata
        if (!isset($body['metadata']) || !is_array($body['metadata'])) {
            throw new Exceptions\BoxClientException("Invalid Response.");
        }

        //Make and return the Model
        return ModelFactory::make($body['metadata']);
    }

    /**
     * Get a temporary link to stream contents of a file
     *
     * @param  string $path Path to the file you want a temporary link to
     *
     * https://www.box.com/developers/documentation/http/documentation#files-get_temporary_link
     *
     * @return \Ziggeo\BoxContent\Content\TemporaryLink
     */
    public function getTemporaryLink($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new Exceptions\BoxClientException("Path cannot be null.");
        }

        //Get Temporary Link
        $response = $this->postToAPI('/files/get_temporary_link', ['path' => $path]);

        //Make and Return the Model
        return $this->makeModelFromResponse($response);
    }

    /**
     * Save a specified URL into a file in user's Box
     *
     * @param  string $path Path where the URL will be saved
     * @param  string $url  URL to be saved
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-save_url
     *
     * @return string Async Job ID
     */
    public function saveUrl($path, $url)
    {
        //Path and URL cannot be null
        if (is_null($path) || is_null($url)) {
            throw new Exceptions\BoxClientException("Path and URL cannot be null.");
        }

        //Save URL
        $response = $this->postToAPI('/files/save_url', ['path' => $path, 'url' => $url]);
        $body = $response->getDecodedBody();

        if (!isset($body['async_job_id'])) {
            throw new Exceptions\BoxClientException("Could not retrieve Async Job ID.");
        }

        //Return the Asunc Job ID
        return $body['async_job_id'];
    }

    /**
     * Save a specified URL into a file in user's Box
     *
     * @param  string $path Path where the URL will be saved
     * @param  string $url  URL to be saved
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-save_url-check_job_status
     *
     * @return string|BoxFileMetadata Status (failed|in_progress) or BoxFileMetadata (if complete)
     */
    public function checkJobStatus($asyncJobId)
    {
        //Async Job ID cannot be null
        if (is_null($asyncJobId)) {
            throw new Exceptions\BoxClientException("Async Job ID cannot be null.");
        }

        //Get Job Status
        $response = $this->postToAPI('/files/save_url/check_job_status', ['async_job_id' => $asyncJobId]);
        $body = $response->getDecodedBody();

        //Status
        $status = isset($body['.tag']) ? $body['.tag'] : '';

        //If status is complete
        if ($status === 'complete') {
            return new BoxFileMetadata($body);
        }

        //Return the status
        return $status;
    }

    /**
     * Upload a File to Box
     *
     * @param  string|BoxFile $boxFile BoxFile object or Path to file
     * @param  string             $folder        Path to upload the file to
     * @param  array              $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata
     */
    public function upload($boxFile, $attributes, array $params = [])
    {
        //Make BoxMain File
        $boxFile = $this->makeBoxFile($boxFile);

        //Simple file upload
        return $this->simpleUpload($boxFile, $attributes, $params);
    }

    /**
     * Upload a File to BoxMain in a single request
     *
     * @param  string|BoxFile $boxFile BoxFile object or Path to file
     * @param  array             $path        Path to upload the file to
     * @param  array              $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload
     *
     * @return \Ziggeo\BoxContent\Content\BoxFileMetadata
     */
    public function simpleUpload($boxFile, $attributes, array $params = [])
    {
        //Make BoxMain File
        $boxFile = $this->makeBoxFile($boxFile);

        //Set the attributes and file
        $params['attributes'] = $attributes;
        $params['file'] = $boxFile;

        //Upload File
        $file = $this->postToContent('/files/content', $params);
        $body = $file->getDecodedBody();
        if ($body["type"] == "error")
            throw new Exceptions\BoxClientException($body["message"] . " - ID: " . $body["context_info"]["conflicts"]["id"], $body["status"]);
        //Make and Return the Model
        $fileData = array();
        if (!empty($body["total_count"]) && $body["total_count"])
            $fileData = $body["entries"][0];
        return new BoxFileMetadata($fileData);
    }

    /**
     * Download a File
     *
     * @param  string $path   Path to the file you want to download
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-download
     *
     * @return \Ziggeo\BoxContent\Content\File
     */
    public function download($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new Exceptions\BoxClientException("Path cannot be null.");
        }

        //Download File
        $response = $this->postToContent('/files/download', ['path' => $path]);

        //Get file metadata from response headers
        $metadata = $this->getMetadataFromResponseHeaders($response);

        //File Contents
        $contents = $response->getBody();

        //Make and return a File model
        return new File($metadata, $contents);
    }

    /**
     * Get metadata from response headers
     *
     * @param  BoxResponse $response
     *
     * @return array
     */
    protected function getMetadataFromResponseHeaders(BoxResponse $response)
    {
        //Response Headers
        $headers = $response->getHeaders();

        //Empty metadata for when
        //metadata isn't returned
        $metadata = [];

        //If metadata is avaialble
        if (isset($headers[static::METADATA_HEADER])) {
            //File Metadata
            $data = $headers[static::METADATA_HEADER];

            //The metadata is present in the first index
            //of the metadata response header array
            if (is_array($data) && isset($data[0])) {
                $data = $data[0];
            }

            //Since the metadata is returned as a json string
            //it needs to be decoded into an associative array
            $metadata = json_decode((string) $data, true);
        }

        //Return the metadata
        return $metadata;
    }

}
