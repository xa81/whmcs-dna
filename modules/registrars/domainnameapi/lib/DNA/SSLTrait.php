<?php
/**
 * Created by PhpStorm.
 * User: esh
 * Project name php-dna-new
 * 29.01.2024 06:48
 * Bünyamin AKÇAY <bunyamin@bunyam.in>
 */

namespace DNA;

trait SSLTrait {

    public function getSSLList() {

        $resp = $this->request('GET','ssls/generate-csr');

        return $resp;

    }

}