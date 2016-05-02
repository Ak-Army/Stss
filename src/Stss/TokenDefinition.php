<?php

namespace Stss;

/**
 * TokenDefinition.
 */
class TokenDefinition
{
    private $nodeIdentifier;
    private $nodeIdentifierLength;
    private $pattern;
    private $tokenType;
    private $isLast;
    private $hasSubDefinition = false;
    private $subDefinition = [];

    /**
     * [__construct description].
     *
     * @param [type] $nodeIdentifier
     * @param [type] $nodeIdentifierLength
     * @param [type] $pattern
     * @param [type] $tokenType
     * @param bool   $isLast
     */
    public function __construct($nodeIdentifier, $nodeIdentifierLength, $pattern, $tokenType, $isLast = false)
    {
        $this->nodeIdentifier = $nodeIdentifier;
        $this->nodeIdentifierLength = $nodeIdentifierLength;
        $this->pattern = $pattern;
        $this->tokenType = $tokenType;
        $this->isLast = $isLast;
    }

    /**
     * [addSubDef description].
     *
     * @param TokenDefinition $tokenDefinition
     */
    public function addSubDef(TokenDefinition $tokenDefinition)
    {
        $this->hasSubDefinition = true;
        $this->subDefinition[] = $tokenDefinition;
    }

    /**
     * [match description].
     *
     * @param string $input
     *
     * @return array|null
     */
    public function match($input)
    {
        if ($this->nodeIdentifierLength > 1) {
            $char = substr($input, ($this->isLast ? strlen($input) - 1 : 0), $this->nodeIdentifierLength);
        } else {
            $char = $input[($this->isLast ? strlen($input) - 1 : 0)];
        }

        if ($char == $this->nodeIdentifier) {
            if ($this->hasSubDefinition) {
                foreach ($this->subDefinition as $def) {
                    $match = $def->match(substr($input, $this->nodeIdentifierLength));
                    if ($match) {
                        return $match;
                    }
                }
            } else {
                $result = preg_match($this->pattern, $input, $matches);

                // preg_match returns false if an error occured
                if ($result === false) {
                    throw new \Exception(preg_last_error());
                }

                if ($result !== 0) {
                    return array($matches, $this->tokenType);
                }
            }
        }

        return;
    }
}
