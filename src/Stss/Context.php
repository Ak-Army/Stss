<?php

namespace Stss;

/**
 * Defines the context that the parser is operating in and so allows variables
 * to be scoped.
 * A new context is created for Mixins and imported files.
 */
class Context implements \Countable, \IteratorAggregate
{
    /**
     * @var \DOMDocument|null
     */
    private $document;

    /**
     * @var \DOMElement[]
     */
    private $nodes = array();

    /**
     * Whether the Crawler contains HTML or XML content (used when converting CSS to XPath).
     *
     * @var bool
     */
    private $isHtml = true;

    /**
     * @var array mixins defined in this context
     */
    private $mixins = array();
    /**
     * @var array variables defined in this context
     */
    private $variables = array();
    /**
     * @var array variables defined in this context
     */
    private $functions = array();

    /**
     * @param mixed $node      A Node to use as the base for the crawling
     * @param array $variables
     * @param array $mixins
     */
    public function __construct($node, $variables = array(), $mixins = array())
    {
        $this->variables = $variables;
        $this->mixins = $mixins;
        $this->add($node);

        $this->prepareFunctions();
    }

    /**
     * [addFunc description].
     *
     * @param string   $name
     * @param callable $function
     */
    public function addFunc($name, $function)
    {
        $this->functions[$name] = $function;
    }

    /**
     * [callFunc description].
     *
     * @param string $name
     * @param array  $args
     *
     * @return mixed
     */
    public function callFunc($name, $args)
    {
        if (isset($this->functions[$name])) {
            return call_user_func_array($this->functions[$name], $args);
        }

        return;
    }

    /**
     * [addMixin description].
     *
     * @param string   $name
     * @param \Closure $closure
     */
    public function addMixin($name, \Closure $closure)
    {
        $this->mixins[$name] = $closure;
    }

    /**
     * [getMixins description].
     *
     * @return array
     */
    public function getMixins()
    {
        return $this->mixins;
    }

    /**
     * [callMixin description].
     *
     * @param string $name
     */
    public function callMixin($name)
    {
        if (isset($this->mixins[$name])) {
            $this->mixins[$name]($this);
        } else {
            throw new \Exception('Undefined mixin: '.$name);
        }
    }

    /**
     * [cloneContext description].
     *
     * @return Context
     */
    public function cloneContext()
    {
        $newNodes = [];
        foreach ($this->nodes as $key => $node) {
            $newNodes[$key] = $node->cloneNode(true);
        }

        return $this->createSubCrawler($newNodes);
    }

    /**
     * [getVars description].
     *
     * @return mixed
     */
    public function getVars()
    {
        return $this->variables;
    }

    /**
     * [setVars description].
     *
     * @param mixed $vars
     */
    public function setVars($vars)
    {
        $this->variables = $vars;
    }

    /**
     * Returns a variable defined in this context.
     *
     * @param string $name    of variable to return
     * @param mixed  $default default value
     *
     * @return mixed
     */
    public function getVar($name, $default = null)
    {
        $array = $this->variables;
        $keys = explode('.', $name);
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Returns a value indicating if the variable exists in this context.
     *
     * @param string $name of variable to test
     *
     * @return bool true if the variable exists in this context, false if not
     */
    public function hasVar($name)
    {
        $keys = explode('.', $name);
        $array = $this->variables;
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                $array = $array[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns a value indicating if the variable exists in this context.
     *
     * @param string $name of variable to test
     *
     * @return bool true if the variable exists in this context, false if not
     */
    public function removeVar($name)
    {
        if (isset($this->variables[$name])) {
            unset($this->variables[$name]);

            return true;
        }

        return false;
    }

    /**
     * Sets a variable to the given value.
     *
     * @param string $name  of variable
     * @param mixed  $value of variable
     *
     * @return self
     */
    public function setVar($name, $value)
    {
        $at = &$this->variables;
        $keys = explode('.', $name);
        while (count($keys) > 0) {
            if (count($keys) === 1) {
                if (is_array($at)) {
                    $at[array_shift($keys)] = $value;
                } else {
                    throw new \RuntimeException("Can not set value at this path ($path) because is not array.");
                }
            } else {
                $key = array_shift($keys);
                if (!isset($at[$key])) {
                    $at[$key] = array();
                }
                $at = &$at[$key];
            }
        }

        return $this;
    }

    /**
     * [setContent description].
     *
     * @param mixed $content
     */
    public function setContent($content)
    {
        $node = $this->getNode(0);
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $node) {
                if ($node->nodeType === XML_TEXT_NODE) {
                    $node->nodeValue = $content;
                    break;
                }
            }
        } else {
            $node->nodeValue = $content;
        }

        //$node->replaceChild(new \DOMText($content),$node->firstChild);
    }

    /**
     * [setComment description].
     *
     * @param mixed $content
     */
    public function setComment($content)
    {
        $content = str_replace('--', '- -', $content);
        $node = $this->getNode(0);
        if ($node->firstChild) {
            $node->insertBefore(new \DOMComment($content), $node->firstChild);
        } else {
            $node->appendChild(new \DOMComment($content));
        }
    }

    /**
     * [replaceNode description].
     *
     * @param array $nodes
     */
    public function replaceNode($nodes)
    {
        $currentNode = $this->getNode(0);
        $parent = $currentNode->parentNode;

        foreach ($nodes as $node) {
            $parent->insertBefore($node, $currentNode);
        }
        $parent->removeChild($currentNode);
    }

    /**
     * [addTemplateToNode description].
     *
     * @param Context $context
     */
    public function addTemplateToNode(Context $context)
    {
        $newNode = $context->query('descendant-or-self::template')->getNode(0);
        if (!$newNode) {
            $newNode = $context->query('descendant-or-self::body')->getNode(0);
        }
        if (!$newNode) {
            throw new \Exception('Empty template!!!');
        }
        $nodes = $newNode->childNodes;
        foreach ($this->nodes as $node) {
            foreach ($nodes as $child) {
                $node->appendChild($this->document->importNode($child, true));
            }
        }
    }

    /**
     * Removes all the nodes.
     */
    public function clear()
    {
        $this->nodes = array();
        $this->document = null;
        $this->variables = array();
        $this->mixins = array();
    }

    /**
     * Adds a node to the current list of nodes.
     *
     * This method uses the appropriate specialized add*() method based
     * on the type of the argument.
     *
     * @param \DOMNodeList|\DOMNode|array|string|null $node A node
     *
     * @throws \InvalidArgumentException When node is not the expected type.
     */
    public function add($node)
    {
        if ($node instanceof \DOMNodeList) {
            $this->addNodeList($node);
        } elseif ($node instanceof \DOMNode) {
            $this->addNode($node);
        } elseif (is_array($node)) {
            $this->addNodes($node);
        } elseif (is_string($node)) {
            $this->addContent($node);
        } elseif (null !== $node) {
            throw new \InvalidArgumentException(sprintf('Expecting a DOMNodeList or DOMNode instance, an array, a string, or null, but got "%s".', is_object($node) ? get_class($node) : gettype($node)));
        }
    }

    /**
     * Adds HTML/XML content.
     *
     * If the charset is not set via the content type, it is assumed
     * to be ISO-8859-1, which is the default charset defined by the
     * HTTP 1.1 specification.
     *
     * @param string      $content A string to parse as HTML/XML
     * @param null|string $type    The content type of the string
     */
    public function addContent($content, $type = null)
    {
        if (empty($type)) {
            $type = 0 === strpos($content, '<?xml') ? 'application/xml' : 'text/html';
        }

        // DOM only for HTML/XML content
        if (!preg_match('/(x|ht)ml/i', $type, $xmlMatches)) {
            return;
        }

        $charset = null;
        if (false !== $pos = stripos($type, 'charset=')) {
            $charset = substr($type, $pos + 8);
            if (false !== $pos = strpos($charset, ';')) {
                $charset = substr($charset, 0, $pos);
            }
        }

        // http://www.w3.org/TR/encoding/#encodings
        // http://www.w3.org/TR/REC-xml/#NT-EncName
        if (null === $charset &&
            preg_match('/\<meta[^\>]+charset *= *["\']?([a-zA-Z\-0-9_:.]+)/i', $content, $matches)) {
            $charset = $matches[1];
        }

        if (null === $charset) {
            $charset = 'ISO-8859-1';
        }

        if ('x' === $xmlMatches[1]) {
            $this->addXmlContent($content, $charset);
        } else {
            $this->addHtmlContent($content, $charset);
        }
    }

    /**
     * Adds an HTML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     *
     * @param string $content The HTML content
     * @param string $charset The charset
     */
    public function addHtmlContent($content, $charset = 'UTF-8')
    {
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        $dom = new \DOMDocument('1.0', $charset);
        $dom->validateOnParse = true;

        set_error_handler(function () {
            throw new \Exception();
        });

        try {
            // Convert charset to HTML-entities to work around bugs in DOMDocument::loadHTML()
            $content = mb_convert_encoding($content, 'HTML-ENTITIES', $charset);
        } catch (\Exception $e) {
        }

        restore_error_handler();

        if ('' !== trim($content)) {
            @$dom->loadHTML($content);
        }

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        $this->addDocument($dom);
    }

    /**
     * Adds an XML content to the list of nodes.
     *
     * The libxml errors are disabled when the content is parsed.
     *
     * If you want to get parsing errors, be sure to enable
     * internal errors via libxml_use_internal_errors(true)
     * and then, get the errors via libxml_get_errors(). Be
     * sure to clear errors with libxml_clear_errors() afterward.
     *
     * @param string $content The XML content
     * @param string $charset The charset
     * @param int    $options Bitwise OR of the libxml option constants
     *                        LIBXML_PARSEHUGE is dangerous, see
     *                        http://symfony.com/blog/security-release-symfony-2-0-17-released
     */
    public function addXmlContent($content, $charset = 'UTF-8', $options = LIBXML_NONET)
    {
        // remove the default namespace if it's the only namespace to make XPath expressions simpler
        if (!preg_match('/xmlns:/', $content)) {
            $content = str_replace('xmlns', 'ns', $content);
        }

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        $dom = new \DOMDocument('1.0', $charset);
        $dom->validateOnParse = true;

        if ('' !== trim($content)) {
            @$dom->loadXML($content, $options);
        }

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        $this->addDocument($dom);

        $this->isHtml = false;
    }

    /**
     * Adds a \DOMDocument to the list of nodes.
     *
     * @param \DOMDocument $dom A \DOMDocument instance
     */
    public function addDocument(\DOMDocument $dom)
    {
        if ($dom->documentElement) {
            $this->addNode($dom->documentElement);
        }
    }

    /**
     * Adds a \DOMNodeList to the list of nodes.
     *
     * @param \DOMNodeList $nodes A \DOMNodeList instance
     */
    public function addNodeList(\DOMNodeList $nodes)
    {
        foreach ($nodes as $node) {
            if ($node instanceof \DOMNode) {
                $this->addNode($node);
            }
        }
    }

    /**
     * Adds an array of \DOMNode instances to the list of nodes.
     *
     * @param \DOMNode[] $nodes An array of \DOMNode instances
     */
    public function addNodes(array $nodes)
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }
    }

    /**
     * Adds a \DOMNode instance to the list of nodes.
     *
     * @param \DOMNode $node A \DOMNode instance
     *
     * @return bool
     */
    public function addNode(\DOMNode $node)
    {
        if ($node instanceof \DOMDocument) {
            $node = $node->documentElement;
        }
        if (null !== $this->document && $this->document !== $node->ownerDocument) {
            throw new \InvalidArgumentException('Attaching DOM nodes from multiple documents in the same crawler is forbidden.');
        }

        if (null === $this->document) {
            $this->document = $node->ownerDocument;
        }

        // Don't add duplicate nodes in the Crawler
        if (in_array($node, $this->nodes, true)) {
            return false;
        }

        $this->nodes[] = $node;

        return true;
    }

    /**
     * Calls an anonymous function on each node of the list.
     *
     * The anonymous function receives the position and the node wrapped
     * in a Crawler instance as arguments.
     *
     * Example:
     *
     *     $crawler->filter('h1')->each(function ($node, $i) {
     *         return $node->text();
     *     });
     *
     * @param \Closure $closure An anonymous function
     */
    public function each(\Closure $closure)
    {
        foreach ($this->nodes as $i => $node) {
            $subNode = $this->createSubCrawler($node);
            $closure($subNode, $i);
            $this->nodes[$i] = $subNode->getNode(0);
        }
    }

    /**
     * Returns the attribute value of the first node of the list.
     *
     * @param string $attribute The attribute name
     *
     * @return string|null The attribute value or null if the attribute does not exist
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function getAttr($attribute)
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }
        $node = $this->getNode(0);

        return $node->hasAttribute($attribute) ? $node->getAttribute($attribute) : null;
    }

    /**
     * Returns the attribute value of the first node of the list.
     *
     * @param string $attribute The attribute name
     * @param string $value     The attribute value
     *
     * @return bool
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function setAttr($attribute, $value)
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $node = $this->getNode(0);

        return $node->setAttribute($attribute, $value);
    }

    /**
     * Returns the node name of the first node of the list.
     *
     * @return string The node name
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function getNodeName()
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->getNode(0)->nodeName;
    }

    /**
     * Returns the node value of the first node of the list.
     *
     * @return string The node value
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function getText()
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        return $this->getNode(0)->nodeValue;
    }

    /**
     * Returns the first node of the list as HTML.
     *
     * @return string The node html
     *
     * @throws \InvalidArgumentException When current node is empty
     */
    public function getHtml()
    {
        if (!$this->nodes) {
            throw new \InvalidArgumentException('The current node list is empty.');
        }

        $nodes = $this->query('descendant-or-self::template')->getNode(0);
        if (!$nodes) {
            $nodes = $this->query('descendant-or-self::body')->getNode(0);
        }
        if (!$nodes) {
            $nodes = $this->getNode(0);
        }
        $html = '';
        foreach ($nodes->childNodes as $child) {
            $html .= $child->ownerDocument->saveXML($child, LIBXML_NOEMPTYTAG);
        }
        $html = str_replace(['></img>', '></br>', '></meta>', '></base>', '></link>', '></hr>', '></input>'], ' />', $html);

        return $html;
    }
    /**
     * Filters the list of nodes with an XPath expression.
     *
     * The XPath expression should already be processed to apply it in the context of each node.
     *
     * @param string $xpath
     *
     * @return Crawler
     */
    public function query($xpath)
    {
        $crawler = $this->createSubCrawler(null);

        foreach ($this->nodes as $node) {
            $domxpath = new \DOMXPath($node->ownerDocument);
            $crawler->add($domxpath->query($xpath, $node));
        }

        return $crawler;
    }

    /**
     * @param int $position
     *
     * @return \DOMElement|null
     */
    public function getNode($position)
    {
        if (isset($this->nodes[$position])) {
            return $this->nodes[$position];
        }
    }

    /**
     * @return array
     */
    public function getNodes()
    {
        return $this->nodes;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->nodes);
    }

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->nodes);
    }

    /**
     * Creates a crawler for some subnodes.
     *
     * @param \DOMElement|\DOMElement[]|\DOMNodeList|null $nodes
     *
     * @return static
     */
    private function createSubCrawler($nodes)
    {
        $crawler = new self($nodes, $this->variables, $this->mixins);
        //$crawler->isHtml = $this->isHtml;
        //$crawler->document = $this->document;

        return $crawler;
    }

    /**
     * [prepareFunctions description].
     */
    private function prepareFunctions()
    {
        $self = $this;
        $this->addFunc('sin', 'sin');
        $this->addFunc('cos', 'cos');
        $this->addFunc('tn', 'tan');
        $this->addFunc('asin', 'asin');
        $this->addFunc('acos', 'acos');
        $this->addFunc('atn', 'atan');
        $this->addFunc('min', 'min');
        $this->addFunc('max', 'max');
        $this->addFunc('avg', function ($arg1, $arg2) {
            return ($arg1 + $arg2) / 2;
        });
        $this->addFunc('attr', function ($attr) use ($self) {
            return $self->getAttr($attr);
        });
        $this->addFunc('content', function () use ($self) {
            return $self->getText();
        });
    }
}
