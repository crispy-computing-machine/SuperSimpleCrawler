<?php

namespace SuperSimple;

// Libs
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use \DOMDocument;
use \DOMXPath;
use \Exception;
use \RuntimeException;

/**
 * Crawl a webpage with a configured crawler
 * Uses "guzzlehttp/guzzle": "^7.2"
 */
class Crawler
{

    /**
     *  -v for verbose mode
     * @var bool
     */
    private static bool $verbose = false;

    /**
     * Actually the "currently processing documents"
     * @var int
     */
    private int $activeRequests = 0;

    /*
     * Guzzle
     */
    private Client $client;

    /**
     * Call back function with Guzzle\ResponseInterface parameters
     * @var callable
     */
    public $fulfilledCallback;

    /**
     * Call back function with Guzzle\ResponseInterface parameters
     * @var callable
     */
    private $rejectedCallback;

    /**
     * Main URl queue. First url is always the root
     * @var array
     */
    private array $urls = [];

    /**
     * Cache to prevent URls being visited more than once (wasting resources)
     * @var array
     */
    private array $visitedUrls = [];

    /**
     * Total pages fetched so far (based on "completed only" config)
     * @var int
     */
    private int $totalPages = 0;

    /**
     * Use a proxy to not get blocked
     * @var array
     */
    private array $proxy;

    /**
     * Custom user agent or use a popular browser
     * @var string
     */
    private string $userAgent;

    /**
     * Guzzle follows redirects be default I think.
     * overrides the allow_redirects guzzle config
     * @var bool
     */
    private bool $followRedirects = true;

    /**
     * Guzzle request delay
     * @var int
     */
    private int $requestDelay = 0;

    /**
     * Guzzle request timeout
     * @var int
     */
    private int $timeout = 0;

    /**
     * Default HTTP port.
     * Port if it's different from 80 or 443
     * @var int
     */
    private int $port;

    /**
     * default mode
     * Current domain or go off on to the internets
     * @var int
     */
    private int $followMode = 2;

    /*
     * No limit by default
     * @var int
     */
    private int $requestLimit;

    /**
     * Only increment doc count if we got the doc (200 status)
     * @var bool
     */
    private bool $onlyCountReceivedDocuments;

    /**
     * Content size in bytes
     * No limit by default
     * @var int
     */
    private int $contentSizeLimit;

    /**
     * Traffic limit in bytes
     * No limit by default
     * @var int
     */
    private int $trafficLimit;

    /**
     * Initial total traffic.
     * traffic tally.
     * @var int
     */
    private int $totalTraffic = 0;

    /**
     * Total requests
     * @var int
     */
    private int $linksFollowed = 0;

    /**
     * If a traffic limit has been set,
     * should the crawler complete the final document if it may send it over the limit.
     * @var bool
     */
    private bool $completeRequestedFiles;

    /**
     * Store file for each url
     * $workingDirectory . md5(filename) . 'html';
     * /tmp Default directory
     * @var string
     */
    private string $workingDirectory = '/tmp';

    /**
     * verify SSl certificates
     * false = insecure!
     * @var bool
     */
    private bool $certificateVerify = true;  // Verify SSL certificates by default

    /**
     * Timeout to give up on page request, continues crawling...
     * @var int
     */
    private int $streamTimeout;  // No timeout by default


    /**
     * Crawler object basic setup
     */
    public function __construct()
    {
        $this->client = new Client();

        // Check if verbose (-v) flag is set
        $options = getopt("v");
        if (isset($options['v'])) {
            self::$verbose = true;
        }
    }

    /**
     * First url added becomes root URL
     * Additional URLS will be added to queue and crawled
     * @param string $url
     * @return bool|null
     */
    public function setURL(string $url): ?bool
    {
        $this->urls[] = $url;
        return true;
    }

    /**
     * Proxy to avoid being blocked
     * @param string|null $proxy_host
     * @param int|null $proxy_port
     * @param string|null $proxy_username
     * @param string|null $proxy_password
     * @return void
     */
    public function setProxy(string $proxy_host = null, int $proxy_port = null, string $proxy_username = null, string $proxy_password = null): void
    {
        if ($proxy_username !== null && $proxy_password !== null) {
            $this->proxy = [
                'http' => "$proxy_username:$proxy_password@$proxy_host:$proxy_port",
                'https' => "$proxy_username:$proxy_password@$proxy_host:$proxy_port",
            ];
        }
    }

    /**
     * Crawler user agent string
     * @param string $user_agent
     * @return bool
     */
    public function setUserAgentString(string $user_agent): bool
    {
        $this->userAgent = $user_agent;
        return true;
    }

    /**
     * Redirect rule
     * @param bool $mode
     * @return bool
     */
    public function setFollowRedirects(bool $mode): bool
    {
        $this->followRedirects = $mode;
        return true;
    }

    /**
     * HTTp request delay
     * @param float $time
     * @return bool
     */
    public function setRequestDelay(float $time): bool
    {
        $this->requestDelay = $time;
        return true;
    }


    /**
     * HTTp request timeout
     * @param int $timeout
     * @return bool|null
     */
    public function setConnectionTimeout(int $timeout): ?bool
    {
        $this->timeout = $timeout;
        return true;
    }

    /**
     * Custom HTTP port
     * @param int $port
     * @return bool
     */
    public function setPort(int $port): bool
    {
        $this->port = $port;
        return true;
    }

    /**
     * Crawl mode
     * @throws RuntimeException
     * @param int $follow_mode
     * @return bool
     */
    public function setFollowMode(int $follow_mode): bool
    {
        if ($follow_mode < 0 || $follow_mode > 3) {
            throw new RuntimeException('Invalid follow mode');
        }
        $this->followMode = $follow_mode;
        return true;
    }

    /**
     * Page limit
     * @param int $limit
     * @param bool $only_count_received_documents
     * @return bool
     * @throws RuntimeException
     */
    public function setRequestLimit(int $limit, bool $only_count_received_documents = true): bool
    {
        if ($limit <= 0) {
            throw new RuntimeException('Invalid request limit');
        }

        $this->requestLimit = $limit;
        $this->onlyCountReceivedDocuments = $only_count_received_documents;
        return true;
    }

    /**
     * byte limit
     * @param int $bytes
     * @return bool
     * @throws RuntimeException
     */
    public function setContentSizeLimit(int $bytes): bool
    {
        if ($bytes <= 0) {
            throw new RuntimeException('Invalid content size limit');
        }

        $this->contentSizeLimit = $bytes;
        return true;
    }

    /**
     * @param int $bytes
     * @param bool $complete_requested_files
     * @return bool|null
     * @throws RuntimeException
     */
    public function setTrafficLimit(int $bytes, bool $complete_requested_files = true): ?bool
    {
        if ($bytes <= 0) {
            throw new RuntimeException('Invalid traffic limit');
        }

        $this->trafficLimit = $bytes;
        $this->completeRequestedFiles = $complete_requested_files;
        return true;
    }

    /**
     * tmp dir
     * @param string $directory
     * @return bool|null
     * @throws RuntimeException
     */
    public function setWorkingDirectory(string $directory): ?bool
    {
        if (!is_dir($directory) || !is_writable($directory)) {
            if(!mkdir($directory) && !is_dir($directory)){
                throw new RuntimeException('Invalid working directory');
            }
        }

        $this->workingDirectory = $directory;
        return true;
    }


    /**
     * SSL_NO_VERIFY
     * @param bool $verify
     * @return void
     */
    public function setCertificateVerify(bool $verify): void
    {
        $this->certificateVerify = $verify;
    }

    /**
     * HTTP timeout
     * @param int $timeout
     * @return bool|null
     * @throws RuntimeException
     */
    public function setStreamTimeout(int $timeout): ?bool
    {
        if ($timeout <= 0) {
            throw new RuntimeException('Invalid timeout');
        }

        $this->streamTimeout = $timeout;
        return true;
    }

    /**
     * Extracts and normalizes all links from the given HTML content.
     *
     * @param string $html The HTML content.
     * @param string $baseUrl The base URL for resolving relative URLs.
     * @return array An array of normalized links.
     */
    public static function extractLinks(string $html, string $baseUrl): array
    {

        // Collect links....
        $links = [];

        // Load the HTML into a DOMDocument.
        $doc = self::createDom($html);

        // Create a DOMXPath object for querying the document.
        $xpath = new DOMXPath($doc);

        // Query all <a> elements with a href attribute.
        $nodes = $xpath->query('//a[@href]');

        // Extract and normalize the URL from each node.
        foreach ($nodes as $node) {
            $url = $node->getAttribute('href');

            // Resolve relative URLs.
            $url = self::resolveUrl($url, $baseUrl);

            $links[] = $url;
        }

        return $links;
    }

    /**
     * Helper to create DOM from HTML
     * @param string $html
     * @return DOMDocument
     */
    public static function createDom(string $html): DOMDocument
    {
        // Suppress warnings due to malformed HTML.
        libxml_use_internal_errors(true);

        // Load the HTML into a DOMDocument.
        $doc = new DOMDocument();
        $doc->loadHTML($html);

        // Reset error handling.
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $doc;
    }

    /**
     * Resolves a relative URL based on a base URL.
     *
     * @param string $url The possibly relative URL.
     * @param string $baseUrl The base URL for resolving.
     * @return string The resolved URL.
     */
    private static function resolveUrl(string $url, string $baseUrl): string
    {
        return UriResolver::resolve(new Uri($baseUrl),new Uri($url));
    }

    /**
     * Main doc handler
     * @param callable $callback
     * @return void
     */
    public function setFulfilledCallback(callable $callback): void
    {
        $this->fulfilledCallback = $callback;
    }

    /**
     * Main error handler
     * @param callable $callback
     * @return void
     */
    public function setRejectedCallback(callable $callback): void
    {
        $this->rejectedCallback = $callback;
    }

    /*
     * Crawl main
     */
    public function crawl(): void
    {

        /**
         * @throws CrawlerCompleteException
         */
        $requests = function ($urls) {

            foreach ($urls as $url) {

                // Check limit
                if (isset($this->requestLimit) && $this->totalPages >= $this->requestLimit) {
                    throw new CrawlerCompleteException('Request limit reached', $this->totalPages, $this->totalTraffic, count($this->urls));
                }

                // Current URL
                $parsedUrl = parse_url($url);

                // Custom port
                if (isset($this->port) && ($this->port !== 80 && $this->port !== 443)) {
                    $url = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ':' . $this->port . ($parsedUrl['path'] ?? '');
                }

                // Follow mode based on root url
                $rootUrl = $this->urls[0];  // assume the first url is the root
                $rootUrlParsed = parse_url($rootUrl);  // assume the first url is the root
                switch ($this->followMode) {
                    case 0:
                        // follow all links
                        break;
                    case 1:
                    case 2:
                        // follow links with same host
                        if ($parsedUrl['host'] !== $rootUrlParsed['host']) {
                           self::log('Skipping (wrong domain/subdomain): ' . $url, "info");
                            continue 2;
                        }
                        break;
                    case 3:
                        // follow links with same path or under
                        if (!str_starts_with($parsedUrl['path'], $rootUrlParsed['path'])) {
                            self::log('Skipping (wrong path): ' . $url, "info");
                            continue 2;
                        }
                        break;
                }

                // If it has been visited, yield!
                if (isset($this->visitedUrls[$url])) {
                    continue;
                }

                // Determine if we crawl the link
                // use root url to extract links
                yield function () use ($url, $rootUrl) {

                    // main async request, "then's" tagged on below...
                    $requestPromise = $this->client->getAsync($url, ['stream' => true]);

                    $requestPromise->then(function (ResponseInterface $response) use($rootUrl) {

                        // Increment active request count.
                        $this->activeRequests++;

                        // Response data
                        $statusCode = $response->getStatusCode();
                        $body = $response->getBody();
                        $url = $body->getMetadata('uri');
                        $content = $body->getContents(); // Read from stream
                        $contentSizeInBytes = strlen($content); // Count bytes from stream

                        // Doc count
                        $this->totalPages++;
                        if ($this->onlyCountReceivedDocuments && $statusCode !== 200) {
                            $this->totalPages--;
                        }

                        // tmp directory, ensure its writable!
                        self::log('Storing temporary file for URL: ' . $url, "info");

                        // Create a safe filename, Store content in a file in the working directory
                        if(isset($this->workingDirectory)){
                            $filename = md5($url) . '.html';
                            file_put_contents($this->workingDirectory . '/' . $filename, $content);
                        }

                        // If content size limit is set, add a callback to check the size
                        if (isset($this->contentSizeLimit)) {
                            self::log('Checking content size limit: ' . $url, "info");
                            if ($contentSizeInBytes > $this->contentSizeLimit) {
                                throw new CrawlerCompleteException('Content size limit exceeded', $this->totalPages, $this->totalTraffic, count($this->urls));
                            }
                        }

                        // If traffic limit is set, add a callback to check the size
                        if (isset($this->trafficLimit)) {
                            $this->totalTraffic += $contentSizeInBytes;
                            self::log('Checking traffic limit: ' . $url, "info");

                            if (!$this->completeRequestedFiles && $this->totalTraffic > $this->trafficLimit) {
                                throw new CrawlerCompleteException('Traffic limit exceeded', $this->totalPages, $this->totalTraffic, count($this->urls));
                            }
                        }

                        // Extract this pages link sand add them to a queue
                        self::log('Extracting links from URL: ' . $url . '(Root:'.$rootUrl.')', "info");
                        $links = SuperSimpleCrawler::extractLinks($content, $rootUrl);
                        foreach ($links as $link){
                            if (!isset($this->visitedUrls[$link])) {
                                $this->setURL($link);
                            }
                        }
                        self::log('Added ' . count($links) .' links!', "success");
                        self::log('Total links: ' . $this->totalPages, "info");

                        // If a callback function is set, call it
                        if (isset($this->fulfilledCallback)) {
                            try{
                                call_user_func($this->fulfilledCallback, $url, self::createDom($content));
                            } catch (Exception $e){
                                self::log($e->getMessage(), "error");

                            }

                        }

                        // If a callback function is set, call it
                        if (isset($this->rejectedCallback) && $statusCode !== 200) {
                            try{
                                call_user_func($this->rejectedCallback, $url, self::createDom($content));
                            } catch (Exception $e){
                                self::log($e->getMessage(), "error");
                            }
                        }


                        // Decrement active request count. (we just processed a url)
                        $this->activeRequests--;

                    });

                    // debug
                    self::log('Crawling URL: ' . $url, "success");

                    // yield request to process, and mark as visited
                    $this->visitedUrls[$url] = true;
                    $this->linksFollowed++;
                    return $requestPromise;
                };

            }
        };

        // Main crawler client config based on configuration options passed in
        $clientOptions = [
            'concurrency' => 5,
            'allow_redirects' => $this->followRedirects,
            'delay' => $this->requestDelay,
            'timeout' => $this->timeout,
        ];

        if (isset($this->proxy)) {
            $clientOptions['proxy'] = $this->proxy;
        }

        if (isset($this->userAgent)) {
            $clientOptions['headers'] = ['User-Agent' => $this->userAgent];
        }

        if ($this->certificateVerify) {
            $clientOptions['verify'] = $this->certificateVerify;
        }

        if (isset($this->streamTimeout)) {
            $clientOptions['read_timeout'] = $this->streamTimeout;
        }

        // Process until no URLS left, or we reach a limit...
        do {
            $pool = new Pool($this->client, $requests($this->urls), $clientOptions);
            $promise = $pool->promise();
            $promise->wait();
        } while ($this->activeRequests > 0 || !empty($this->urls));

    }

    /**
     * Verbose logging
     * @param $message
     * @param string $type
     * @return void
     */
    public static function log($message, string $type = 'default'): void
    {
        // Get colour code
        $colours = [
            'error' => "\033[31m",
            'success' => "\033[32m",
            'info' => "\033[34m",
            'default' => "\033[0m"
        ];

        if (self::$verbose) {
            echo $colours[$type] . $message . $colours[$type] . PHP_EOL;
        }
    }

}