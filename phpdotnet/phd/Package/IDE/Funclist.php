<?php
namespace phpdotnet\phd;

class Package_IDE_Funclist extends Format {
    protected $elementmap   = array(
        'refname'               => 'format_refname',
        'set'                   => 'format_set',
    );
    protected $textmap      = array(
        'refname'               => 'format_refname_text',
    );

    protected $isFunctionRefSet = false;
    protected $isRefname = false;
    protected $buffer = "";

    public function __construct(
        Config $config,
        OutputHandler $outputHandler
    ) {
        parent::__construct($config, $outputHandler);
        $this->registerFormatName("IDE-Funclist");
        $this->setExt($this->config->ext === null ? ".txt" : $this->config->ext);
    }

    public function createLink($for, &$desc = null, $type = Format::SDESC) {}
    public function UNDEF($open, $name, $attrs, $props) {}
    public function TEXT($value) {}
    public function CDATA($value) {}
    public function transformFromMap($open, $tag, $name, $attrs, $props) {}

    public function appendData($data) {
        if ($data && $this->isFunctionRefSet && $this->isRefname) {
            $this->buffer .= $data . "\n";
        }
    }

    public function update($event, $value = null) {
        switch($event) {
        case Render::STANDALONE:
            $this->registerElementMap($this->elementmap);
            $this->registerTextMap($this->textmap);
            break;
       case Render::FINALIZE:
            $filename = $this->config->outputDir . strtolower($this->getFormatName()) . $this->getExt();
            file_put_contents($filename, $this->buffer);
            break;
        case Render::VERBOSE:
            $this->outputHandler->v("Starting %s rendering", $this->getFormatName(), VERBOSE_FORMAT_RENDERING);
            break;
        }
    }

    public function format_set($open, $name, $attrs, $props) {
        if (isset($attrs[Reader::XMLNS_XML]["id"]) && $attrs[Reader::XMLNS_XML]["id"] == "funcref") {
            $this->isFunctionRefSet = $open;
        }
        return "";
    }

    public function format_refname($open, $name, $attrs, $props) {
        $this->isRefname = $open;
        return "";
    }

    public function format_refname_text($value, $tag) {
        if (false !== strpos(trim($value), ' ')) {
            return;
        }
        return str_replace(array("::", "->", "()"), array(".", ".", ""), trim($value));
    }

}
