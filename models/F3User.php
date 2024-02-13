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
    const GET_LOCAL_GROUPS = 1;
    const GET_LDAP_GROUPS  = 2;

       
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

    /** @var array An array for the auth_type_icons */
    protected $auth_icons = [
        1 => 'fa-solid fa-database',
        2 => 'fa-solid fa-robot',
        3 => 'fa-solid fa-network-wired'
    ];

    protected $state_active = [
        0 => 'fa-solid fa-toggle-off',
        1 => 'fa-solid fa-toggle-on',
    ];

    protected $state_deleted = [
        0 => '',
        1 => 'fa-regular fa-trash-can'
    ];

    /** @var string The default format for return date&time */
    protected $datetime_format = 'd.m.y H:i:s';



	public function __construct($app_db_instance_name = 'DB') {
		parent::__construct( \Base::instance()->get($app_db_instance_name), 'ug__users' );
        $this->f3 = \Base::instance();
        $this->appDB = $this->f3->get($app_db_instance_name);
        $this->F3Group = new \manne65hd\F3Group($app_db_instance_name);
        $this->F3UserGroup = new \manne65hd\F3UserGroup($app_db_instance_name);
	}

    // ************************************************************************************************************* //
    // *****                                                                                                   ***** //
    // *****                                                                                                   ***** //
    // *****                          general getter/setter-Methods                                            ***** //
    // *****                                                                                                   ***** //
    // *****                                                                                                   ***** //
    // ************************************************************************************************************* //

    
    /**
     * Returns all users  ...
     *
     * @param array $filter   
     * @return array    a associative array of the users matching optional $filter
     */
    public function getUserList(array $filter = []) :array {
        $sql = 'SELECT * FROM ug__users WHERE 1';
        $user_list = $this->appDB->exec($sql);
        /* let's add some sugar to the array:
            - add icon for AUTH_type 
            - remove pw_hash ... although pw_hashes can hardly ever be decrypted
        */
        foreach ($user_list as $key => $value){
            unset($user_list[$key]['pw_hash']);
            $user_list[$key]['auth_icon'] = $this->auth_icons[$value['auth_type']];
            $user_list[$key]['last_login'] = ($user_list[$key]['last_login']) ? date($this->datetime_format, $user_list[$key]['last_login']) : '';
            $user_list[$key]['last_ldap_sync'] = ($user_list[$key]['last_ldap_sync']) ? date($this->datetime_format, $user_list[$key]['last_ldap_sync']) : '';
            $user_list[$key]['created_datetime'] = ($user_list[$key]['created_datetime']) ? date($this->datetime_format, strtotime($user_list[$key]['created_datetime'])) : '';
            $user_list[$key]['updated_datetime'] = ($user_list[$key]['updated_datetime']) ? date($this->datetime_format, strtotime($user_list[$key]['updated_datetime'])) : '';
        }
        return $user_list;
    }

    public function getUserDatabyName($username){
        if ($this->load(['username = ?', $username])) {
            $user_without_pw_hash = $this->cast();
            unset($user_without_pw_hash['pw_hash']);
            return $user_without_pw_hash;
        } else {
            return [];
        }

    }
    // ************************************************************************************************************* //
    // *****                                                                                                   ***** //
    // *****                                                                                                   ***** //
    // *****                        Login / Authentication / Session / Cookies                                  ***** //
    // *****                                                                                                   ***** //
    // *****                                                                                                   ***** //
    // ************************************************************************************************************* //

    public function login(string $username, string $password) :bool {
        $login_success = false;

        if ($this->load(['username = ?', $username])) {
            // Username was found ...
            if ($this->auth_type === self::AUTH_TYPE_LOCAL) {
                $login_success = $this->checkAuthLocalUser($password);
                $group_retrieval_mode = self::GET_LOCAL_GROUPS;
            } elseif ($this->auth_type === self::AUTH_TYPE_LDAP) {
                $login_success = $this->checkAuthLocalLdapUser($password);
                $duration_since_last_ldap_sync = time() - intval($this->last_ldap_sync);
                if ($duration_since_last_ldap_sync > $this->f3->get('ldap.force_sync_after_seconds')) {
                    $group_retrieval_mode = self::GET_LDAP_GROUPS;
                } else {
                    $group_retrieval_mode = self::GET_LOCAL_GROUPS;
                }
            }
        } else {
            // username was NOT found ...
            $login_success = $this->checkAuthNewLdapUser($username, $password);
            $group_retrieval_mode = self::GET_LDAP_GROUPS;
        }

        if ($login_success) {
            $this->last_login = time();
            // finally we need to get the user's GROUP-memberships ...
            if ($group_retrieval_mode === self::GET_LOCAL_GROUPS) {
                // \Flash::instance()->addMessage('Getting local GROUP-memberships ...', 'info');
                $this->groups = $this->F3Group->getUsersGroupIDs($this->id);
            } else {
                $this->getUsersLdapGroups();
                $this->last_ldap_sync = time();
            }

            $this->save();
            $this->startUserSession($this->id);
        }
        return $login_success;
    }

    protected function checkAuthLocalUser(string $password) :bool {
        $auth_success = false;
        // we'll only check the password if the user is ACTIVE and not DELETED ... otherwise AUTH will fail!
        if ($this->is_active && !$this->is_deleted) {
            $auth_success = password_verify($password, $this->pw_hash);
        }
        return $auth_success;
    }

    protected function checkAuthLocalLdapUser(string $password) :bool {
        /* ToDo:
                Will have to handle a couple of EDGE-Cases later:
                - User has been DEACTIVATED in LDAP 
                    - How does the ldapAuth-method react?
                    - unset IS_ACTIVE-flag for local user
                - Previously DEACTIVATED LDAP-user has been reactivated
                    - reset IS_ACTIVE-flag for local user
                - if a user has been renamed in LDAP, we'll have to handle:
                    - User logged in with old username
                    - User logged in with new username
                    - what else ??
        */
        $auth_success = false;
        $user_renamed = false;

        self::connectLdapServer($this->f3->get('ldap.server_type'));
        $ldap_user_data = $this->ldapServer->getUserDataByName($this->username);
        if ($ldap_user_data) {
            $auth_success =  $this->ldapServer->ldapAuth($ldap_user_data['distinguishedname'], $password);
            // User might have been DEACTIVATED, but as AUTH was successful ... let's reset the IS_ACTIVE-flag for the local user!
            $this->is_active = 1;
            $this->save();
        } else {
            // Looks like the LDAP user has been DELETED ... need to mark current local user as DELETED and INACTIVE
            $this->is_deleted = 1;
            $this->is_active = 0;
            $this->save();
        }

        return $auth_success;
    }

    protected function checkAuthNewLdapUser(string $username, string $password) :bool {
        /* ToDo:
                Will have to handle a couple of EDGE-Cases later:
                - User has been DEACTIVATED in LDAP 
                    - How does the ldapAuth-method react?
                    - unset IS_ACTIVE-flag for local user
                - Previously DEACTIVATED LDAP-user has been reactivated
                    - reset IS_ACTIVE-flag for local user
                - if a user has been renamed in LDAP, we'll have to handle:
                    - User logged in with old username
                    - User logged in with new username
                    - what else ??
        */
        $auth_success = false;
        $user_renamed = false;

        self::connectLdapServer($this->f3->get('ldap.server_type'));
        $ldap_user_data = $this->ldapServer->getUserDataByName($username);
        if ($ldap_user_data) {
            $auth_success =  $this->ldapServer->ldapAuth($ldap_user_data['distinguishedname'], $password);
            if ($auth_success) {
                $ldap_user_data['auth_type'] = self::AUTH_TYPE_LDAP;
                $ldap_user_data['ldap_unique_id'] = $ldap_user_data['guid'];
                $ldap_user_data['created_datetime'] = date('Y-m-d H:i:s');
                $ldap_user_data['creator_id'] = $this->f3->get('ldap.bot_user_id');
                $new_userid = self::saveUser($ldap_user_data);
            }
        }

        return $auth_success;
    }


    protected function getUsersLdapGroups() {
        // \Flash::instance()->addMessage('SYNCING LDAP GROUP-memberships ...', 'info');
        $users_ldap_groups = $this->ldapServer->getUserGroupsByGuid($this->ldap_unique_id);
        // Before we can assign the user to his groups, we need to make sure they exist in the local groups-table!
        $this->groups = $this->F3Group->syncLDAPGroups($users_ldap_groups);
        $this->F3UserGroup->syncLocalGroupsOfUser($this->id, $this->groups);

    }


    public function saveUser(array $user_data) :int{
        $this->copyFrom($user_data);
        $this->save();
        $new_userid = $this->get('_id');
        // load the record we just saved!
        $this->load(['username = ?', $user_data['username']]);
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
        $this->f3->set('SESSION.user.id', $user_id);
        $this->f3->set('SESSION.user.username', $this->username);
        $this->f3->set('SESSION.user.auth_type', $this->auth_type);
        $this->f3->set('SESSION.user.groups', $this->groups);
    }

}