<?php
namespace phpdotnet\phd;

class Config
{
    const VERSION = '@phd_version@';

    private static $optionArrayDefault = array(
        'output_format'     => array(),
        'no_index'          => false,
        'force_index'       => false,
        'no_toc'            => false,
        'xml_root'          => '.',
        'xml_file'          => './.manual.xml',
        'history_file'      => './fileModHistory.php',
        'lang_dir'          => './',
        'language'          => 'en',
        'fallback_language' => 'en',
        'verbose'           => VERBOSE_DEFAULT,
        'date_format'       => 'H:i:s',
        'render_ids'        => array(
        ),
        'skip_ids'          => array(
        ),
        'color_output'      => true,
        'output_dir'        => './output/',
        'output_filename'   => '',
        'php_error_output'  => STDERR,
        'php_error_color'   => '01;31', // Red
        'user_error_output' => STDERR,
        'user_error_color'  => '01;33', // Yellow
        'phd_info_output'   => STDOUT,
        'phd_info_color'    => '01;32', // Green
        'phd_warning_output' => STDOUT,
        'phd_warning_color' => '01;35', // Magenta
        'highlighter'       => 'phpdotnet\\phd\\Highlighter',
        'package'           => array(
            'Generic',
        ),
        'css'               => array(),
        'process_xincludes' => false,
        'ext'               => null,
        'package_dirs'      => array(__INSTALLDIR__),
        'saveconfig'        => false,
        'quit'              => false,
        'indexcache'        => '',
        'memoryindex'       => '',
    );

    private static $optionArray = null;

    public static function init(array $a) {
        // Override any defaults due to operating system constraints
        // and copy defaults to working optionArray
        if ($newInit = is_null(self::$optionArray)) {

            // By default, Windows does not support colors on the console.
            // ANSICON by Jason Hood (http://adoxa.110mb.com/ansicon/index.html)
            // can be used to provide colors at the console on Windows.
            // Color output can still be enabled via the command line parameters --color
            if('WIN' === strtoupper(substr(PHP_OS, 0, 3))) {
        	self::$optionArrayDefault['color_output'] = false;
            }

            self::$optionArray = self::$optionArrayDefault;
        }

        // now merge other options
        self::$optionArray = array_merge(self::$optionArray, (array)$a);

        if ($newInit) {
            // Always set saveconfig to false for a new initialization, even after restoring
            // a save configuration. This allows additional options to be added at the
            // command line without them being automatically saved.
            self::$optionArray['saveconfig'] = false;

            // As well as the quit option.
            self::$optionArray['quit'] = false;

            // Set the error reporting level to the restored level.
            error_reporting($GLOBALS['olderrrep'] | self::$optionArray['verbose']);
        }
    }

    public static function getAllFiltered() {
        $retval = self::$optionArray;
        return self::exportable($retval);
    }
    public static function exportable($val) {
        foreach($val as $k => &$opt) {
            if (is_array($opt)) {
                $opt = self::exportable($opt);
                continue;
            }
            if (is_resource($opt)) {
                unset($val[$k]);
            }
        }
        return $val;
    }
    /**
     * Maps static function calls to config option setter/getters.
     *
     * To set an option, call "Config::setOptionname($value)".
     * To retrieve an option, call "Config::optionname()".
     *
     * It is also possible, but deprecated, to call
     * "Config::set_optionname($value)".
     *
     * @param string $name   Name of called function
     * @param array  $params Array of function parameters
     *
     * @return mixed Config value that was requested or set.
     */
    public static function __callStatic($name, $params)
    {
        $name = strtolower($name); // FC if this becomes case-sensitive

        if (strncmp($name, 'set', 3) === 0) {
            $name = substr($name, 3);
            if ($name[0] === '_') {
                $name = substr($name, 1);
            }
            if (strlen($name) < 1 || count($params) !== 1) { // assert
                trigger_error('Misuse of config option setter', E_USER_ERROR);
            }
            self::$optionArray[$name] = $params[0];
            // no return, intentional
        }
        return isset(self::$optionArray[$name])
            ? self::$optionArray[$name]
            : NULL;
    }

    public function __call($name, $params) {
        return self::__callStatic($name, $params);
    }

    public static function getSupportedPackages() {
        $packageList = array();
        foreach(Config::package_dirs() as $dir) {
            foreach (glob($dir . "/phpdotnet/phd/Package/*", GLOB_ONLYDIR) as $item) {
                $baseitem = basename($item);
                if ($baseitem[0] != '.') {
                    $packageList[] = $baseitem;
                }
            }
        }
        return $packageList;
    }

    public static function setColor_output($color_output)
    {
        // Disable colored output if the terminal doesn't support colors
        if ($color_output && function_exists('posix_isatty')) {
            if (!posix_isatty(Config::phd_info_output())) {
                Config::setPhd_info_color(false);
            }
            if (!posix_isatty(Config::phd_warning_output())) {
                Config::setPhd_warning_color(false);
            }
            if (!posix_isatty(Config::php_error_output())) {
                Config::setPhd_error_color(false);
            }
            if (!posix_isatty(Config::user_error_output())) {
                Config::setUser_error_color(false);
            }
        }
        self::$optionArray['color_output'] = $color_output;
    }

    public static function set_color_output()
    {
        trigger_error('Use setColor_output()', E_USER_DEPRECATED);
    }

    public static function copyright() {
        return sprintf('Copyright(c) 2007-%s The PHP Documentation Group', date('Y'));
    }

    /**
     * Checks if indexing is needed.
     *
     * This is determined the following way:
     * 0. If no index file exists, indexing is required.
     * 1. If the config option --no-index is supplied, nothing is indexed
     * 2. If the config option --force-index is supplied, indexing is required
     * 3. If no option is given, the file modification time of the index and
     *    the manual docbook file are compared. If the index is older than
     *    the docbook file, indexing will be done.
     *
     * @return boolean True if indexing is required.
     */
    public function requiresIndexing(): bool {
        if (! $this->indexcache()) {
            $indexfile = $this->output_dir() . 'index.sqlite';
            if (!\file_exists($indexfile)) {
                return true;
            }
        }

        if ($this->no_index()) {
            return false;
        }

        if ($this->force_index()) {
            return true;
        }

        if ($this->indexcache()->getIndexingTimeCount() === 0) {
            return true;
        }

        $xmlLastModification = \filemtime($this->xml_file());
        if ($this->indexcache()->getIndexingTime() > $xmlLastModification) {
            return false;
        }
        return true;
    }
}
