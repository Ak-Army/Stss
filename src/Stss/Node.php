<?php

namespace Stss;

/**
 * Node class.
 * Base class for all tss nodes.
 */
class Node
{
    private $definitions = [];

    private $selectorSplitter = ',';

    /**
     * [addDef description].
     *
     * @param TokenDefinition $tokenDefinition
     */
    public function addDef(TokenDefinition $tokenDefinition)
    {
        $this->tokenDefinitions[] = $tokenDefinition;
    }

    /**
     * [getToken description].
     *
     * @param Token $token
     *
     * @return Token
     */
    public function getToken(Token $token)
    {
        foreach ($this->tokenDefinitions as $def) {
            $match = $def->match($token->source);
            if ($match) {
                $this->setTokenVariable($token, $match[0]);
                $token->type = $match[1];

                return $token;
            }
        }

        return;
    }

    private function setTokenVariable($token, $vars)
    {
        foreach ($vars as $key => $value) {
            if (!is_numeric($key)) {
                $token->{$key} = $value;
            }
        }
    }
}
