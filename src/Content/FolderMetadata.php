<?php
namespace Ziggeo\BoxContent\Content;


class FolderMetadata extends BaseModel
{
    /**
     * A unique identifier of the folder
     *
     * @var string
     */
    protected $id;

    /**
     * The last component of the path (including extension).
     * This never contains a slash.
     *
     * @var string
     */
    protected $name;

    /**
     * The lowercased full path in the user's Box.
     * This always starts with a slash.
     *
     * @var string
     */
    protected $parent;



    /**
     * Create a new FolderMetadata instance
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = $this->getDataProperty('id');
        $this->name = $this->getDataProperty('name');
        $this->parent = $this->getDataProperty('parent');
    }

    /**
     * Get the 'id' property of the folder model.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the 'name' property of the folder model.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the 'parent' property of the folder model.
     *
     * @return string
     */
    public function getPathLower()
    {
        return $this->parent;
    }

}