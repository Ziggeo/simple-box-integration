<?php
namespace Ziggeo\BoxContent\Content;

class BoxFileMetadata extends BaseModel
{
    /**
     * A unique identifier of the file
     *
     * @var string
     */
    protected $id;

    /**
     * The last component of the path (including extension).
     *
     * @var string
     */
    protected $name;

    /**
     * A unique identifier for the current revision of a file.
     * This field is the same rev as elsewhere in the API and
     * can be used to detect changes and avoid conflicts.
     *
     * @var string
     */
    protected $rev;

    /**
     * The file size in bytes.
     *
     * @var int
     */
    protected $size;



    /**
     * Create a new FileMetadata instance
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = $this->getDataProperty('id');
        $this->rev = $this->getDataProperty('rev');
        $this->name = $this->getDataProperty('name');
        $this->size = $this->getDataProperty('size');

    }

    /**
     * Get the 'id' property of the file model.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the 'name' property of the file model.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the 'rev' property of the file model.
     *
     * @return string
     */
    public function getRev()
    {
        return $this->rev;
    }

    /**
     * Get the 'size' property of the file model.
     *
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

}