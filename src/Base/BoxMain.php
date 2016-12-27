<?php
namespace Pablo2309\BoxContent\Base;
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
     * @var \Box\Security\RandomStringGeneratorInterface
     */
    protected $randomStringGenerator;

    /**
     * Persistent Data Store
     *
     * @var \Box\Store\PersistentDataStoreInterface
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

        //Make and Set the Random String Generator
        $this->randomStringGenerator = RandomStringGeneratorFactory::makeRandomStringGenerator($config['random_string_generator']);

        //Make and Set the Persistent Data Store
        $this->persistentDataStore = PersistentDataStoreFactory::makePersistentDataStore($config['persistent_data_store']);
    }

    /**
     * Get the Client
     *
     * @return \Box\BoxClient
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
     * @return \Box\BoxApp BoxMain App
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * Get OAuth2Client
     *
     * @return \Box\Authentication\OAuth2Client
     */
    public function getOAuth2Client()
    {
        if (!$this->oAuth2Client instanceof OAuth2Client) {
            return new OAuth2Client(
                $this->getApp(),
                $this->getClient(),
                $this->getRandomStringGenerator()
            );
        }

        return $this->oAuth2Client;
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
     * Get BoxMain Auth Helper
     *
     * @return \Box\Authentication\BoxAuthHelper
     */
    public function getAuthHelper()
    {
        return new BoxAuthHelper(
            $this->getOAuth2Client(),
            $this->getRandomStringGenerator(),
            $this->getPersistentDataStore()
        );
    }

    /**
     * Set the Access Token.
     *
     * @param string $accessToken Access Token
     *
     * @return \Box\BoxMain BoxMain Client
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
     * @return \Box\BoxResponse
     *
     * @throws \Box\Exceptions\BoxClientException
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
     * @return \Box\BoxResponse
     */
    public function postToAPI($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("POST", $endpoint, 'api', $params, $accessToken);
    }

    /**
     * Make a HTTP POST Request to the Content endpoint type
     *
     * @param  string $endpoint     Content Endpoint to send Request to
     * @param  array  $params       Request Query Params
     * @param  string $accessToken Access Token to send with the Request
     *
     * @return \Box\BoxResponse
     */
    public function postToContent($endpoint, array $params = [], $accessToken = null)
    {
        return $this->sendRequest("POST", $endpoint, 'content', $params, $accessToken);
    }

    /**
     * Make Model from BoxResponse
     *
     * @param  BoxResponse $response
     *
     * @return \Box\Models\ModelInterface
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
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param int $maxLength   Max Bytes to read from the file
     * @param int $offset      Seek to specified offset before reading
     *
     * @return \Box\BoxFile
     */
    public function makeBoxFile($dropboxFile, $maxLength = -1, $offset = -1)
    {
        //Uploading file by file path
        if (!$dropboxFile instanceof BoxFile) {
            //File is valid
            if (is_file($dropboxFile)) {
                //Create a BoxFile Object
                $dropboxFile = new BoxFile($dropboxFile, $maxLength, $offset);
            } else {
                //File invalid/doesn't exist
                throw new BoxClientException("File '{$dropboxFile}' is invalid.");
            }
        }

        $dropboxFile->setOffset($offset);
        $dropboxFile->setMaxLength($maxLength);

        //Return the BoxFile Object
        return $dropboxFile;
    }

    /**
     * Get the Metadata for a file or folder
     *
     * @param  string $path   Path of the file or folder
     * @param  array  $params Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-get_metadata
     *
     * @return \Box\Models\FileMetadata|\Box\Models\FolderMetadata
     */
    public function getMetadata($path, array $params = [])
    {
        //Root folder is unsupported
        if ($path === '/') {
            throw new BoxClientException("Metadata for the root folder is unsupported.");
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
     * @return \Box\Models\MetadataCollection
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
     * @return \Box\Models\MetadataCollection
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
            throw new BoxClientException("Could not retrieve cursor. Something went wrong.");
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
     * @return \Box\Models\ModelCollection
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
        //them as \Box\Models\FileMetadata manually.
        $body = $response->getDecodedBody();
        $entries = isset($body['entries']) ? $body['entries'] : [];
        $processedEntries = [];

        foreach ($entries as $entry) {
            $processedEntries[] = new FileMetadata($entry);
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
     * @return \Box\Models\SearchResults
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
     * @param  string   $path       Path to create
     * @param  boolean  $autorename Auto Rename File
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-create_folder
     *
     * @return \Box\Models\FolderMetadata
     */
    public function createFolder($path, $autorename = false)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
        }

        //Create Folder
        $response = $this->postToAPI('/files/create_folder', ['path' => $path, 'autorename' => $autorename]);

        //Fetch the Metadata
        $body = $response->getDecodedBody();

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
     * @return \Box\Models\DeletedMetadata|FileMetadata|FolderMetadata
     */
    public function delete($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
        }

        //Delete
        $response = $this->postToAPI('/files/delete', ['path' => $path]);

        return $this->makeModelFromResponse($response);
    }

    /**
     * Move a file or folder to a different location
     *
     * @param  string $fromPath Path to be moved
     * @param  string $toPath   Path to be moved to
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-move
     *
     * @return \Box\Models\FileMetadata|FileMetadata|DeletedMetadata
     */
    public function move($fromPath, $toPath)
    {
        //From and To paths cannot be null
        if (is_null($fromPath) || is_null($toPath)) {
            throw new BoxClientException("From and To paths cannot be null.");
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
     * @return \Box\Models\FileMetadata|FileMetadata|DeletedMetadata
     */
    public function copy($fromPath, $toPath)
    {
        //From and To paths cannot be null
        if (is_null($fromPath) || is_null($toPath)) {
            throw new BoxClientException("From and To paths cannot be null.");
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
     * @return \Box\Models\DeletedMetadata|FileMetadata|FolderMetadata
     */
    public function restore($path, $rev)
    {
        //Path and Revision cannot be null
        if (is_null($path) || is_null($rev)) {
            throw new BoxClientException("Path and Revision cannot be null.");
        }

        //Response
        $response = $this->postToAPI('/files/restore', ['path' => $path, 'rev' => $rev]);

        //Fetch the Metadata
        $body = $response->getDecodedBody();

        //Make and Return the Model
        return new FileMetadata($body);
    }

    /**
     * Get Copy Reference
     *
     * @param  string $path Path to the file or folder to get a copy reference to
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-copy_reference-get
     *
     * @return \Box\Models\CopyReference
     */
    public function getCopyReference($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
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
     * @return \Box\Models\FileMetadata|\Box\Models\FolderMetadata
     */
    public function saveCopyReference($path, $copyReference)
    {
        //Path and Copy Reference cannot be null
        if (is_null($path) || is_null($copyReference)) {
            throw new BoxClientException("Path and Copy Reference cannot be null.");
        }

        //Save Copy Reference
        $response = $this->postToAPI('/files/copy_reference/save', ['path' => $path, 'copy_reference' => $copyReference]);
        $body = $response->getDecodedBody();

        //Response doesn't have Metadata
        if (!isset($body['metadata']) || !is_array($body['metadata'])) {
            throw new BoxClientException("Invalid Response.");
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
     * @return \Box\Models\TemporaryLink
     */
    public function getTemporaryLink($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
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
            throw new BoxClientException("Path and URL cannot be null.");
        }

        //Save URL
        $response = $this->postToAPI('/files/save_url', ['path' => $path, 'url' => $url]);
        $body = $response->getDecodedBody();

        if (!isset($body['async_job_id'])) {
            throw new BoxClientException("Could not retrieve Async Job ID.");
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
     * @return string|FileMetadata Status (failed|in_progress) or FileMetadata (if complete)
     */
    public function checkJobStatus($asyncJobId)
    {
        //Async Job ID cannot be null
        if (is_null($asyncJobId)) {
            throw new BoxClientException("Async Job ID cannot be null.");
        }

        //Get Job Status
        $response = $this->postToAPI('/files/save_url/check_job_status', ['async_job_id' => $asyncJobId]);
        $body = $response->getDecodedBody();

        //Status
        $status = isset($body['.tag']) ? $body['.tag'] : '';

        //If status is complete
        if ($status === 'complete') {
            return new FileMetadata($body);
        }

        //Return the status
        return $status;
    }

    /**
     * Upload a File to Box
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  string             $path        Path to upload the file to
     * @param  array              $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload
     *
     * @return \Box\Models\FileMetadata
     */
    public function upload($dropboxFile, $path, array $params = [])
    {
        //Make BoxMain File
        $dropboxFile = $this->makeBoxFile($dropboxFile);

        //If the file is larger than the Chunked Upload Threshold
        if ($dropboxFile->getSize() > static::AUTO_CHUNKED_UPLOAD_THRESHOLD) {
            //Upload the file in sessions/chunks
            return $this->uploadChunked($dropboxFile, $path, null, null, $params);
        }

        //Simple file upload
        return $this->simpleUpload($dropboxFile, $path, $params);
    }

    /**
     * Upload a File to BoxMain in a single request
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  string             $path        Path to upload the file to
     * @param  array              $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload
     *
     * @return \Box\Models\FileMetadata
     */
    public function simpleUpload($dropboxFile, $path, array $params = [])
    {
        //Make BoxMain File
        $dropboxFile = $this->makeBoxFile($dropboxFile);

        //Set the path and file
        $params['path'] = $path;
        $params['file'] = $dropboxFile;

        //Upload File
        $file = $this->postToContent('/files/upload', $params);
        $body = $file->getDecodedBody();

        //Make and Return the Model
        return new FileMetadata($body);
    }

    /**
     * Start an Upload Session
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  int                $chunkSize   Size of file chunk to upload
     * @param  boolean            $close       Closes the session for "appendUploadSession"
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-start
     *
     * @return string Unique identifier for the upload session
     */
    public function startUploadSession($dropboxFile, $chunkSize = -1, $close = false)
    {
        //Make BoxMain File with the given chunk size
        $dropboxFile = $this->makeBoxFile($dropboxFile, $chunkSize);

        //Set the close param
        $params['close'] = $close ? true : false;

        //Set the file param
        $params['file'] = $dropboxFile;

        //Upload File
        $file = $this->postToContent('/files/upload_session/start', $params);
        $body = $file->getDecodedBody();

        //Cannot retrieve Session ID
        if (!isset($body['session_id'])) {
            throw new BoxClientException("Could not retrieve Session ID.");
        }

        //Return the Session ID
        return $body['session_id'];
    }

    /**
     * Finish an upload session and save the uploaded data to the given file path
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  string $sessionId   Session ID returned by `startUploadSession`
     * @param  int    $offset      The amount of data that has been uploaded so far
     * @param  int    $remaining   The amount of data that is remaining
     * @param  string $path        Path to save the file to, on Box
     * @param  array  $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-finish
     *
     * @return \Box\Models\FileMetadata
     */
    public function finishUploadSession($dropboxFile, $sessionId, $offset, $remaining, $path, array $params = [])
    {
        //Make BoxMain File
        $dropboxFile = $this->makeBoxFile($dropboxFile, $remaining, $offset);

        //Session ID, offset, remaining and path cannot be null
        if (is_null($sessionId) || is_null($path) || is_null($offset) || is_null($remaining)) {
            throw new BoxClientException("Session ID, offset, remaining and path cannot be null");
        }

        $queryParams = [];

        //Set the File
        $queryParams['file'] = $dropboxFile;

        //Set the Cursor: Session ID and Offset
        $queryParams['cursor'] = ['session_id' => $sessionId, 'offset' => $offset];

        //Set the path
        $params['path'] = $path;
        //Set the Commit
        $queryParams['commit'] = $params;

        //Upload File
        $file = $this->postToContent('/files/upload_session/finish', $queryParams);
        $body = $file->getDecodedBody();

        //Make and Return the Model
        return new FileMetadata($body);
    }

    /**
     * Append more data to an Upload Session
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  string             $sessionId   Session ID returned by `startUploadSession`
     * @param  int                $offset      The amount of data that has been uploaded so far
     * @param  int                $chunkSize   The amount of data to upload
     * @param  boolean            $close       Closes the session for futher "appendUploadSession" calls
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-append_v2
     *
     * @return string Unique identifier for the upload session
     */
    public function appendUploadSession($dropboxFile, $sessionId, $offset, $chunkSize, $close = false)
    {
        //Make BoxMain File
        $dropboxFile = $this->makeBoxFile($dropboxFile, $chunkSize, $offset);

        //Session ID, offset, chunkSize and path cannot be null
        if (is_null($sessionId) || is_null($offset) || is_null($chunkSize)) {
            throw new BoxClientException("Session ID, offset and chunk size cannot be null");
        }

        $params = [];

        //Set the File
        $params['file'] = $dropboxFile;

        //Set the Cursor: Session ID and Offset
        $params['cursor'] = ['session_id' => $sessionId, 'offset' => $offset];

        //Set the close param
        $params['close'] = $close ? true : false;

        //Since this endpoint doesn't have
        //any return values, we'll disable the
        //response validation for this request.
        $params['validateResponse'] = false;

        //Upload File
        $file = $this->postToContent('/files/upload_session/append_v2', $params);

        //Make and Return the Model
        return $sessionId;
    }

    /**
     * Upload file in sessions/chunks
     *
     * @param  string|BoxFile $dropboxFile BoxFile object or Path to file
     * @param  string             $path        Path to save the file to, on Box
     * @param  int                $fileSize    The size of the file
     * @param  int                $chunkSize   The amount of data to upload in each chunk
     * @param  array              $params      Additional Params
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-start
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-finish
     * @link https://www.box.com/developers/documentation/http/documentation#files-upload_session-append_v2
     *
     * @return string Unique identifier for the upload session
     */
    public function uploadChunked($dropboxFile, $path, $fileSize = null, $chunkSize = null, array $params = array())
    {
        //Make BoxMain File
        $dropboxFile = $this->makeBoxFile($dropboxFile);

        //No file size specified explicitly
        if (is_null($fileSize)) {
            $fileSize = $dropboxFile->getSize();
        }

        //No chunk size specified, use default size
        if (is_null($chunkSize)) {
            $chunkSize = static::DEFAULT_CHUNK_SIZE;
        }

        //If the filesize is smaller
        //than the chunk size, we'll
        //make the chunk size relatively
        //smaller than the file size
        if ($fileSize <= $chunkSize) {
            $chunkSize = $fileSize / 2;
        }

        //Start the Upload Session with the file path
        //since the BoxFile object will be created
        //again using the new chunk size.
        $sessionId = $this->startUploadSession($dropboxFile->getFilePath(), $chunkSize);

        //Uploaded
        $uploaded = $chunkSize;

        //Remaining
        $remaining = $fileSize - $chunkSize;

        //While the remaining bytes are
        //more than the chunk size, append
        //the chunk to the upload session.
        while ($remaining > $chunkSize) {
            //Append the next chunk to the Upload session
            $sessionId = $this->appendUploadSession($dropboxFile, $sessionId, $uploaded, $chunkSize);

            //Update remaining and uploaded
            $uploaded = $uploaded + $chunkSize;
            $remaining = $remaining - $chunkSize;
        }

        //Finish the Upload Session and return the Uploaded File Metadata
        return $this->finishUploadSession($dropboxFile, $sessionId, $uploaded, $remaining, $path, $params);
    }

    /**
     * Get thumbnail size
     *
     * @param  string $size Thumbnail Size
     *
     * @return string
     */
    protected function getThumbnailSize($size)
    {
        $thumbnailSizes = [
            'thumb'  => 'w32h32',
            'small'  => 'w64h64',
            'medium' => 'w128h128',
            'large'  => 'w640h480',
            'huge'   => 'w1024h768'
        ];

        return isset($thumbnailSizes[$size]) ? $thumbnailSizes[$size] : $thumbnailSizes['small'];
    }

    /**
     * Get a thumbnail for an image
     *
     * @param  string $path   Path to the file you want a thumbnail to
     * @param  string $size   Size for the thumbnail image ['thumb','small','medium','large','huge']
     * @param  string $format Format for the thumbnail image ['jpeg'|'png']
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-get_thumbnail
     *
     * @return \Box\Models\Thumbnail
     */
    public function getThumbnail($path, $size = 'small', $format = 'jpeg')
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
        }

        //Invalid Format
        if (!in_array($format, ['jpeg', 'png'])) {
            throw new BoxClientException("Invalid format. Must either be 'jpeg' or 'png'.");
        }

        //Thumbnail size
        $size = $this->getThumbnailSize($size);

        //Get Thumbnail
        $response = $this->postToContent('/files/get_thumbnail', ['path' => $path, 'format' => $format, 'size' => $size]);

        //Get file metadata from response headers
        $metadata = $this->getMetadataFromResponseHeaders($response);

        //File Contents
        $contents = $response->getBody();

        //Make and return a Thumbnail model
        return new Thumbnail($metadata, $contents);
    }

    /**
     * Download a File
     *
     * @param  string $path   Path to the file you want to download
     *
     * @link https://www.box.com/developers/documentation/http/documentation#files-download
     *
     * @return \Box\Models\File
     */
    public function download($path)
    {
        //Path cannot be null
        if (is_null($path)) {
            throw new BoxClientException("Path cannot be null.");
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

    /**
     * Get Current Account
     *
     * @link https://www.box.com/developers/documentation/http/documentation#users-get_current_account
     *
     * @return \Box\Models\Account
     */
    public function getCurrentAccount()
    {
        //Get current account
        $response = $this->postToAPI('/users/get_current_account', []);
        $body = $response->getDecodedBody();

        //Make and return the model
        return new Account($body);
    }

    /**
     * Get Account
     *
     * @param string $account_id Account ID of the account to get details for
     *
     * @link https://www.box.com/developers/documentation/http/documentation#users-get_account
     *
     * @return \Box\Models\Account
     */
    public function getAccount($account_id)
    {
        //Get account
        $response = $this->postToAPI('/users/get_account', ['account_id' => $account_id]);
        $body = $response->getDecodedBody();

        //Make and return the model
        return new Account($body);
    }

    /**
     * Get Multiple Accounts in one call
     *
     * @param string $account_id Account ID of the account to get details for
     *
     * @link https://www.box.com/developers/documentation/http/documentation#users-get_account_batch
     *
     * @return \Box\Models\AccountList
     */
    public function getAccounts(array $account_ids = [])
    {
        //Get account
        $response = $this->postToAPI('/users/get_account_batch', ['account_ids' => $account_ids]);
        $body = $response->getDecodedBody();

        //Make and return the model
        return new AccountList($body);
    }

    /**
     * Get Space Usage for the current user's account
     *
     * @link https://www.box.com/developers/documentation/http/documentation#users-get_space_usage
     *
     * @return array
     */
    public function getSpaceUsage()
    {
        //Get space usage
        $response = $this->postToAPI('/users/get_space_usage', []);
        $body = $response->getDecodedBody();

        //Return the decoded body
        return $body;
    }
}
