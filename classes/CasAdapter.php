<?php


class CasAdapter
{
    const ROLE = "manage_options";
    static $form_action_name = '';
    static $status = ['initialized' => false, 'available' => false, 'activated' => false];

    static function init() {
        try {
        Options::init(__CLASS__);
        self::$form_action_name = __CLASS__ . '_update';
        self::activation();

        register_activation_hook(__FILE__, ['CasUsers', 'maybe_create_table']);
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        register_uninstall_hook(__FILE__, ['Options', 'erase']);
        if (is_admin() || (defined( 'WP_CLI') && WP_CLI)) {
            if (self::submit_action()) {
                Options::update_options();
            }
            if (self::submit_action(['ACTIVATE', 'DESACTIVATE'])) {
                Options::set_option('activation', $_POST[self::$form_action_name]);
            }
            self::$status['activated'] = self::$status['available'] && Options::get_option('activation') === 'ACTIVATE';
        }
        } catch (Exception $e) {
            var_dump($e);die();
        }
    }

    static function activation() {
        self::$status['initialized'] = CasClient::init(__CLASS__);
        if (!self::$status['initialized']) {
            Notices::push('warning', json_encode(self::$status));
        }
        self::$status['available'] = self::$status['initialized'] && CasUsers::init();
        if (!self::$status['available']) {
            Notices::push('warning', json_encode(self::$status));
        }
        if (!(self::$status['available'] && (Options::get_option('activation') === 'ACTIVATE'))) {
            return;
        }
        add_filter('wp_authenticate', ['CasClient', 'authentication'], 10, 2); // priority=10, nbargs=2
        add_filter('wp_logout', ['CasClient', 'logout'], 10, 0);
        add_filter('show_password_fields', ['CasClient', 'show_password_fields'], 10, 1); // default values

        add_action('lost_password', ['CasClient', 'disabled_action']);
        add_action('retrieve_password', ['CasClient', 'disabled_action']);
        add_action('password_reset', ['CasClient', 'disabled_action']);
        add_action('check_passwords', ['CasClient', 'check_passwords'], 10, 3);
    }

    static function is_submit() {
        return isset($_POST) && isset($_POST[__CLASS__ . '_submit']);
    }

    static function submit_action($values=['OPTIONS']) {
        return self::is_submit() && in_array($_POST[self::$form_action_name], $values);
    }

    // https://codex.wordpress.org/Administration_Menus
    static function admin_menu() {
        $hook = add_options_page(__(__CLASS__ . " Settings", __CLASS__), __(__CLASS__, __CLASS__), self::ROLE, __FILE__, [__CLASS__, 'options_page']);
        if ($hook) {
            // add_action("load-$hook", [self, 'admin_help']);
        }
    }

    // https://codex.wordpress.org/Creating_Options_Pages
    static function options_page() {
        if (!current_user_can(self::ROLE)) {
            wp_die(__( 'You do not have sufficient permissions to access this page.'));
        }
        $template = dirname(__DIR__) . '/templates/admin_panel.twig';
        $header = __CLASS__ . " configuration panel";
        $panel = new H2o($template, ['cache_dir' => dirname(__DIR__) . '/cache']);
        $data = ["namespace" => __CLASS__,
                 "header" => __($header, __CLASS__),
                 'notices' => Notices::popAll(),
                 "update_options" => __("Update Options", __CLASS__),
                 "saved_options" => __("Options Saved",__CLASS__),
                 "cas_versions" => ['2.0', '3.0'],
                 "panel_settings" => Options::panel_settings(),
                 "status" => self::$status,
                 "updated_flag" => (self::submit_action() ? 'block' : 'none'),
                 "form_action_name" => self::$form_action_name,
                 // "form_action_url" => plugin_dir_url(__FILE__) . basename(__FILE__)
                 "form_action_url" => str_replace( '%7E', '~', $_SERVER['REQUEST_URI'])
        ];
        echo $panel->render($data);
    }
}
