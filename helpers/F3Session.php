<?php

/**
 * F3Session - Internal wrapper for the Session-class of FatFreeFramework
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

use Session;

class F3Session extends \Prefab {
    /** @var string Contains the current version tag of the F3-UserGroupNavbar-package */
    const VERSION='0.2.0-BETA';

    /** @var object The FatFreeFramework-Object required to use F3-functions inside this class */
    protected $f3;

    public function __construct() {
        $this->f3 = \Base::instance();
        new Session;
    }

    public function start() {
        if (!$this->f3->get('SESSION.user.id')) {
            $this->f3->set('SESSION.user.id', 0);
            $this->f3->clear('SESSION.user.username');
            $this->f3->clear('SESSION.user.auth_type');
            $this->f3->clear('SESSION.user.groups');
          }
    }

    public function logout() {
        $this->f3->set('SESSION.user.id', 0);
        $this->f3->clear('SESSION.user.username');
        $this->f3->clear('SESSION.user.auth_type');
        $this->f3->clear('SESSION.user.groups');
    }

    /**
     * Returns PackageInformation
     *
     * @return array An associative array with information about the package
     */
    public function getPackageInfo() {
        // Read package-info from composer.json into an array
        $pkg_composer_json = json_decode(file_get_contents('../vendor/manne65hd/f3-usergroupnavbar/composer.json'), JSON_OBJECT_AS_ARRAY );

        return array(
            'pkg_fullname'      => $pkg_composer_json['name'],
            'pkg_vendor'        => explode('/', $pkg_composer_json['name'])[0],
            'pkg_name'          => explode('/', $pkg_composer_json['name'])[1],
            'pkg_description'   => $pkg_composer_json['description'],
            'pkg_version'       => self::VERSION,// version-tag not recommended in composer.json, so pulling from CONST
            'pkg_license'       => $pkg_composer_json['license'],
            'pkg_authors'       => $pkg_composer_json['authors'],
            // detect if the package is included via SYMLINK (for local development)
            'pkg_is_symlinked'  => (str_contains(dirname(__FILE__), 'aaa_local_pkgdev')) ? true : false,
        );
    }


}
