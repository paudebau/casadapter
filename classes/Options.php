<?php

class Options
{
    static $ns = '';
    static $options = null;
    static $options_tag = '';
    static $options_out = ['cert_path', 'cert_hash'];
    static $spec = ['version' => ['version', '2.0', '2.0'],
                    'server' => ['server', '', 'cas.example.com'],
                    'port' => ['port', '443', '443'],
                    'path' => ['path', '/cas', '/cas'],
                    'domain' => ['email domain', '', '@example.com'],
                    'admin' => ['admin user', '', 'aValidCasUser'],
                    'cert_value' => ['certificate', '', ''],
                    'cert_path' => ['cert. cache', null],
                    'cert_hash' => ['cert. hash', '']];

    static function init($ns) {
        self::$ns = $ns;
        self::init_options();
    }

    static function get_all() {
        return self::$options;
    }

    static function get($key) {
        if ($key && array_key_exists($key, self::$spec)) {
            return self::$options[$key];
        }
        return false;
    }

    static function set($key, $val) {
        self::$options[$key] = $val;
    }

    static function init_options() {
        self::$options = self::get_option('options');
        if (self::$options === false) {
            $options = [];
            foreach(self::$spec as $key => $args) {
                list($label, $default, $placeholder) = $args;
                $options[$key] = $default;
            }
            self::set_option('options', $options);
            self::$options = $options;
        }
    }

    static function update_options() {
        $options = [];
        foreach(array_keys(self::$spec) as $key) {
            if (!in_array($key, self::$options_out)) {
                $ns_key = self::ns_key($key);
                if ($key === 'cert_value') {
                    $val = str_replace("\r\n", PHP_EOL, trim($_POST[$ns_key])) . PHP_EOL;
                } else {
                    $val = $_POST[$ns_key];
                }
                self::set($key, $val);
            }
        }
        if (self::get('server')) {
            self::update_certificate();
        }
        self::set_option('options', self::$options);
    }

    static function update_certificate() {
        $server = self::get('server');
        $value = self::get('cert_value');
        $hash = md5($value);
        if ($hash !== self::get('cert_hash')) {
            $path = self::certificate_path();
            file_put_contents($path, $value);
            self::set('cert_path', $path);
            self::set('cert_hash', $hash);
        }
    }

    static function certificate_path() {
        return dirname(plugin_dir_path(__FILE__)) . '/certs/' . self::get('server') . '.pem';
    }

    static function load_certificate() {
        $path = self::certificate_path();
        return (file_exists($path)) ? file_get_contents($path) : '';
    }

    static function get_option($key) {
        return get_option(self::ns_key($key));
    }

    static function set_option($key, $val) {
        update_option(self::ns_key($key), $val);
    }

    static function ns_key($key) {
        return strtoupper(self::$ns . '_' . $key);
    }

    static function panel_settings() {
        $arr = [];
        foreach(self::$spec as $key => $args) {
            if (!in_array($key, self::$options_out)) {
                list($label, $default, $placeholder) = $args;
                $arr[$key] = ['label' => __($label, self::$ns),
                              'placeholder' => $placeholder,
                              'name' => self::ns_key($key),
                              'value' => ($value = self::get($key)) ?: $default];
            }
        }
        return $arr;
    }

    static function erase() {
        if (array_key_exists('wpdb', $GLOBALS)) {
            $query = implode(' ', ['SELECT option_id, option_name FROM',
                                   $GLOBALS['wpdb']->options,
                                   'WHERE option_name LIKE "' . self::$ns . '%"']);
            foreach ($GLOBALS['wpdb']->get_results($query) as $opt) {
                delete_site_option($opt->option_name);
                delete_option($opt->option_name);
            }
        }
    }
}
