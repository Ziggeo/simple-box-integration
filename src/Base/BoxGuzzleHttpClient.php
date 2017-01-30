<?php
namespace Ziggeo\BoxContent\Base;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Exception\RingException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * BoxGuzzleHttpClient
 */
class BoxGuzzleHttpClient implements BoxHttpClientInterface
{
    /**
     * GuzzleHttp client
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Create a new BoxGuzzleHttpClient instance
     *
     * @param Client $client GuzzleHttp Client
     */
    public function __construct(Client $client = null)
    {
        //Set the client
        $this->client = $client ?: new Client();
    }

    /**
     * Send request to the server and fetch the raw response
     *
     * @param  string $url     URL/Endpoint to send the request to
     * @param  string $method  Request Method
     * @param  string|resource|StreamInterface $body Request Body
     * @param  array  $headers Request Headers
     * @param  array  $options Additional Options
     *
     * @return BoxRawResponse Raw response from the server
     *
     * @throws BoxClientException
     */
    public function send($url, $method, $body, $headers = [], $options = [])
    {
        //Create a new Request Object
        $request = new Request($method, $url, $headers, $body);

        try {
            //Send the Request
            $rawResponse = $this->client->send($request, $options);
        } catch (RequestException $e) {
            $rawResponse = $e->getResponse();

            if ($e->getCode() === 409) {
                $body = $this->getResponseBody($rawResponse);

                $rawHeaders = $rawResponse->getHeaders();
                $httpStatusCode = $rawResponse->getStatusCode();

                //Create and return a BoxRawResponse object
                return new BoxRawResponse($rawHeaders, $body, $httpStatusCode);
            }

            if ($e->getPrevious() instanceof RingException || !$rawResponse instanceof ResponseInterface) {
                throw new Exceptions\BoxClientException($e->getMessage(), $e->getCode());
            }
        }

        //Something went wrong
        if ($rawResponse->getStatusCode() >= 400) {
            throw new Exceptions\BoxClientException($rawResponse->getBody());
        }

        //Get the Response Body
        $body = $this->getResponseBody($rawResponse);

        $rawHeaders = $rawResponse->getHeaders();
        $httpStatusCode = $rawResponse->getStatusCode();

        //Create and return a BoxRawResponse object
        return new BoxRawResponse($rawHeaders, $body, $httpStatusCode);
    }

    /**
     * Get the Response Body
     *
     * @param string|\Psr\Http\Message\ResponseInterface $response Response object
     *
     * @return string
     */
    protected function getResponseBody($response)
    {
        //Response must be string
        $body = $response;

        if ($response instanceof ResponseInterface) {
            //Fetch the body
            $body = $response->getBody();
        }

        if ($body instanceof StreamInterface) {
            $body = $body->getContents();
        }

        return $body;
    }
}
