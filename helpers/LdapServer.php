<?php

/**
 * LdapServer - Abstract Class for AD-User-AUTH and GROUP-Information
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
 * @version 0.3.0-BETA 
 * @link https://github.com/manne65-hd/F3-UserGroupNavbar
 * 
 **/

namespace manne65hd;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;

abstract class LdapServer {
    const TYPE_ACTIVE_DIRECTORY = 'ActiveDirectory';
    const TYPE_OPEN_LDAP = 'OpenLDAP'; // 
    const TYPE_FREE_IPA = 'FreeIPA';
    const TYPE_OTHER = 'Other';

    protected $ldapServer; // The LDAP-connection-object
    protected $ldap_groups_base; // connect to
    protected $ldapUser; // The general LDAP-user-object required to use methods for searching, etc.
    protected $ldapGroup; // The general LDAP-group-object required to use methods for searching, etc.
    protected $currentLdapUser; // An object for a single concrete user
    protected $currentLdapGroup; // An object for a single concrete group


public function __construct(){
    $f3 = \Base::instance();
    $this->ldapServer =  new Connection([
        'hosts' => $f3->get('ldap.hosts'),
        'base_dn' => $f3->get('ldap.users_base_dn'),
        'username' => $f3->get('ldap.qry_username'),
        'password' => $f3->get('ldap.qry_password'),
      ]);

    Container::addConnection($this->ldapServer);
    $this->ldapUser  = new \LdapRecord\Models\ActiveDirectory\User();
    $this->ldapGroup = new \LdapRecord\Models\ActiveDirectory\Group();

    return $this->ldapServer;
}

    public function ldapAuth($distinguishedname, $password) {
        return $this->ldapServer->auth()->attempt($distinguishedname, $password);
    }

    abstract public function getUserDataByName($username);
    abstract public function getUserDataByGuid($guid);
    abstract public function getUserGroupsByGuid($guid);
    abstract protected function getUserDataAsArray($userObject);


}