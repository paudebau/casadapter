<?php

Class Notices
{
    static $notices = [];
    static $log_file = null;
    static $log_fp = null;

    static function push($type, $text) {
        self::$notices[] = ['type' => $type, 'time' => gmdate('H:i:s'), 'message' => $text];
        self::log($type, $text);
    }

    static function popAll() {
        $arr = self::$notices;
        self::$notices = [];
        return $arr;
    }

    static function log($level, $text) {
        if (is_null(self::$log_fp)) {
            self::log_init();
        }
        flock (self::$log_fp, LOCK_EX);
        fwrite(self::$log_fp, '' . date('H:i:s') . ' - ' . strtoupper($level) . ' - ' . "$text\n");
        flock (self::$log_fp, LOCK_UN);
    }

    static function log_init() {
        self::$log_file = dirname(__DIR__) . '/cache/' . date('Y-m-d') . '.log';
        if (!file_exists(self::$log_file)) touch(self::$log_file);
        chmod(self::$log_file, 0644);
        self::$log_fp = fopen(self::$log_file, 'a');
    }
}

/*
class Logging
{
    private $log_dir = dirname(__DIR__) . '/cache';
    private $fps = array();

    function __construct($path=NULL) {
        if ($path) $this->log_dir .= "/$path";
        Local\Fs::mkdirs($this->log_dir);
  }

    public function lwrite($mode, $message) {
        if (!array_key_exists($mode, $this->fps)) $this->lopen($mode);
        $fp = $this->fps[$mode];
        $time = date('H:i:s');
        flock ($fp, LOCK_EX);
        fwrite($fp, "$time $message\n");
        flock ($fp, LOCK_UN);
    }

    private function lopen($mode) {
        $lfile = $this->log_dir . '/' . $mode . '_' . date('Y-m-d') . '.log';
        if (!file_exists($lfile)) touch($lfile);
        chmod($lfile, 0644);
        $this->fps[$mode] = fopen($lfile, 'a') or exit("Can't open $lfile!");
    }
}
*/
