<?php

namespace Stss;

/**
 * Parser class.
 */
class Parser
{
    /**#@+
     * Default option values
     */
    const BEGIN_COMMENT = '/';
    const BEGIN_ML_COMMENT = '/*';
    const END_ML_COMMENT = '*/';
    const BEGIN_SL_COMMENT = '//';
    const BEGIN_INTERPOLATION = '#';
    const BEGIN_INTERPOLATION_BLOCK = '#{';
    const BEGIN_BLOCK = '{';
    const END_BLOCK = '}';
    const END_STATEMENT = ';';
    const DOUBLE_QUOTE = '"';
    const SINGLE_QUOTE = "'";

    /**
     * source.
     *
     * @var string
     */
    private $source;

    /**
     * The filename of the file being rendered. This is used solely for reporting errors.
     *
     * @var string
     */
    public $filename;

    /**
     * The number of the first line of the Sass template. Used for
     * reporting line numbers for errors. This is useful to set if the Sass template is embedded.
     *
     * @var int
     *
     * Defaults to 1.
     */
    private $sourceLineNum;

    /**
     * Constructor.
     * Sets parser options.
     *
     * @param array $options
     *
     * @return SassParser
     */
    public function __construct($options = array())
    {
        if (!is_array($options)) {
            throw new \Exception('options must be a array');
        }

        $defaultOptions = array(
            'sourceLineNum' => 1,
            'filename' => '',
        );

        foreach ($defaultOptions as $name => $value) {
            $this->$name = isset($options[$name]) ? $options[$name] : $value;
        }

        $this->createNodeDefinitions();
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
    public function throwParseError($msg = 'parse error')
    {
        $loc = empty($this->filename) ? "line: $this->sourceLineNum - $this->sourceLine" : "$this->filename on line $this->sourceLineNum - $this->sourceLine";

        throw new \Exception("$msg: $loc");
    }

    /**
     * Parse a tss file or tss source code and
     * returns the document tree that can then be rendered.
     * The file will be searched for in the directories specified by the
     * load_paths option.
     * If caching is enabled a cached version will be used if possible or the
     * compiled version cached if not.
     *
     * @param string $source name of source file or Sass source
     *
     * @return RootNode Root node of document tree
     */
    public function parse($source)
    {
        $this->source = $source;

        unset($source);
        $root = new Token('root');
        $root->filename = $this->filename;
        $this->buildTree($root);

        return $root;
    }

    private function createNodeDefinitions()
    {
        $this->node = new Node();

        $this->node->addDef(new TokenDefinition('/', 1, '%^/[\*|/]\s*(?<name>.*?)(\s*(\*/)?)?$%s', 'comment'));
        $this->node->addDef(new TokenDefinition('$', 1, '/^\$(?<name>[a-z\_]([a-z0-9\-_]+)?):?\s+(?<value>.+?);$/i', 'variable'));
        $this->node->addDef(new TokenDefinition(';', 1, '/^(?<name>[^\s:"]+):\s+(?<value>.*?);$/', 'property', true));

        $directioves = new TokenDefinition('@', 1, null, null);
        $directioves->addSubDef(new TokenDefinition('debug', 5, '/^(?<name>debug)\s+(?<value>.+?);$/', 'debug'));
        $directioves->addSubDef(new TokenDefinition('warn', 4, '/^(?<name>warn)\s+(?<value>.+?);$/', 'warn'));
        $directioves->addSubDef(new TokenDefinition('extend', 6, '/^extend\s+(?<value>.+);/', 'extend'));
        $directioves->addSubDef(new TokenDefinition('for', 3, '/for\s+[!\$](?<variable>\w+)\s+from\s+(?<from>.+?)\s+(?<inclusive>through|to)\s+(?<to>.+?)(?:\s+step\s+(?<step>.+))?\s*\{$/', 'for'));
        //$directioves->addSubDef(new TokenDefinition('do', 2, '/^(?<name>do)\s+(?<value>.+)\s*\{$/', 'do'));
        $directioves->addSubDef(new TokenDefinition('while', 5, '/^(?<name>while)\s+(?<value>.+)\s*\{$/', 'while'));
        $directioves->addSubDef(new TokenDefinition('if', 2, '/^(?<name>if)\s+(?<value>.+)\s*\{$/', 'if'));
        $directioves->addSubDef(new TokenDefinition('else', 4, '/^(?<name>else)\s+\{$/', 'else'));
        $directioves->addSubDef(new TokenDefinition('elseif', 6, '/^(?<name>elseif)\s+(?<value>.+)\s*\{$/', 'elseIf'));
        $directioves->addSubDef(new TokenDefinition('mixin', 5, '/^mixin\s+(?<name>[^\s]+)\s*\{$/', 'mixin'));
        $directioves->addSubDef(new TokenDefinition('include', 7, '/^include\s+(?<name>.+);$/', 'include'));
        $directioves->addSubDef(new TokenDefinition('each', 4, '/^each\s+(?<name>[^,]+)(,\s*(?<key>.+))?\s+in\s+(?<value>[^\s]+)\s*\{$/', 'each'));
        $directioves->addSubDef(new TokenDefinition('import', 6, '/^import\s+((?<html>.*\.html)\s*)?(?<tss>.*\.tss)?;$/', 'import'));
        $this->node->addDef($directioves);

        $this->node->addDef(new TokenDefinition('{', 1, '/^(?<selectors>.+?)\s*\{$/', 'rule', true));
    }

    /**
     * Builds a parse tree under the parent node.
     * Called recursivly until the source is parsed.
     *
     * @param Node the node
     */
    private function buildTree($parent)
    {
        $node = $this->getNode($parent);
        while (is_object($node) && $node->isChildOf($parent)) {
            $parent->addChild($node);
            $node = $this->buildTree($node);
        }
        if (empty($node) && $this->sourceLine !== '}') {
            $this->throwParseError('Unknown Node');
        }

        return $node;
    }

    /**
     * Creates and returns the next SassNode.
     * The tpye of SassNode depends on the content of the SassToken.
     *
     * @return SassNode a SassNode of the appropriate type. Null when no more
     *                  source to parse.
     */
    private function getNode($node)
    {
        $token = $this->getToken();

        if ($token) {
            return $this->node->getToken($token);
        }

        return;
    }

    /**
     * Returns a token object that contains the next source statement and
     * meta data about it.
     *
     * @return object
     */
    private function getToken()
    {
        static $srcpos = 0; // current position in the source stream
        static $srclen; // the length of the source stream

        $statement = '';
        $token = null;
        if (empty($srclen)) {
            $srclen = strlen($this->source);
        }
        while (is_null($token) && $srcpos < $srclen) {
            $c = $this->source[$srcpos++];
            switch ($c) {
                case self::BEGIN_COMMENT:
                    if (substr($this->source, $srcpos - 1, strlen(self::BEGIN_SL_COMMENT)) === self::BEGIN_SL_COMMENT) {
                        $statement = self::BEGIN_SL_COMMENT;
                        while ($this->source[$srcpos++] !== "\n") {
                            $statement .= $this->source[$srcpos];
                        }
                        $token = $this->createToken($statement);
                    } elseif (substr($this->source, $srcpos - 1, strlen(self::BEGIN_ML_COMMENT))
                            === self::BEGIN_ML_COMMENT) {
                        if (ltrim($statement)) {
                            throw new \Exception('Invalid comment');
                        }
                        $statement .= $c.$this->source[$srcpos++];
                        while (substr($this->source, $srcpos, strlen(self::END_ML_COMMENT))
                                !== self::END_ML_COMMENT) {
                            $statement .= $this->source[$srcpos++];
                        }
                        $srcpos += strlen(self::END_ML_COMMENT);
                        $token = $this->createToken($statement.self::END_ML_COMMENT);
                    } else {
                        $statement .= $c;
                    }
                    break;
                case self::DOUBLE_QUOTE:
                case self::SINGLE_QUOTE:
                    $statement .= $c;
                    while ($this->source[$srcpos] !== $c) {
                        $statement .= $this->source[$srcpos++];
                    }
                    $statement .= $this->source[$srcpos++];
                    break;
                case self::BEGIN_INTERPOLATION:
                    $statement .= $c;
                    if (substr($this->source, $srcpos - 1, strlen(self::BEGIN_INTERPOLATION_BLOCK))
                            === self::BEGIN_INTERPOLATION_BLOCK) {
                        while ($this->source[$srcpos] !== self::END_BLOCK) {
                            $statement .= $this->source[$srcpos++];
                        }
                        $statement .= $this->source[$srcpos++];
                    }
                    break;
                case self::BEGIN_BLOCK:
                case self::END_BLOCK:
                case self::END_STATEMENT:
                    $token = $this->createToken($statement.$c);
                    if (is_null($token)) {
                        $statement = '';
                    }
                    break;
                default:
                    $statement .= $c;
                    break;
            }
        }

        if (is_null($token)) {
            $srclen = $srcpos = 0;
        }

        return $token;
    }

    /**
     * Returns an object that contains the source statement and meta data about
     * it.
     * If the statement is just and end block we update the meta data and return null.
     *
     * @param string source statement
     *
     * @return SassToken
     */
    private function createToken($statement)
    {
        static $level = 1;

        $this->sourceLineNum += substr_count($statement, "\n");
        $statement = trim($statement);

        $last = substr($statement, -1);

        $this->sourceLine = $statement;

        $token = null;
        if ($statement[0] !== '}') {
            $token = new Token('T_UNDEF');
            $token->level = $level;
            $token->sourceLine = $this->sourceLineNum;
            $token->source = $statement;
            $token->filename = $this->filename;
            $token->selectors = [];
            $token->comments = [];
            $token->parent = null;
        }
        $level += ($last === self::BEGIN_BLOCK ? 1 : ($last === self::END_BLOCK ? -1 : 0));

        return $token;
    }
}
