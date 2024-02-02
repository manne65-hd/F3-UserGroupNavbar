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
 * @version 0.1.0-BETA 
 * @link https://github.com/manne65-hd/F3-UserGroupNavbar
 * 
 **/

namespace manne65hd;

/*
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;
*/

final class LdapServerActiveDirectory extends LdapServer {


    public function ldapGetUserInfo($username) {
        $f3 = \Base::instance();

        $user = $this->ldap_user::findByAnr($username);

        if ($user) {
            $user_info = array(
                'distinguishedname' => $user->getDn(),
                'guid' => $user->getConvertedGuid(),
                'recently_renamed' => $user['wasRecentlyRenamed'],
                'username'  => $user->getFirstAttribute('samaccountname'),
                'firstname'  => $user->getFirstAttribute('givenname'),
                'lastname'  => $user->getFirstAttribute('sn'),
                'displayname'  => $user->getFirstAttribute('displayname'),
                'email'  => $user->getFirstAttribute('mail'),
            );

            // get ALL groups
            $all_groups = $user->groups()->get();
            // Array to hold the relevant group-memberships of the user based on $f3->get('ldap.groups_base_dn')
            $base_groups = []; 

            foreach ($all_groups as $group) {
                // check if current group is INSIDE the relevant groups
                if (str_contains(mb_strtolower($group->getDn()), mb_strtolower($f3->get('ldap.groups_base_dn')))) {
                    $base_groups[] = array(
                        'name'  => $group->getName(),
                        'dn'    => $group->getDn(),
                        'guid'  => $group->getConvertedGuid(),
                    );
                }
            }

            return array(
                'user' => $user_info,
                'groups' => $base_groups,
            );
        } else {
            return false;
        }
    }


}