# SuperSimpleCrawler
Super Simple Crawler for PHP 8
[![Latest Stable Version](https://poser.pugx.org/crispy-computing-machine/SuperSimpleCrawler/v/stable)](https://packagist.org/packages/crispy-computing-machine/SuperSimpleCrawler) [![Total Downloads](https://poser.pugx.org/bcrispy-computing-machine/SuperSimpleCrawler/downloads)](https://packagist.org/packages/crispy-computing-machine/SuperSimpleCrawler)

```
composer require crispy-computing-machine/supersimplecrawler
```

```php
//Composer
require 'vendor/autoload.php';

// Libs
use SuperSimple\Crawler;
use SuperSimple\CrawlerCompleteException;
use DOMDocument;

// Configure the crawler.
$crawler = new Crawler($verbose = true);
try {
    $crawler->setUrl("https://www.php.net/"); // Set the URL.
    $crawler->setPort(80); // Set the port (80 is the default HTTP port).
    $crawler->setFollowRedirects(true); // Follow redirects.
    $crawler->setFollowMode(2); // Follow only links within the same host.
    $crawler->setRequestLimit(10); // Limit the number of requests.
    $crawler->setContentSizeLimit(2000000); // Limit the content size (2 MB in this case).
    $crawler->setTrafficLimit(10000000); // Limit the traffic (10 MB in this case).
    $crawler->setUserAgentString("Mozilla/5.0 (compatible; MyCrawler/1.0)"); // Set a custom user agent.
    $crawler->setWorkingDirectory(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crawler'); // Set a directory for storing data.
    $crawler->setProxy(); // Set a proxy (if required).
    $crawler->setCertificateVerify(true); // Verify SSL certificates.
    $crawler->setConnectionTimeout(10); // Set the connection timeout (10 seconds).
    $crawler->setStreamTimeout(20); // Set the stream timeout (20 seconds).
    $crawler->setRequestDelay(3000); // Wait 1048576 millisecond between requests.
    $crawler->setConcurrency(10); // multiprocessing mode
    $crawler->addContentTypeReceiveRule(['text/html', 'application/json']);
    
    // Handle doc
    $crawler->setFulfilledCallback(function($url, DOMDocument $content){
        echo 'Successful request! ' . $url . ' -> ' . strlen($content->textContent) . PHP_EOL;
    });

    // Handle error
    $crawler->setRejectedCallback(function ($url, DOMDocument $content){
        echo 'Failed request! ' . $url . ' -> ' . strlen($content->textContent) . PHP_EOL;
    });

    // Start the crawling process.
    $crawler->crawl();

} catch (CrawlerCompleteException $e) {

    // Crawler complete report
    Crawler::log("Total pages downloaded: " . $e->getTotalPages(), "info");
    Crawler::log("Total size downloaded: " . $e->getTotalSize() . " bytes", "info");
    Crawler::log("Total links followed: " . $e->getTotalLinks(), "info");
    Crawler::log("Abort Reason: " . $e->getMessage(), "info");

} finally {
    // Additional clean up or summary actions
    Crawler::log("Crawler has ended gracefully.", "success");
}
```