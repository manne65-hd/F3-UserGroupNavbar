<?php

/**
 * F3User - The user-class handling login, user-session, logout and more ...
 * 
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 *                                     __ _____       _         _ 
 * Created by:                        / /| ____|     | |       | |
 *  _ __ ___   __ _ _ __  _ __   ___ / /_| |__ ______| |__   __| |
 * | '_ ` _ \ / _` | '_ \| '_ \ / _ \ '_ \___ \______| '_ \ / _` |
 * | | | | | | (_| | | | | | | |  __/ (_) |__) |     | | | | (_| |
 * |_| |_| |_|\__,_|_| |_|_| |_|\___|\___/____/      |_| |_|\__,_|
 * 
 *     [ASCII-Art by: https://textkool.com/en/ascii-art-generator]
 * 
 * @package F3-UserGroupNavbar
 * @copyright 2024 Manfred Hoffmann
 * @author Manfred Hoffmann <oss@manne65-hd.de>
 * @license GPLv3
 * @version 0.1.0-BETA 
 * @link https://github.com/manne65-hd/F3-UserGroupNavbar
 * 
 **/

namespace manne65hd;

class F3User extends \DB\SQL\Mapper{
    const AUTH_TYPE_LOCAL = 0;
    const AUTH_TYPE_BOT   = 1;
    const AUTH_TYPE_LDAP  = 2;

       
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.1.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The LDAP-connection-object */
    protected $ldapServer; 

	public function __construct() {
		parent::__construct( \Base::instance()->get('DB'), 'users' );
	}
    
    public function login($username, $password) {
        $this->f3 = \Base::instance();
        
        if ($this->load(['username = ?', $username])) {
            // Username was found ...
            if ($this->auth_type === self::AUTH_TYPE_LOCAL) {
                // ... and it is a LOCAL user, so we can check against pw_hash
                $auth_success = password_verify($password, $this->pw_hash);
                if ($auth_success) {
                    $this->startUserSession($this->id);
                }
                return $auth_success;

            } elseif ($this->auth_type === self::AUTH_TYPE_LDAP) {
                // ... but user is from LDAP, so let's check auth via LDAP
                self::connectLdapServer($this->f3->get('ldap.server_type'));
                // for now it's OK to assume, that we will find a matching username in the LDAP ...
                // but later we will have to check if that's really true and also check, if the user is still active!
                $ldap_user_info = $this->ldapServer->ldapGetUserInfo($username);
                $auth_success =  $this->ldapServer->ldapAuth($ldap_user_info['user']['distinguishedname'], $password);
                if ($auth_success) {
                    $this->startUserSession($this->id);
                }
                return $auth_success;
            }
        } else {
            // Username was NOT found ... check LDAP ??
            self::connectLdapServer($this->f3->get('ldap.server_type'));
            $ldap_user_info = $this->ldapServer->ldapGetUserInfo($username);
            
            if ($ldap_user_info) {
                // user exists ... NEXT let's check if AUTH is OK
                $auth_success = $this->ldapServer->ldapAuth($ldap_user_info['user']['distinguishedname'], $password);

                if ($auth_success) {
                    $ldap_user_info['user']['auth_type'] = self::AUTH_TYPE_LDAP;
                    $ldap_user_info['user']['ldap_unique_id'] = $ldap_user_info['user']['guid'];
                    $ldap_user_info['user']['created_datetime'] = date('Y-m-d H:i:s');
                    $ldap_user_info['user']['creator_id'] = $this->f3->get('ldap.bot_user_id');
                    $new_userid = self::saveUser($ldap_user_info['user']);
                    // LATER I will have to catch CASE where the user was renamed (will not be saved because of unique GUID!)
                    $this->startUserSession($new_userid);
                }

                return $auth_success;
            }
        }

        return false;
    }

    public function saveUser(array $user_data) :int{
        $this->copyFrom($user_data);
        $this->save();
        $new_userid = $this->get('_id');
        $this->reset;
        return $new_userid;
    }

    protected function connectLdapServer($server_type){
        switch ($server_type) {
            case LdapServer::TYPE_ACTIVE_DIRECTORY:
                $this->ldapServer = new LdapServerActiveDirectory();
                break;
            case LdapServer::TYPE_OPEN_LDAP:
                $this->ldapServer = new LdapServerOpenLDAP();
                break;
            case LdapServer::TYPE_FREE_IPA:
                $this->ldapServer = new LdapServerFreeIPA();
                break;
            case LdapServer::TYPE_OTHER:
                $this->ldapServer = new LdapServerOther();
                break;
        }
    }

    protected function startUserSession(int $user_id) :void {
        $this->f3->set('SESSION.userid', $user_id);
        $this->f3->set('SESSION.username', $this->username);
    }

}