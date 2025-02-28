<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Event;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\Document;
use MongoDB\Driver\Session;

/**
 * Class that holds event arguments for a preLoad event.
 */
final class PreLoadEventArgs extends LifecycleEventArgs
{
    private array $deprecatedDataArray;

    public function __construct(
        object $document,
        DocumentManager $dm,
        private Document &$data,
        ?Session $session = null,
    ) {
        parent::__construct($document, $dm, $session);
    }

    public function getRawData(): Document
    {
        if (isset($this->deprecatedDataArray)) {
            $this->data = Document::fromPHP($this->deprecatedDataArray);
            unset($this->deprecatedDataArray);
        }

        return $this->data;
    }

    public function setRawData(Document $document): void
    {
        $this->data = $document;
        unset($this->deprecatedDataArray);
    }

    /**
     * Get the array of data to be loaded and hydrated.
     *
     * @deprecated Use {@see self::getBsonDocument()} and {@see self::setBsonDocument()}
     *
     * @return array<string, mixed>
     */
    public function &getData(): array
    {
        $this->deprecatedDataArray ??= $this->data->toPHP(['root' => 'array']);

        return $this->deprecatedDataArray;
    }

    public function __destruct()
    {
        $this->getRawData();
    }
}
