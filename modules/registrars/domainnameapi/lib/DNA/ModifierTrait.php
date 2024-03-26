<?php
/**
 * Created by PhpStorm.
 * User: esh
 * Project name php-dna-new
 * 29.01.2024 06:46
 * Bünyamin AKÇAY <bunyamin@bunyam.in>
 */

namespace DNA;

trait ModifierTrait {

     public function parseContact($params, $type = 'Registrant') {

        $firstname   = $params["firstName"];
        $lastname    = $params["lastName"];
        $compantname = $params["companyName"];
        $email       = $params["eMail"];
        $address1    = $params["address"];
        $state       = $params["state"];
        $city        = $params["city"];
        $country     = $params["country"];
        $fax         = $params["fax"];
        $faxcc       = $params["faxCountryCode"];
        $phone       = $params["phone"];
        $phonecc     = $params["phoneCountryCode"];
        $postcode    = $params["postalCode"];


        $arr_client = [
            "firstName"        => mb_convert_encoding($firstname, "UTF-8", "auto"),
            "lastName"         => mb_convert_encoding($lastname, "UTF-8", "auto"),
            "companyName"      => mb_convert_encoding($compantname, "UTF-8", "auto"),
            "eMail"            => mb_convert_encoding($email, "UTF-8", "auto"),
            "address"          => mb_convert_encoding($address1 . ' ', "UTF-8", "auto"),
            "state"            => mb_convert_encoding($state, "UTF-8", "auto"),
            "city"             => mb_convert_encoding($city, "UTF-8", "auto"),
            "country"          => mb_convert_encoding($country, "UTF-8", "auto"),
            "fax"              => mb_convert_encoding($fax, "UTF-8", "auto"),
            "faxCountryCode"   => mb_convert_encoding($faxcc, "UTF-8", "auto"),
            "phone"            => mb_convert_encoding($phone, "UTF-8", "auto"),
            "phoneCountryCode" => mb_convert_encoding($phonecc, "UTF-8", "auto"),
            "postalCode"       => mb_convert_encoding($postcode, "UTF-8", "auto"),
            'discloseMask'     => true,
            'contactType'      => $type
        ];




        return $arr_client;

    }

}