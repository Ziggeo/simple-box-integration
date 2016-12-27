<?php

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
     * @return \Kunnu\Box\Http\BoxRawResponse Raw response from the server
     *
     * @throws \Kunnu\Box\Exceptions\BoxClientException
     */
    public function send($url, $method, $body, $headers = [], $options = []);
}
