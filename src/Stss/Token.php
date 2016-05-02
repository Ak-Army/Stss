<?php

namespace Stss;

/**
 * Token.
 */
class Token
{
    /**
     * @var string
     */
    public $type;

    /**
     * @var Token
     */
    public $parent;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var int
     */
    public $level = 0;

    /**
     * @var int
     */
    public $sourceLine = 0;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $selectors;

    /**
     * @var array
     */
    public $comments = [];

    /**
     * @var array
     */
    public $children = [];

    /**
     * [__construct description].
     *
     * @param string $type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Returns a value indicating if this Token is a child of the passed Token.
     * This just checks the levels of the Tokens. If this Token is at a greater
     * level than the passed Token if is a child of it.
     *
     * @param Token $token
     *
     * @return bool true if the Token is a child of the passed Token, false if not
     */
    public function isChildOf(Token $token)
    {
        return $this->level > $token->level;
    }

    /**
     * Returns the last child node of this node.
     *
     * @return Node the last child node of this node
     */
    public function getLastChild()
    {
        return $this->children[count($this->children) - 1];
    }

    /**
     * Adds a child to this Token.
     *
     * @param Token $child
     *
     * @return Token the child to add
     */
    public function addChild(Token $child)
    {
        $child->parent = $this;
        $this->children[] = $child;
    }
}
