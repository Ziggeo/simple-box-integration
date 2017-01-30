<?php
namespace Ziggeo\BoxContent\Base;

/**
 * BoxClient
 */
class BoxClient
{
    /**
     * Box API Root URL.
     *
     * @const string
     */
    const BASE_PATH = 'https://api.box.com/2.0';

    /**
     * Box API Content Root URL.
     *
     * @const string
     */
    const UPLOAD_PATH = 'https://upload.box.com/api/2.0';

    /**
     * BoxHttpClientInterface Implementation
     *
     * @var BoxHttpClientInterface
     */
    protected $httpClient;

    /**
     * Create a new BoxClient instance
     *
     * @param BoxHttpClientInterface $httpClient
     */
    public function __construct(BoxHttpClientInterface $httpClient)
    {
        //Set the HTTP Client
        $this->setHttpClient($httpClient);
    }

    /**
     * Get the HTTP Client
     *
     * @return BoxHttpClientInterface $httpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Set the HTTP Client
     *
     * @param BoxHttpClientInterface $httpClient
     *
     * @return BoxClient
     */
    public function setHttpClient(BoxHttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * Get the API Base Path.
     *
     * @return string API Base Path
     */
    public function getBasePath()
    {
        return static::BASE_PATH;
    }

    /**
     * Get the API Content Path.
     *
     * @return string API Content Path
     */
    public function getUploadPath()
    {
        return static::UPLOAD_PATH;
    }

    /**
     * Get the Authorization Header with the Access Token.
     *
     * @param string $accessToken Access Token
     *
     * @return array Authorization Header
     */
    protected function buildAuthHeader($accessToken = "")
    {
        return ['Authorization' => 'Bearer '. $accessToken];
    }

    /**
     * Get the Content Type Header.
     *
     * @param string $contentType Request Content Type
     *
     * @return array Content Type Header
     */
    protected function buildContentTypeHeader($contentType = "")
    {
        return ['Content-Type' => $contentType];
    }

    /**
     * Build URL for the Request
     *
     * @param string $endpoint Relative API endpoint
     * @param string $type Endpoint Type
     *
     *
     * @return string The Full URL to the API Endpoints
     */
    protected function buildUrl($endpoint = '', $type = 'api')
    {
        //Get the base path
        $base = $this->getBasePath();

        //If the endpoint type is 'upload'
        if ($type === 'upload') {
            //Get the Content Path
            $base = $this->getUploadPath();
        }

        //Join and return the base and api path/endpoint
        return $base . $endpoint;
    }

    /**
     * Send the Request to the Server and return the Response
     *
     * @param  BoxRequest $request
     *
     * @return BoxResponse
     *
     * @throws Exceptions\BoxClientException
     */
    public function sendRequest(BoxRequest $request)
    {
        //Method
        $method = $request->getMethod();

        //Prepare Request
        list($url, $headers, $requestBody, $options) = $this->prepareRequest($request);

        //Send the Request to the Server through the HTTP Client
        //and fetch the raw response as BoxRawResponse
        $rawResponse = $this->getHttpClient()->send($url, $method, $requestBody, $headers, $options);

        //Create BoxResponse from BoxRawResponse
        $boxResponse = new BoxResponse(
            $request,
            $rawResponse->getBody(),
            $rawResponse->getHttpResponseCode(),
            $rawResponse->getHeaders()
        );

        //Return the BoxResponse
        return $boxResponse;
    }

    /**
     * Prepare a Request before being sent to the HTTP Client
     *
     * @param  BoxResponse $request
     *
     * @return array [Request URL, Request Headers, Request Body, Options Array]
     */
    protected function prepareRequest(BoxRequest $request)
    {
        $options = array();
        $contentType = true;
        //Build URL
        $url = $this->buildUrl($request->getEndpoint(), $request->getEndpointType());

        //The Endpoint is content
        if ($request->getEndpointType() === 'upload') {
            $requestBody = null;
            //If a File is also being uploaded
            if ($request->hasFile()) {
                //Request Body (File Contents)
                $options = array(
                   "multipart" => array(
                       array(
                           'name'     => 'attributes',
                           'contents' => json_encode($request->getParams()["attributes"])
                       ),
                       array(
                           'name'     => 'file',
                           'contents' => fopen($request->getFile()->getFilePath(), 'r')
                       )
                   )
                );
                $contentType = false;
            } else {
                //Empty Body
                $requestBody = null;
            }
        } else {
            //The endpoint is 'api'
            //Request Body (Parameters)
            $requestBody = $request->getJsonBody()->getBody();
        }

        //Build headers
        $headers = array_merge(
            $this->buildAuthHeader($request->getAccessToken()),
            $request->getHeaders()
        );

        if ($contentType)
            $headers[] = $this->buildContentTypeHeader($request->getContentType());
        //Return the URL, Headers and Request Body
        return [$url, $headers, $requestBody, $options];
    }
}
