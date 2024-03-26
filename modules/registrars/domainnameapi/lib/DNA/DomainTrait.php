<?php
/**
 * Created by PhpStorm.
 * User: esh
 * Project name php-dna-new
 * 6.10.2023 01:24
 * Bünyamin AKÇAY <bunyamin@bunyam.in>
 */

namespace DNA;
trait DomainTrait {

    use ModifierTrait;


    public function generateCSR() {

        echo "generateCSR";

    }


    public function getCurrentBalance() {

        $resp = $this->request('GET', 'deposit/accounts/me');

        return $resp;

    }

    public function checkAvailability($Domains, $TLDs) {

        $queries = [];
        foreach ($Domains as $domain) {
            foreach ($TLDs as $tld) {
                $queries[]['domainName'] = $domain . '.' . $tld;
            }
        }

        $resp = $this->request('POST', 'domains/bulk-search', $queries);

        return $resp;

    }

    public function getDomainList() {

        $resp = $this->request('GET', 'domains',['MaxResultCount'=>200]);

        return $resp;

    }

    public function getTldList() {

        $resp = $this->request('GET', 'products/tlds');

        return $resp;


    }

    public function getDomainDetails($DomainName) {

        $resp = $this->request('GET', 'domains/info', ['DomainName' => $DomainName]);

        return $resp;

    }

    public function getContacts($code) {

        $resp = $this->request('GET', "domains/contacts/{$code}");

        return $resp;

    }

    public function modifyNameServer($DomainName, $NameServers) {

        /*
        $pattern = "/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9\-]{0,61}[a-z0-9]$/i";


        if(!is_array($NameServers)){
            return $this->customException('Lib.Validation', 'NameServers must be array');
        }

        foreach ($NameServers as $k => $v) {
            if (strlen($v) > 0) {
                if (!preg_match($pattern, $v)) {
                    return $this->customException('Lib.Validation', 'Invalid name server: ' . $v);
                }
            }else{
                unset($NameServers[$k]);
            }
        }

        if(count($NameServers) < 2){
            return $this->customException('Lib.Validation', 'NameServers must be at least 2');
        }
        */

        $resp = $this->request('PUT', "domains/dns/name-server", [
            'domainName'  => $DomainName,
            'nameServers' => $NameServers
        ]);

        return $resp;

    }

    public function enableTheftProtectionLock($DomainName) {

        $resp = $this->request('POST', "domains/lock", ['DomainName' => $DomainName]);

        return $resp;

    }

    public function disableTheftProtectionLock($DomainName) {
        $resp = $this->request('POST', "domains/unlock", ['DomainName' => $DomainName]);

        return $resp;
    }

    public function addChildNameServer($DomainName, $NameServer, $IPAdresses) {

        $iptype = filter_var($IPAdresses, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'v4' : 'v6';

        $payload = [
            'hostName'    => $NameServer . '.' . $DomainName,
            'ipAddresses' => [$IPAdresses => $iptype],
        ];

        $resp = $this->request('POST', "domains/dns/host", $payload);

        return $resp;

    }

    public function deleteChildNameServer($DomainName, $NameServer) {

        $payload = [
            'hostName' => $NameServer . '.' . $DomainName,
        ];

        $resp = $this->request('DELETE', "domains/dns/host", $payload);

        return $resp;

    }

    public function modifyChildNameServer($DomainName, $NameServer, $NewIPAdresses) {

        $newiptype = filter_var($NewIPAdresses, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'v4' : 'v6';

        $payload = [
            'hostName'          => $NameServer . '.' . $DomainName,
            'addIpAddresses'    => [$NewIPAdresses => $newiptype],
        ];

        $resp = $this->request('PUT', "domains/dns/host", $payload);

        return $resp;

    }

    public function saveContacts($DomainName, $Contacts) {

        $payload = [
            'domainName' => $DomainName,
            'contacts'   => [],
        ];

        $_keys = [
            'Administrative',
            'Billing',
            'Registrant',
            'Technical'
        ];

        foreach ($_keys as $k => $v) {
            $payload['contacts'][] = $this->parseContact($Contacts[$v], $v);
        }



        $resp = $this->request('PUT', "domains/contacts/update", $payload);

        return $resp;


    }

    public function transfer($DomainName, $AuthCode, $Period = 1, $Contacts=[]) {

        $payload = [
            'domainName' => $DomainName,
            'authCode'   => $AuthCode,
            'period'     => $Period,
            'contacts'   => [
                $this->parseContact($Contacts['Administrative'], 'Administrator'),
                $this->parseContact($Contacts['Billing'], 'Billing'),
                $this->parseContact($Contacts['Registrant'], 'Registrant'),
                $this->parseContact($Contacts['Technical'], 'Technical'),
            ],
        ];

        $resp = $this->request('POST', "domains/transfer", $payload);

        return $resp;

    }

    public function cancelTransfer($DomainName) {

    }

    public function approveTransfer($DomainName) {

    }

    public function rejectTransfer($DomainName) {

    }

    public function renew($DomainName, $Period) {
        $payload = [
            'domainName' => $DomainName,
            'period'     => $Period,
        ];

        $resp = $this->request('POST', "domains/renew", $payload);

        return $resp;
    }

    public function register(
        $DomainName, $Period, $Contacts, $NameServers = [
        "dns.domainnameapi.com",
        "web.domainnameapi.com"
    ]) {

        $payload = [
            'domainName'  => $DomainName,
            'period'      => $Period,
            'nameServers' => $NameServers,
            'contacts'    => [
                $this->parseContact($Contacts['Administrative'], 'Administrative'),
                $this->parseContact($Contacts['Billing'], 'Billing'),
                $this->parseContact($Contacts['Registrant'], 'Registrant'),
                $this->parseContact($Contacts['Technical'], 'Technical'),
            ],
        ];

        $resp = $this->request('POST', "domains/register", $payload);

        return $resp;

    }

    public function modifyPrivacyProtectionStatus($DomainName, $Status, $Reason = "Owner request") {

    }

    public function getForward($DomainName) {
        $payload = [
            'domainName' => $DomainName,
        ];

        $resp = $this->request('GET', "domains/forwards", $payload);

        return $resp;
    }

    public function setForward($DomainName, $forwardto) {
        $payload = [
            'domainName'      => $DomainName,
            'redirectAddress' => $forwardto,
            'forwardType'     => 'Temporary',
        ];

        $resp = $this->request('POST', "domains/forwards", $payload);

        return $resp;
    }

    public function getZoneRecords($DomainName) {
        $payload = [
            'domainName' => $DomainName,
        ];

        $resp = $this->request('GET', "domains/zones", $payload);

        return $resp;
    }

    public function addZoneRecord($DomainName, $Name, $Type, $Value, $TTL = 3600) {
        $payload = [
            "zoneStruct" => [
                "name"     => $Name,
                "ttl"      => $TTL,
                "type"     => $Type,
                "contents" => [$Value],
            ]
        ];

        $resp = $this->request('POST', "domains/zones?domainName={$DomainName}", $payload);

        return $resp;
    }

    public function modifyZoneRecord($DomainName, $OldName, $Name, $Type, $Value, $TTL = 3600) {
        $payload = [
            "zoneStruct" => [
                "name"     => $Name,
                "ttl"      => $TTL,
                "type"     => $Type,
                "contents" => [$Value],
            ]
        ];

        $resp = $this->request('PUT', "domains/zones?domainName={$DomainName}&recordName={$OldName}", $payload);

        return $resp;
    }

    public function deleteZoneRecord($DomainName, $Name, $Type, $Value) {
        $payload = [
            "domainName" => $DomainName,
            "Name"       => $Name.'.' . $DomainName,
            "RecordType" => $Type,
            "Record"     => $Value,
        ];

        $resp = $this->request('DELETE', "domains/zones", $payload);

        return $resp;
    }


}