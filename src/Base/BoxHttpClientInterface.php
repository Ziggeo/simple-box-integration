<?php
namespace Ziggeo\BoxContent\Base;
/**
 * BoxHttpClientInterface
 */
interface BoxHttpClientInterface
{
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
    public function send($url, $method, $body, $headers = [], $options = []);
}
