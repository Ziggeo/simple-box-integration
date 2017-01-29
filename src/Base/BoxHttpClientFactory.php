<?php
namespace Ziggeo\BoxContent\Base;
use InvalidArgumentException;
use GuzzleHttp\Client as Guzzle;

/**
 * BoxHttpClientFactory
 */
class BoxHttpClientFactory
{
    /**
     * Make HTTP Client
     *
     * @param  BoxHttpClientInterface|GuzzleHttp\Client|null $handler
     *
     * @return BoxHttpClientInterface
     */
    public static function make($handler)
    {
        //No handler specified
        if (!$handler) {
            return new BoxGuzzleHttpClient();
        }

        //Custom Implemenation, maybe.
        if ($handler instanceof BoxHttpClientInterface) {
            return $handler;
        }

        //Handler is a custom configured Guzzle Client
        if ($handler instanceof Guzzle) {
            return new BoxGuzzleHttpClient($handler);
        }

        //Invalid handler
        throw new InvalidArgumentException('The http client handler must be an instance of GuzzleHttp\Client or an instance of Kunnu\Box\Http\Clients\BoxHttpClientInterface.');
    }
}
