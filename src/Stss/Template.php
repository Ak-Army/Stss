<?php

namespace Stss;

/**
 *
 */
class Template
{
    const CACHE = true;
    const CACHE_LOCATION = './tss-cache';
    const TSS_LOCATION = './tss-templates';
    const TEMPLATE_LOCATION = './view';

    /**
     * cache.
     *
     * @var bool Whether parsed Tss files should be cached, allowing greater speed.
     *
     * Defaults to true.
     */
    private $cache;

    /**
     * cacheLocation.
     *
     * @var string The path where the cached sassc files should be written to.
     *
     * Defaults to './tss-cache'.
     */
    private $cacheLocation;

    /**
     * tssLocation.
     *
     * @var string The path where TSS output should be found
     *
     * Defaults to './tss'.
     */
    private $tssLocation;

    /**
     * debug_info:.
     *
     * @var bool When true the line number and file where a selector is defined
     *           is emitted into the compiled TSS in a format that can be understood by the
     *           Disabled when using the compressed output style.
     *
     * Defaults to false.
     *
     * @see style
     */
    private $debug;

    /**
     * templateLocation.
     *
     * @var array An array of filesystem paths which should be searched for
     *            TSS templates imported with the @import directive.
     *
     * Defaults to './tss-templates'.
     */
    private $templateLocation;

    /**
     * parserOptions.
     *
     * @var array
     */
    private $parserOptions = array();

    private static $instance;

    /**
     * [__construct description].
     *
     * @param array $options
     */
    private function __construct($options = array())
    {
        if (!is_array($options)) {
            throw new \Exception('options must be a array');
        }

        $defaultOptions = array(
            'cache' => self::CACHE,
            'cacheLocation' => self::CACHE_LOCATION,
            'tssLocation' => self::TSS_LOCATION,
            'templateLocation' => self::TEMPLATE_LOCATION,
        );

        foreach ($defaultOptions as $name => $value) {
            $this->$name = isset($options[$name]) ? $options[$name] : $value;
        }

        self::$instance = $this;
    }

    /**
     * [getInstance description].
     *
     * @param array $options
     *
     * @return self
     */
    public static function getInstance($options = array())
    {
        if (self::$instance) {
            return self::$instance;
        } else {
            return new self($options);
        }
    }

    /**
     * Parse a tss file or tss source code and
     * returns the document tree that can then be rendered.
     * The file will be searched for in the directories specified by the
     * load_paths option.
     * If caching is enabled a cached version will be used if possible or the
     * compiled version cached if not.
     *
     * @param string $source name of source file or tss source
     *
     * @return RootNode Root node of document tree
     */
    public function parse($source)
    {
        $this->filename = File::getFile($source, $this->tssLocation);
        if ($this->filename) {
            $source = file_get_contents($this->filename);
        } else {
            $this->filename = md5($source);
        }

        if ($this->cache) {
            $cached = File::getCachedFile($this->filename, $this->cacheLocation);
            if ($cached !== false) {
                return $cached;
            }
        }

        $parser = new Parser($this->parserOptions);
        $tree = $parser->parse($source);

        if ($this->cache) {
            File::setCachedFile(serialize($tree), $this->filename, $this->cacheLocation);
        }

        return $tree;
    }

    /**
     * [compile description].
     *
     * @param string $tssc
     *
     * @return string
     */
    public function compile($tssc)
    {
        if ($this->cache) {
            $cached = File::getCachedFile($this->filename, $this->cacheLocation, 'compiled.php');
            if ($cached !== false) {
                return $cached;
            }
        }

        $compiler = new Compiler();
        $compiled = '<?php'."\n\n".$compiler->compile($tssc);

        return File::setCachedFile($compiled, $this->filename, $this->cacheLocation, 'compiled.php');
    }

    /**
     * [getCompiledDatas description].
     *
     * @param string $tss
     *
     * @return string
     */
    public function getCompiledDatas($tss)
    {
        $tssc = $this->parse($tss);

        $compiled = $this->compile($tssc);

        return $compiled;
    }

    /**
     * [getTemplateContent description].
     *
     * @param string $template
     *
     * @return string
     */
    public function getTemplateContent($template)
    {
        $file = File::getFile($template, $this->templateLocation, null);
        if ($file) {
            return file_get_contents($file);
        }

        return $template;
    }

    /**
     * [render description].
     *
     * @param string     $template
     * @param string     $tss
     * @param array|null $datas
     * @param Context    $context
     *
     * @return string
     */
    public function render($template, $tss, $datas, Context $context = null)
    {
        $compiled = $this->getCompiledDatas($tss);

        $template = $this->getTemplateContent($template);
        if (!$context) {
            $context = new Context($template, $datas);
        } else {
            $context = new Context($template, $context->getVars(), $context->getMixins());
        }

        include $compiled;

        return $context;
    }
}
