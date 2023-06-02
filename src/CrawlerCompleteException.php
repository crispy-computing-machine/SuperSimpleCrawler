<?php

namespace SuperSimple\SuperSimple;
/**
 * Not really an exception.
 *
 * Thrown when one of the limits set are reached.
 */
class CrawlerCompleteException extends Exception
{

    /**
     * Total pages visited
     * @var int
     */
    private int $totalPages;

    /**
     * Total bytes downloaded
     * @var int
     */
    private int $totalSize;

    /**
     * Total links collected
     * @var int
     */
    private int $totalLinks;

    /**
     * Main crawler exception for crawl complete.
     *
     * @param $message
     * @param $totalPages
     * @param $totalSize
     * @param $totalLinks
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message, $totalPages, $totalSize, $totalLinks, int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setTotalPages($totalPages);
        $this->setTotalSize($totalSize);
        $this->setTotalLinks($totalLinks);

    }

    /**
     * @return int
     */
    public function getTotalLinks(): int
    {
        return $this->totalLinks;
    }

    /**
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * @param mixed $totalPages
     */
    public function setTotalPages(mixed $totalPages): void
    {
        $this->totalPages = $totalPages;
    }

    /**
     * @return int
     */
    public function getTotalSize(): int
    {
        return $this->totalSize;
    }

    /**
     * @param mixed $totalSize
     */
    public function setTotalSize(mixed $totalSize): void
    {
        $this->totalSize = $totalSize;
    }

    /**
     * @param mixed $totalLinks
     */
    public function setTotalLinks(mixed $totalLinks): void
    {
        $this->totalLinks = $totalLinks;
    }
}