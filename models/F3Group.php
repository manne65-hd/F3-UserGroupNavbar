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
 * @version 0.2.0-BETA 
 * @link https://github.com/manne65-hd/F3-UserGroupNavbar
 * 
 **/

namespace manne65hd;

class F3Group extends \DB\SQL\Mapper{
    const GROUP_TYPE_LOCAL = 1;
    const GROUP_TYPE_LDAP  = 2;

       
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.2.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    /** @var object The "pure" SQL-object, required for some special queries NOT suitable for use with the mapper*/
    protected $appDB;

    /** @var object The LDAP-connection-object */
    protected $ldapServer; 

	public function __construct($app_db_instance_name = 'DB') {
		parent::__construct( \Base::instance()->get($app_db_instance_name), 'groups' );
        $this->f3 = \Base::instance();
        $this->appDB = $this->f3->get($app_db_instance_name);
	}
    
    public function syncLDAPGroups($groups) {
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

    protected function AddGroups2UserSession(int $user_id) :void {
        // $this->f3->set('SESSION.groups', $groups);
    }

}