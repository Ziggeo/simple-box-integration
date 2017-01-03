<?php

namespace Pablo2309\BoxContent\Base;
use Pablo2309\BoxContent\Content\BoxFile;


class RequestBodyStream implements RequestBodyInterface
{

    /**
     * File to be sent with the Request
     *
     * @var BoxFile
     */
    protected $file;

    /**
     * Create a new RequestBodyStream instance
     *
     * @param BoxFile $file
     */
    public function __construct(BoxFile $file)
    {
        $this->file = $file;
    }

    /**
     * Get the Body of the Request
     *
     * @return resource
     */
    public function getBody()
    {
        return $this->file->getContents();
    }
}