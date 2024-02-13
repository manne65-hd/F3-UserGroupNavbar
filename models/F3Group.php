<?php

/**
 * F3Group - The group-class handling group-memberships
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

class F3Group extends \DB\SQL\Mapper{
    const GROUP_TYPE_LOCAL = 1;
    const GROUP_TYPE_LDAP  = 2;

       
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.3.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The "pure" SQL-object, required for some special queries NOT suitable for use with the mapper*/
    protected $appDB;

    /** @var object The LDAP-connection-object */
    protected $ldapServer; 

    /** @var array An array for the group_type_icons */
    protected $group_icons = [
        1 => 'fa-solid fa-database',
        2 => 'fa-solid fa-network-wired'
    ];
    

	public function __construct($app_db_instance_name = 'DB') {
		parent::__construct( \Base::instance()->get($app_db_instance_name), 'ug__groups' );
        $this->f3 = \Base::instance();
        $this->appDB = $this->f3->get($app_db_instance_name);
	}

    
        /**
     * Returns all groups  ...
     *
     * @param array $filter   
     * @return array an associative array of the groups matching optional $filter
     */
    public function getGroupList(array $filter = []) :array {
        $sql = 'SELECT * FROM ug__groups';
        $group_list = $this->appDB->exec($sql);
        /*let's add some sugar to the array:
            - add icon for GROUP_type 
            - Convert LineBreaks to <br> for description
        */
        foreach ($group_list as $key => $value){
            $group_list[$key]['group_icon'] = $this->group_icons[$value['group_type']];
            $group_list[$key]['description'] = (!empty($group_list[$key]['description'])) ? nl2br($value['description']) : '';
        }
        return $group_list;
    }

    /**
     * Returns the user's local group-memberships
     *
     * @param integer   $user_id    The id of the user
     * @return array    a flat array with the local group-IDs, that the user is a member of
     */
    public function getUsersGroupIDs(int $user_id) :array {
        $sql = 'SELECT group_id FROM ug__group_has_users WHERE user_id=?';
        $users_groups = array_column($this->appDB->exec($sql, $user_id), 'group_id');
        if ($users_groups) {
            return $users_groups;
        } else {
            return [];
        }
    }

    /**
     * Returns the user's local group-memberships with id, name, type and description
     *
     * @param integer   $user_id    The id of the user
     * @return array    a flat array with the local group-IDs, that the user is a member of
     */
    public function getUsersGroupMemberships(int $user_id) :array {
        $sql = 'SELECT u.group_id AS id, g.group_type, g.groupname, g.description 
                    FROM ug__group_has_users AS u 
                    LEFT JOIN ug__groups AS g ON u.group_id = g.id 
                        WHERE u.user_id = ?';
        return $this->appDB->exec($sql, $user_id);
    }

    /**
     * Returns all member-users of a given group with id, name, type and description
     *
     * @param integer   $group_id    The id of the group
     * @return array    a flat array with the local group-IDs, that the user is a member of
     */
    public function getGroupMembers(int $group_id) :array {
        $sql = 'SELECT ug.user_id AS id, u.auth_type, u.username 
                    FROM ug__group_has_users AS ug 
                    LEFT JOIN ug__users AS u ON ug.user_id = u.id 
                        WHERE ug.group_id = ?';
        return $this->appDB->exec($sql, 1);
    }

    /**
     * Sync local groups with group-information from LDAP
     *
     * @param array $groups An associative array of LDAP-group-information with the following keys:'name', 'dn', 'guid', 'description'
     *              HINT: dn = DistinguishedName

     * @return array a flat array with the local group-IDs, that the user is a member of
     */
    public function syncLDAPGroups(array $groups) :array {
        $users_local_groups = [];
        foreach ($groups as $group) {
            if ($this->load(['ldap_unique_id = ?', $group['guid']])) {
                // group exists ... CHECK if we need to update information from LDAP
                $group_was_updated = false;
                if ($this->groupname !== $group['name']) {
                    $group_was_updated = true;
                    $this->groupname = $group['name'];
                }
                if ($this->description !== $group['description']) {
                    $group_was_updated = true;
                    $this->description = $group['description'];
                }
                if ($group_was_updated) {
                    $this->updated_datetime = date('Y-m-d H:i:s');
                    $this->updater_id = $this->f3->get('ldap.bot_user_id');
                    $this->save();
                }
                $users_local_groups[] = $this->get('id');
            } else {
                // group does NOT exist ... let's create it!
                $this->group_type = self::GROUP_TYPE_LDAP;
                $this->ldap_unique_id = $group['guid'];
                $this->created_datetime = date('Y-m-d H:i:s');
                $this->creator_id = $this->f3->get('ldap.bot_user_id');
                $this->groupname = $group['name'];
                $this->description = $group['description'];
                $this->save();
                $users_local_groups[] = $this->get('_id');
                $this->reset();
            }
        }
        return $users_local_groups;
    }

}