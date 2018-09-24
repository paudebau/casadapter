<?php

class CasClient
{
    static $ns = '';

    static function init($ns) {
        self::$ns = $ns;
        extract(Options::get_all());
        if (!($version && $server && $port && $path && $domain)) {
            Notices::push('warning', 'CAS parameters missing');
            return false;
        }
        phpCAS::client($version, $server, (int)$port, $path);
        if (!phpCAS::isInitialized()) {
            Notices::push('error', 'CAS client is not initialized....');
            return false; // wp_die(__('CAS client is not initialized....', self::$ns));
        }
        phpCAS::handleLogoutRequests(true);
        if ($cert_path && file_exists($cert_path) && is_readable($cert_path)) { // TODO le upload
            phpCAS::setCasServerCACert($cert_path);
        } else {
            phpCAS::setNoCasServerValidation();
        }

        if (!phpCAS::isInitialized()) {
            Notices::push('error', 'CAS client has problem wrt to certificate');
            return false;
        }

        $pgt_base = preg_quote(get_option('siteurl')); // FIXME weakening wrt preg_replace('/^http:/', 'https:', $proxy), '/');
        phpCAS::allowProxyChain(new CAS_ProxyChain(["/^{$pgt_base}.*$/"]));

        if (!phpCAS::isInitialized()) {
            Notices::push('error', 'CAS client has problem wrt proxy chaining');
            return false;
        }
        return true;
    }

    static function logout() {
        phpCAS::logoutWithRedirectService(get_settings( 'siteurl' )); // FIXME diff wrt get_option('siteurl') ?
        // phpCAS::logout( array( 'url' => get_settings( 'siteurl' )));
    }

    static function authentication() {
        if (phpCAS::isAuthenticated()) {
            $uid = phpCAS::getUser();
            $attributes = phpCAS::getAttributes();
            $email = $attributes['mail'];
            $is_admin = $uid === Options::get('admin');
            Notices::push('info', "authenticated as $uid <$email> ($is_admin)");
            $user = get_user_by('login', $uid);

            if (!$user) {
                wp_insert_user(self::prepare_data($uid, $email, $is_admin)); // FIXME harcoded
                $user = get_user_by('login', $uid);
            }
            if ($user) {
                CasUsers::record($user->user_login, $is_admin);
                wp_set_auth_cookie($user->id, true);
                $is_admin = current_user_can('administrator');
                wp_redirect($is_admin ? 'wp-admin/index.php' : 'index.php');
            }
        } else {
            phpCAS::forceAuthentication();
        }
    }

    static function prepare_data($uid, $email, $is_admin) {

        $role = $is_admin ? "administrator" : "subscriber";

        $domain = Options::get('domain') ?: '';
        $domain = ($domain && count($domain) > 1 && !$domain[0] !== '@' ? '@' : '') . $domain;

        $dotted_ident = str_replace($domain, '', $email);
        $url = 'http://perso.ens-lyon.fr/' . strtolower($dotted_ident) . '/'; // FIXME
        list($first_name, $last_name) = array_map(ucfirst, explode('.', $dotted_ident));

        return ['user_login' => $uid,
                'user_pass' => CasUsers::randomize_password(),
                'user_email' => $email,
                'user_url' => $url,
                'user_nicename' => '',
                'user_registered' => gmdate('Y-m-d H:i:s'),
                'user_activation_key' => '',
                'user_status' => '0',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => '',
                'role' => $role];
    }

    static function disabled_action() {
        wp_die(__('Action with NO effect; ask administrator for help.', self::$ns));
    }

    static function show_password_fields($show_password_fields) {
        return false;
    }

    static function check_passwords($user, $password, $password_confirmed) {
        $password = $password_confirmed = CasUsers::randomize_password();
    }

}
