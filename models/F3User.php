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
 * @version 0.2.0-BETA 
 * @link https://github.com/manne65-hd/F3-UserGroupNavbar
 * 
 **/

namespace manne65hd;

class F3User extends \DB\SQL\Mapper{
    const AUTH_TYPE_LOCAL = 1;
    const AUTH_TYPE_BOT   = 2;
    const AUTH_TYPE_LDAP  = 3;

       
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.2.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The "pure" SQL-object, required for some special queries NOT suitable for use with the mapper*/
    protected $appDB;

    /** @var object The LDAP-connection-object */
    protected $ldapServer; 

    /** @var object The F3Group-object */
    protected $F3Group; 

    /** @var object The F3UserGroup-object */
    protected $F3UserGroup; 

    /** @var array The users group memberships(array of IDs for the groups) */
    protected $groups;

	public function __construct($app_db_instance_name = 'DB') {
		parent::__construct( \Base::instance()->get($app_db_instance_name), 'users' );
        $this->f3 = \Base::instance();
        $this->appDB = $this->f3->get($app_db_instance_name);
        $this->F3Group = new \manne65hd\F3Group($app_db_instance_name);
        $this->F3UserGroup = new \manne65hd\F3UserGroup($app_db_instance_name);
	}
    
    public function login($username, $password) {
        
        if ($this->load(['username = ?', $username])) {
            // Username was found ...
            if ($this->auth_type === self::AUTH_TYPE_LOCAL) {
                // ... and it is a LOCAL user, so we can check against pw_hash
                if (!$this->is_deleted && $this->is_active) {
                    $auth_success = password_verify($password, $this->pw_hash);
                    if ($auth_success) {
                        $this->startUserSession($this->id);
                    }
                    return $auth_success;
                } else {
                    return false;
                }
            } elseif ($this->auth_type === self::AUTH_TYPE_LDAP) {
                // ... but user is from LDAP, so let's check auth via LDAP
                self::connectLdapServer($this->f3->get('ldap.server_type'));
                $ldap_user_info = $this->ldapServer->ldapGetUserInfo($username);
                if ($ldap_user_info) {
                    /* ToDo
                       Will have to do more testing to find out, what happens if LDAP-user has been disabled?
                            - AUTH not successful -> return false !
                            - AUTH successful -> get MORE $ldap_user_info with flags to figure out the status ?
                            - Should I inform the calling method about the status ??
                    */
                    $auth_success =  $this->ldapServer->ldapAuth($ldap_user_info['user']['distinguishedname'], $password);
                    if ($auth_success) {
                        /*
                        echo '<pre>';
                        print_r($ldap_user_info);
                        echo '</pre>';
                        exit;
                        */
                        // Before we can assign the user to his groups, we need to make sure they exist in the local groups-table!
                        $this->groups = $this->F3Group->syncLDAPGroups($ldap_user_info['groups']);
                        $this->F3UserGroup->syncLocalGroupsOfUser($this->id, $this->groups);

                        $this->startUserSession($this->id);
                    }
                    return $auth_success;
                } else {
                    return false;
                }
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

                    // Before we can assign the user to his groups, we need to make sure they exist in the local groups-table!
                    $this->groups = $this->F3Group->syncLDAPGroups($ldap_user_info['groups']);
                    $this->F3UserGroup->syncLocalGroupsOfUser($this->id, $this->groups);

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
        $this->f3->set('SESSION.auth_type', $this->auth_type);
        $this->f3->set('SESSION.groups', $this->groups);
    }

}