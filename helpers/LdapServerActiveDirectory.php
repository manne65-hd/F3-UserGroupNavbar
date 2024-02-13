<?php

/**
 * LdapServerActiveDirectory - Concrete Class for User-AUTH and GROUP-Information in MS ActiveDirectory
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

final class LdapServerActiveDirectory extends LdapServer {

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The "pure" SQL-object, required for some special queries NOT suitable for use with the mapper*/
    protected $appDB;

    public function getUserDataByName($username) {
        $this->currentLdapUser = $this->ldapUser::findByAnr($username);
        if ($this->currentLdapUser) {
            return $this->getUserDataAsArray($this->currentLdapUser);
        } else {
            return false;
        }
    }

    public function getUserDataByGuid($guid) {
        $this->currentLdapUser = $this->ldapUser::findByGuid($guid);
        if ($this->currentLdapUser) {
            return $this->getUserDataAsArray($this->currentLdapUser);
        } else {
            return false;
        }
    }

    public function getUserGroupsByGuid($guid) {
        $f3 = \Base::instance(); // ToDo ... make this a class-property and assign on __construct()

        $this->currentLdapUser = $this->ldapUser::findByGuid($guid);
        if ($this->currentLdapUser) {
            // get ALL groups
            $all_groups = $this->currentLdapUser->groups()->get();
            // Array to hold the relevant group-memberships of the user based on $f3->get('ldap.groups_base_dn')
            $base_groups = []; 
            // let's iterate all groups ...
            foreach ($all_groups as $group) {
                // check if current group is INSIDE the relevant groups
                if (str_contains(mb_strtolower($group->getDn()), mb_strtolower($f3->get('ldap.groups_base_dn')))) {
                    $base_groups[] = array(
                        'name'  => $group->getName(),
                        'dn'    => $group->getDn(),
                        'guid'  => $group->getConvertedGuid(),
                        'description' => $group->getFirstAttribute('Description'),
                    );
                }
            }
            return $base_groups;            
        } else {
            return false;
        }
    }

    protected function getUserDataAsArray($userObject) {
        return array(
            'distinguishedname' => $userObject->getDn(),
            'guid' => $userObject->getConvertedGuid(),
            'recently_renamed' => $userObject['wasRecentlyRenamed'],
            'username'  => $userObject->getFirstAttribute('samaccountname'),
            'firstname'  => $userObject->getFirstAttribute('givenname'),
            'lastname'  => $userObject->getFirstAttribute('sn'),
            'displayname'  => $userObject->getFirstAttribute('displayname'),
            'email'  => $userObject->getFirstAttribute('mail'),
        );
    }


}