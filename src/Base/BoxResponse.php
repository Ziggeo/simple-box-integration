<?php
namespace Ziggeo\BoxContent\Base;


class BoxResponse
{
    /**
     *  The HTTP status code response
     *
     * @var int
     */
    protected $httpStatusCode;

    /**
     *  The headers returned
     *
     * @var array
     */
    protected $headers;

    /**
     *  The raw body of the response
     *
     * @var string
     */
    protected $body;

    /**
     *  The decoded body of the response
     *
     * @var array
     */
    protected $decodedBody = [];

    /**
     * The original request that returned this response
     *
     * @var BoxRequest
     */
    protected $request;

    /**
     * Create a new BoxResponse instance
     *
     * @param BoxRequest $request
     * @param string|null $body
     * @param int|null    $httpStatusCode
     * @param array       $headers
     *
     * @throws BoxClientException
     */
    public function __construct(BoxRequest $request, $body = null, $httpStatusCode = null, array $headers = [])
    {
        $this->request = $request;
        $this->body = $body;
        $this->httpStatusCode = $httpStatusCode;
        $this->headers = $headers;

        //Decode the Response Body
        $this->decodeBody();
    }

    /**
     * Get the Request Request
     *
     * @return BoxRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the Response Body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Get the Decoded Body
     *
     * @return string
     */
    public function getDecodedBody()
    {
        return $this->decodedBody;
    }

    /**
     * Get Access Token for the Request
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->getRequest()->getAccessToken();
    }

    /**
     * Get Request Headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get the HTTP Status Code
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * Decode the Body
     *
     * @throws BoxClientException
     *
     * @return void
     */
    protected function decodeBody()
    {
        $body = $this->getBody();

        if (isset($this->headers['Content-Type']) && in_array('application/json', $this->headers['Content-Type'])) {
            $this->decodedBody = json_decode((string)$body, true);
        } else {
            $this->decodedBody = $this->body;
        }

        // If the response needs to be validated
        if ($this->getRequest()->validateResponse()) {
            //Validate Response
            $this->validateResponse();
        }
    }

    /**
     * Validate Response
     *
     * @return void
     */
    protected function validateResponse()
    {
        // If JSON cannot be decoded
        if ($this->decodedBody === null) {
            throw new BoxClientException("Invalid Response");
        }
    }
}
