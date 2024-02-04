<?php

/**
 * F3UserGroup - The UserGroup-Class handling the n:m-Relationships
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

class F3UserGroup extends \DB\SQL\Mapper{
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.2.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The "pure" SQL-object, required for some special queries NOT suitable for use with the mapper*/
    protected $appDB;

    public function __construct($app_db_instance_name = 'DB') {
        parent::__construct( \Base::instance()->get($app_db_instance_name), 'users_groups' );
        $this->f3 = \Base::instance();
        $this->appDB = $this->f3->get($app_db_instance_name);
    }
    
    public function userIsMemberOfGroup($user_id, $group_id) {
        return $this->count(array('user_id = ? AND group_id = ?', $user_id, $group_id));
    }

    public function syncLocalGroupsOfUser($user_id, $group_ids) {
        \Flash::instance()->addMessage('Syncing Group-IDs: ' . implode(',', $group_ids), 'info');

        // first we'll remove the user from local groups he no longer belongs to
        $this->removeUserFromStaleLDAPGroups($user_id, $group_ids);
        foreach ($group_ids as $group_id) {
            if (!$this->userIsMemberOfGroup($user_id, $group_id)) {
                \Flash::instance()->addMessage('Adding user to Group-ID: ' . $group_id, 'success');
                $this->user_id = $user_id;
                $this->group_id = $group_id;
                $this->group_type = \manne65hd\F3Group::GROUP_TYPE_LDAP;
                $this->save();
                $this->reset();
            }
        }
    }

    /**
     * Removes a given user from (local)LDAP-groups he no longer belongs to
     *
     * @param integer $user_id           The user_id of the user 
     * @param array   $active_group_ids  An array with the ids of the (local)LDAP-groups the user currently belongs to
     * @return void
     */
    protected function removeUserFromStaleLDAPGroups(int $user_id, array $active_group_ids) :void{
        // let's sanitize the parameters to prevent SQL-injection 
        // we need this because the F3-SQL-class doesn't properly support the "NOT In (1,2,3,...)"-clause, so we cannot use a parametrized query!
        $user_id                = intval($user_id);
        $active_group_ids       = array_filter(array_map(fn($value) => intval($value), $active_group_ids));
        $active_group_ids_list  = implode(',', $active_group_ids);
        $group_type_ldap        = \manne65hd\F3Group::GROUP_TYPE_LDAP;

        $sql = "DELETE FROM users_groups  
                        WHERE   user_id     = $user_id  
                            AND group_type  = $group_type_ldap
                            AND group_id    NOT IN ($active_group_ids_list)";

        $this->appDB->exec($sql);
        $purged_groups = $this->appDB->count();
        \Flash::instance()->addMessage('Purged user from  ' . $purged_groups . ' groups!', 'info');
    }


}