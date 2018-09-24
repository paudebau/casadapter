<?php

class CasUsers
{
    const UNCHECKED = 0;
    const CHECKED = 1;
    const ADMIN = 2;

    static function init() {
        if (!Table::init()) {
            Notices::push('error', '(CasUsers) Table initialization failed');
            return false;
        }
        return true;
    }

    static function record($uid, $is_admin) {
        if (self::exists($uid)) {
            if (Table::get_status($uid) === self::UNCHECKED) {
                Table::set_status($uid, self::CHECKED);
            }
        } else {
            Table::insert($uid, $is_admin ? self::ADMIN : self::UNCHECKED);
        }
    }

    static function scramble($user, $force=false) {
        if ($force || self::exists($user->user_login)) {
            Table::set_status($user->user_login, self::CHECKED);
            wp_set_password(self::randomize_password(), $user->id);
        }
    }

    static function randomize_password($seed=null) {
        if ($seed && count($seed) >= 8) {
            return hash($seed, time());
        } else {
            return substr(md5(uniqid(microtime())), 0, 8);
        }
    }

    static function consistent() {
        return; // vérifie qu'il existe (au moins) un compte Admin CAS + un compte Admin local
    }

    static function exists($uid) {
        return !is_null(Table::get_by_login($uid));
        // $res = Table::get_by_login($uid);
        // Notices::push('info', "USER $uid? " . json_encode([$res, !empty($res)]));
        // return !is_null($res);  // FIXME
    }
}

class Table
{
    static $table = 'cas_users';
    const ISO_DATE_TIME = '0000-00-00 00:00:00';

    static function init($debug=true) {
        self::db()->show_errors = $debug;
        $charset_collate = self::db()->get_charset_collate();

        if (self::exists()) {
            return true;
        }
        Notices::push('info', 'CREATE TABLE ' . self::name());
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        $sql = implode(PHP_EOL, ["CREATE TABLE " . self::name() . "(", // " IF NOT EXISTS (",
                                 "id bigint(20) NOT NULL AUTO_INCREMENT,",
                                 "login varchar(60) NOT NULL,",
                                 "registered datetime NOT NULL DEFAULT '" . self::ISO_DATE_TIME . "',",
                                 "modified datetime NOT NULL DEFAULT '" . self::ISO_DATE_TIME . "',",
                                 "status int(11) NOT NULL DEFAULT '" . CasUsers::UNCHECKED . "',",
                                 "PRIMARY KEY  (id)", ") " . $charset_collate . ";", ""]);
        $res = dbDelta($sql);
        Notices::push('info', 'CREATE returns ' . json_encode($res));
        $res = self::exists();
        Notices::push('info', 'EXISTS returns ' . json_encode($res));
        return $res;
    }

    static function drop() {
        require_once ABSPATH . '/wp-admin/includes/upgrade.php';
        $res = dbDelta(implode('', ["DROP TABLE '", self::name(), "'"]));
        Notices::push('info', 'DROP returns ' . json_encode($res));
    }

    static function dump() {
        $sql = implode('', ['SELECT * FROM ', self::name(), ' ORDER BY login ASC', ";"]);
        return self::db()->get_results($sql);
    }

    static function name() {
        return self::db()->prefix . self::$table;
    }

    static function void() {
        // COUNT(*) on empty returns 1: why?
        $res = self::db()->query('SELECT * FROM ' . self::name() . ';');
        Notices::push('info', 'COUNT = ' . $res);
    }

    static function exists() {
        // $res = self::db()->get_col("SHOW TABLES", 0); Notices::push('info', 'EXISTS? ' . json_encode($res));
        return in_array(self::name(), self::db()->get_col("SHOW TABLES", 0));
    }

    static function get_by_login($uid) {
        $sql = 'SELECT * FROM '. self::name() . " WHERE login = '$uid'";
        return self::db()->get_row($sql);
    }

    static function get_status($uid) {
        $sql = 'SELECT status FROM ' . self::name() . " WHERE login = '$uid'";
        $res = self::db()->get_row($sql);
        return $res->status;
    }

    static function set_status($uid, $status) {
        $data = ['status' => $status, 'modified' => gmdate('Y-m-d H:i:s')];
        // table, data, where_conditions, [format, where_format]
        return self::db()->update(self::name(), $data, ['login' => $uid]);
    }

    static function insert($uid, $status) {
        $data = ['login' => $uid,
                 'registered' => gmdate('Y-m-d H:i:s'),
                 'status' => $status];
        return self::db()->insert(self::name(), $data);
    }

    static function db() {
        global $wpdb;
        return $wpdb;
    }
}
