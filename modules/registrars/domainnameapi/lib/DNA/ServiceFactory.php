<?php
/**
 * Created by PhpStorm.
 * User: esh
 * Project name php-dna-new
 * 21.02.2024 06:46
 * Bünyamin AKÇAY <bunyamin@bunyam.in>
 */

namespace DNA;

class ServiceFactory {

    public static function createWithToken($token, $resellerId) {
        $service = new Service();
        $service->setToken($token);
        $service->setResellerId($resellerId);
        return $service;
    }

    public static function createWithCredentials($username, $password, $resellerId) {
        $service = new Service();
        $service->setCredentials($username, $password);
        $service->setResellerId($resellerId);
        // authenticate metodunu çağırabilirsiniz, örneğin:
        $service->authenticate();
        return $service;
    }

}