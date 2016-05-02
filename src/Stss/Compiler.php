<?php

namespace Stss;

/**
 * Compiler.
 */
class Compiler
{
    private $parsedFiles;
    private $sourceNames;

    /**
     * [__contruct description].
     *
     * @return [type]
     */
    public function __contruct()
    {
        $this->parsedFiles = [];
        $this->sourceNames = [];
    }

    /**
     * Compile scss.
     *
     * @api
     *
     * @param Token $tree
     *
     * @return string
     */
    public function compile(Token $tree)
    {
        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, 'C');

        $out = $this->compileChildren($tree);

        setlocale(LC_NUMERIC, $locale);

        return $out;
    }

    /**
     * Compile children and throw exception if unexpected @return.
     *
     * @param Token $tree
     *
     * @throws \Exception
     */
    protected function compileChildren($tree)
    {
        $out = '';
        foreach ($tree->children as $stm) {
            $ret = $this->compileChild($stm, $out);
            if (!isset($ret)) {
                $this->throwError('@return may only be used within a function');
            }
            $out .= $ret."\n";
        }

        return $out;
    }

    /**
     * Compile child; returns a value to halt execution.
     *
     * @param array $child
     *
     * @return array
     */
    protected function compileChild($child)
    {
        $this->sourceFilename = $child->filename;
        $this->sourceLineNum = $child->sourceLine;
        $this->sourceLine = $child->source;

        if (method_exists($this, 'c'.ucfirst($child->type))) {
            return $this->{'c'.ucfirst($child->type)}($child);
        }

        $this->throwError('Unknown node: '.$child->type);
    }

    /**
     * [cRule description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cRule(Token $token)
    {
        $this->extendMap[$token->selectors] = $this->compileChildren($token);

        return '$context->query(\''.$this->selectorToXpath($token->selectors).'\')->each(function($context,$i){
            '.$this->extendMap[$token->selectors].'
        });
        ';
    }

    /**
     * [cRule description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cProperty(Token $token)
    {
        return '$context->set'.ucfirst($token->name).'('.$this->execute($token->value).');';
    }

    /**
     * [cExtend description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cExtend(Token $token)
    {
        return $this->extendMap[$token->value];
    }

    /**
     * [cComment description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cComment(Token $token)
    {
        return '/*'.$token->name.'*/';
    }

    /**
     * [cVariable description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cVariable(Token $token)
    {
        return '$context->setVar("'.$token->name.'",'.$this->execute($token->value).');';
    }

    /**
     * [cFor description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cFor(Token $token)
    {
        return '$to = '.$this->execute($token->to, false).';
            $from = '.$this->execute($token->from, false).';
            $step = '.(!empty($token->step) ? $this->execute($token->step, false) : 1).';
            '.($token->inclusive == 'to' ? '$to += ($from < $to ? 1 : -1);' : '').'
            $newNodes = array();
            $realContext = $context;
            for ($i = $from; ($from < $to ? $i < $to : $i > $to); $i = $i + $step) {
                $context = $realContext->cloneContext();
                $context->setVar("'.$token->variable.'", $i);
                '.$this->compileChildren($token).'
                $newNodes = array_merge($newNodes, $context->getNodes());
            }
            $realContext->replaceNode($newNodes);';
    }

    /**
     * [cWhile description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cWhile(Token $token)
    {
        return '$context->setVar("a","1");
            $newNodes = array();
            $realContext = $context;
            $currentVariables = $context->getVars();
            $i=0;
            while('.$this->execute($token->value, false).' && $i++<10000) {
                $context = $realContext->cloneContext();
                '.$this->compileChildren($token).'
                $realContext->setVars($context->getVars());
                $newNodes = array_merge($newNodes, $context->getNodes());
            }
            $realContext->setVars($currentVariables);

            $realContext->replaceNode($newNodes);';
    }

    /**
     * [cDo description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cDo(Token $token)
    {
        return '$context->setVar("a","1");
            $newNodes = array();
            $realContext = $context;
            $currentVariables = $context->getVars();
            $i=0;
            do {
                $context = $realContext->cloneContext();
                '.$this->compileChildren($token).'
                $realContext->setVars($context->getVars());
                $newNodes = array_merge($newNodes, $context->getNodes());
            }while('.$this->execute($token->value, false).' && $i++<10000);
            $realContext->setVars($currentVariables);

            $realContext->replaceNode($newNodes);';
    }

    /**
     * [cIf description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cIf(Token $token)
    {
        return 'if('.$this->execute($token->value, false).') {
            '.$this->compileChildren($token).'
        }';
    }

    /**
     * [cElse description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cElse(Token $token)
    {
        return 'else{
            '.$this->compileChildren($token).'
        }';
    }

    /**
     * [cElseIf description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cElseIf(Token $token)
    {
        return 'elseif('.$this->execute($token->value, false).') {
            '.$this->compileChildren($token).'
        }';
    }

    /**
     * [cMixin description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cMixin(Token $token)
    {
        return '$context->addMixin(\''.$token->name.'\',function($context){
            '.$this->compileChildren($token).'
        });';
    }

    /**
     * [cEach description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cEach(Token $token)
    {
        return '$newNodes = array();
            $realContext = $context;
            foreach('.$this->execute($token->value, false).' as $key=>$value) {
                $context = $realContext->cloneContext();
                $context->setVar(\''.$token->name.'\', $value);
                '.(isset($token->key) ? '$context->setVar(\''.$token->key.'\', $key);' : '').'
                '.$this->compileChildren($token).'
                $newNodes = array_merge($newNodes, $context->getNodes());
            }

            $realContext->replaceNode($newNodes);';
    }

    /**
     * [cInclude description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cInclude(Token $token)
    {
        return '$context->callMixin(\''.$token->name.'\');';
    }

    /**
     * [cImport description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cImport(Token $token)
    {
        if (!empty($token->html) && !empty($token->tss)) {
            return '$template = \Stss\Template::getInstance();
                $content = $template->render("'.$token->html.'", "'.$token->tss.'", null, $context);
                $context->addTemplateToNode($content);
            ';
        } elseif (!empty($token->html)) {
            return '
                $template = \Stss\Template::getInstance();
                $html = $template->getTemplateContent("'.$token->html.'");
                $newContext = new \Stss\Context($html);
                $context->addTemplateToNode($newContext);
            ';
        } elseif (!empty($token->tss)) {
            return '$template = \Stss\Template::getInstance();
                $compiled = $template->getCompiledDatas("'.$token->tss.'");
                include($compiled);
            ';
        }

        return '';
    }

    /**
     * [cDebug description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cDebug(Token $token)
    {
        return 'var_dump('.$this->execute($token->value, true).');';
    }

    /**
     * [cDebug description].
     *
     * @param Token $token
     *
     * @return string
     */
    protected function cWarn(Token $token)
    {
        return '$context->setComment('.$this->execute($token->value, true).');';
    }

    /**
     * [selectorToXpath description].
     *
     * @param string $selector
     *
     * @return string
     */
    private function selectorToXpath($selector)
    {
        $variables = array();
        for ($i = 0, $n = preg_match_all('/(?<!\\\\)#\{(.*?)\}/', $selector, $matches); $i < $n; ++$i) {
            $variables['__interpolate__'.$i] = '\'.'.$this->execute($matches[1][$i]).'.\'';
        }
        if (!empty($variables)) {
            $selector = str_replace($matches[0], array_keys($variables), $selector);
        }
        // remove spaces around operators
        $selector = preg_replace('/\s*>\s*/', '>', $selector);
        $selector = preg_replace('/\s*~\s*/', '~', $selector);
        $selector = preg_replace('/\s*\+\s*/', '+', $selector);
        $selector = preg_replace('/\s*,\s*/', ',', $selector);
        $selectors = preg_split('/\s+(?![^\[]+\])/', $selector);

        foreach ($selectors as &$selector) {
            // ,
            $selector = preg_replace('/,/', '|descendant::', $selector);
            // input:checked, :disabled, etc.
            $selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);
            // input:autocomplete, :autocomplete
            $selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);
            // input:button, input:submit, etc.
            $selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);
            // foo[id]
            $selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $selector);
            // [id]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $selector);
            // foo[id=foo]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $selector);
            // [id=foo]
            $selector = preg_replace('/^\[/', '*[', $selector);
            // div#foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $selector);
            // #foo
            $selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $selector);
            // div.foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $selector);
            // .foo
            $selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $selector);
            // div:first-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $selector);
            // div:last-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $selector);
            // :first-child
            $selector = str_replace(':first-child', '*/*[position()=1]', $selector);
            // :last-child
            $selector = str_replace(':last-child', '*/*[position()=last()]', $selector);
            // :nth-last-child
            $selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $selector);
            // div:nth-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $selector);
            // :nth-child
            $selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $selector);
            // :contains(Foo)
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $selector);
            // >
            $selector = preg_replace('/>/', '/', $selector);
            // ~
            $selector = preg_replace('/~/', '/following-sibling::', $selector);
            // +
            $selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $selector);
            $selector = str_replace(']*', ']', $selector);
            $selector = str_replace(']/*', ']', $selector);
        }

        // ' '
        $selector = implode('/descendant::', $selectors);
        $selector = 'descendant::'.$selector;
        // :scope
        $selector = preg_replace('/(((\|)?descendant::):scope)/', '.\3', $selector);
        // $element
        $subSelectors = explode(',', $selector);

        foreach ($subSelectors as $key => $subSelector) {
            $parts = explode('$', $subSelector);
            $subSelector = array_shift($parts);

            if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
                $results = $matches[0];
                $results[] = str_repeat('/..', count($results) - 2);
                $subSelector .= implode('', $results);
            }

            $subSelectors[$key] = $subSelector;
        }

        $selector = implode(',', $subSelectors);

        if (!empty($variables)) {
            $selector = str_replace(array_keys($variables), array_values($variables), $selector);
        }

        return $selector;
    }

    /**
     * Throw parser error.
     *
     * @api
     *
     * @param string $msg
     *
     * @throws \Leafo\ScssPhp\Exception\ParserException
     */
    private function throwError($msg = 'compile error')
    {
        $loc = empty($this->sourceFilename) ? "line: $this->sourceLineNum - $this->sourceLine" : "$this->sourceFilename on line $this->sourceLineNum - $this->sourceLine";

        throw new \Exception("$msg: $loc");
    }

    /**#@+
     * Default option values
     */
    const BEGIN_COMMENT = '/';
    const BEGIN_ML_COMMENT = '/*';
    const END_ML_COMMENT = '*/';
    const BEGIN_INTERPOLATION = '$';
    const BEGIN_BLOCK = '(';
    const END_BLOCK = ')';
    const DOUBLE_QUOTE = '"';
    const SINGLE_QUOTE = "'";
    const END_STATEMENT = ' ';

    /**
     * [execute description].
     *
     * @param string $source
     * @param bool   $inFunction
     *
     * @return string
     */
    private function execute($source, $inFunction = false)
    {
        $out = '';
        $statement = '';
        $srclen = strlen($source);
        $srcpos = 0;
        $inFunction = $inFunction ? 1 : 0;
        $needSep = true;

        $separator = function ($inFunction, $out, &$needSep) {
            if ($out != '' && $needSep) {
                return $inFunction ? ', ' : ' . ';
            }
            $needSep = true;

            return '';
        };

        while ($srcpos < $srclen) {
            $c = $source[$srcpos++];
            switch ($c) {
                case self::BEGIN_COMMENT:
                    if (substr($source, $srcpos - 1, strlen(self::BEGIN_ML_COMMENT))
                            === self::BEGIN_ML_COMMENT) {
                        if (ltrim($statement)) {
                            throw new \Exception('Invalid comment');
                        }
                        $out .= $c.$source[$srcpos++];
                        while ($srcpos < $srclen && substr($source, $srcpos, strlen(self::END_ML_COMMENT))
                                !== self::END_ML_COMMENT) {
                            $out .= $source[$srcpos++];
                        }
                        $srcpos += strlen(self::END_ML_COMMENT);
                    } else {
                        $statement .= $c;
                    }
                    break;
                case self::DOUBLE_QUOTE:
                case self::SINGLE_QUOTE:
                    $out .= $separator($inFunction, $out, $needSep)."'";
                    while ($srcpos < $srclen && $source[$srcpos] !== $c) {
                        $out .= $source[$srcpos++];
                    }
                    ++$srcpos;
                    $out .= "'";
                    break;
                case self::BEGIN_INTERPOLATION:
                    $out .= $separator($inFunction, $out, $needSep).'$context->getVar(\'';
                    if (preg_match('/^\$([a-z\_]([a-z0-9\-_\.]+)?)/i', substr($source, $srcpos - 1), $matches)) {
                        $out .= $matches[1];
                        $srcpos += strlen($matches[1]);
                    }
                    $out .= "')";
                    break;
                case self::BEGIN_BLOCK:
                    $out .= $separator($inFunction, $out, $needSep).'$context->callFunc(\'';
                    $out .= $statement."', array(";
                    $statement = '';
                    ++$inFunction;
                    $needSep = false;
                    break;
                case self::END_BLOCK:
                    $out .= ($statement != '' ? $separator($inFunction, $out, $needSep)."'".$statement."'))" : '))');
                    $statement = '';
                    --$inFunction;
                    break;
                case self::END_STATEMENT:
                    if (preg_match('/^([=&\*\/+\-<>]+)$/i', $statement, $matches)) {
                        $out .= ' '.$statement.' ';
                        $needSep = false;
                    } else {
                        $out .= ($statement != '' ? $separator($inFunction, $out, $needSep)."'".$statement."'" : '');
                    }
                    $statement = '';
                    break;
                default:
                    $statement .= $c;
                    break;
            }
        }

        $out .= ($statement != '' ? $separator($inFunction, $out, $needSep)."'".$statement."'" : '');

        return $out;

        $out = '';
        $pattern = preg_match_all("/([a-z_-]+)\(([^\(\)]+)?\)/i", $source, $matches, PREG_SET_ORDER);
        if ($pattern) {
            foreach ($matches as $match) {
                $out .= '$context->callFunc(\''.$match[1].'\',array('.(isset($match[2]) ? $this->execute($match[2]) : '').'))';
            }
        } else {
            $sources = preg_split('/\s+/', $source);
            foreach ($sources as $k => $s) {
                if (!empty($s)) {
                    $firstChar = $s[0];
                    $lastChar = $s[strlen($s) - 1];
                    $escapeChars = array('$', '"', "'");
                    if (!in_array($firstChar, $escapeChars) && !in_array($lastChar, $escapeChars)) {
                        $sources[$k] = preg_replace('/([^,<>=]+)/', '"$1"', $s);
                    }
                }
            }
            $out = implode(($inFunction ? ', ' : ' '), $sources);
            // variables
            $out = preg_replace_callback('/\$([a-z0-9_\-\.]+)/i', function ($matches) {
                return '$context->getVar("'.$matches[1].'")';
            }, $out);
        }

        return $source == $out ? '"'.$source.'"' : $out;
    }
}
