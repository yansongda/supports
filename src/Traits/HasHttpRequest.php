<?php

namespace Yansongda\Supports\Traits;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

trait HasHttpRequest
{
    /**
     * @var string
     */
    protected $baseUri;

    /**
     * @var float
     */
    protected $timeout = 5.0;

    /**
     * @var float
     */
    protected $connectTimeout = 3.0;

    /**
     * Http client.
     *
     * @var Client|null
     */
    protected $httpClient = null;

    /**
     * Http client options.
     *
     * @var array
     */
    protected $httpOptions = [];

    /**
     * Send a GET request.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $endpoint
     * @param array  $query
     * @param array  $headers
     *
     * @return array|string
     */
    public function get($endpoint, $query = [], $headers = [])
    {
        return $this->request('get', $endpoint, [
            'headers' => $headers,
            'query' => $query,
        ]);
    }

    /**
     * Send a POST request.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string       $endpoint
     * @param string|array $data
     * @param array        $options
     *
     * @return array|string
     */
    public function post($endpoint, $data, $options = [])
    {
        if (!is_array($data)) {
            $options['body'] = $data;
        } else {
            $options['form_params'] = $data;
        }

        return $this->request('post', $endpoint, $options);
    }

    /**
     * Send request.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $options
     *
     * @return array|string
     */
    public function request($method, $endpoint, $options = [])
    {
        return $this->unwrapResponse($this->getHttpClient()->{$method}($endpoint, $options));
    }

    /**
     * Set http client.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return $this
     */
    public function setHttpClient(Client $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    /**
     * Return http client.
     */
    public function getHttpClient(): Client
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = $this->getDefaultHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * Get default http client.
     *
     * @author yansongda <me@yansongda.cn>
     */
    public function getDefaultHttpClient(): Client
    {
        return new Client($this->getOptions());
    }

    /**
     * Get default options.
     *
     * @author yansongda <me@yansongda.cn>
     */
    public function getOptions(): array
    {
        return array_merge([
            'base_uri' => $this->baseUri,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
        ], $this->httpOptions);
    }

    /**
     * setOptions.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->httpOptions = $options;

        return $this;
    }

    /**
     * setBaseUri.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return $this
     */
    public function setBaseUri(string $url): self
    {
        $parsedUrl = parse_url($url);

        $this->baseUri = $parsedUrl['scheme'].'://'.$parsedUrl['host'].(isset($parsedUrl['port']) ? (':'.$parsedUrl['port']) : '');

        return $this;
    }

    /**
     * getBaseUri.
     *
     * @author yansongda <me@yansongda.cn>
     */
    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeout(float $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    public function getHttpOptions(): array
    {
        return $this->httpOptions;
    }

    public function setHttpOptions(array $httpOptions): self
    {
        $this->httpOptions = $httpOptions;

        return $this;
    }

    /**
     * Convert response.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return array|string
     */
    public function unwrapResponse(ResponseInterface $response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $contents = $response->getBody()->getContents();

        if (false !== stripos($contentType, 'json') || stripos($contentType, 'javascript')) {
            return json_decode($contents, true);
        } elseif (false !== stripos($contentType, 'xml')) {
            return json_decode(json_encode(simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
        }

        return $contents;
    }
}
