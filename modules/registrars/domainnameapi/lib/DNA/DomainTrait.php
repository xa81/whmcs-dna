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

    public function getDomainDetails($domainName) {

        $resp = $this->request('GET', 'domains/info', ['DomainName' => $domainName]);

        return $resp;

    }

    public function getContacts($code) {

        $resp = $this->request('GET', "domains/contacts/{$code}");

        return $resp;

    }

    public function modifyNameServer($domainName, $NameServers) {

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
            'domainName'  => $domainName,
            'nameServers' => $NameServers
        ]);

        return $resp;

    }

    public function enableTheftProtectionLock($domainName) {

        $resp = $this->request('POST', "domains/lock", ['DomainName' => $domainName]);

        return $resp;

    }

    public function disableTheftProtectionLock($domainName) {
        $resp = $this->request('POST', "domains/unlock", ['DomainName' => $domainName]);

        return $resp;
    }

    public function addChildNameServer($domainName, $NameServer, $IPAdresses) {

        $iptype = filter_var($IPAdresses, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'v4' : 'v6';

        $payload = [
            'hostName'    => $NameServer . '.' . $domainName,
            'ipAddresses' => [$IPAdresses => $iptype],
        ];

        $resp = $this->request('POST', "domains/dns/host", $payload);

        return $resp;

    }

    public function deleteChildNameServer($domainName, $NameServer) {

        $payload = [
            'hostName' => $NameServer . '.' . $domainName,
        ];

        $resp = $this->request('DELETE', "domains/dns/host", $payload);

        return $resp;

    }

    public function modifyChildNameServer($domainName, $NameServer, $NewIPAdresses) {

        $newiptype = filter_var($NewIPAdresses, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'v4' : 'v6';

        $payload = [
            'hostName'          => $NameServer . '.' . $domainName,
            'addIpAddresses'    => [$NewIPAdresses => $newiptype],
        ];

        $resp = $this->request('PUT', "domains/dns/host", $payload);

        return $resp;

    }

    public function saveContacts($domainName, $Contacts) {

        $payload = [
            'domainName' => $domainName,
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

    public function transfer($domainName, $AuthCode, $Period = 1, $Contacts=[]) {

        $payload = [
            'domainName' => $domainName,
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

    public function cancelTransfer($domainName) {

    }

    public function approveTransfer($domainName) {

    }

    public function rejectTransfer($domainName) {

    }

    public function renew($domainName, $Period) {
        $payload = [
            'domainName' => $domainName,
            'period'     => $Period,
        ];

        $resp = $this->request('POST', "domains/renew", $payload);

        return $resp;
    }

    public function register($domainName, $Period, $Contacts, $NameServers = ["dns.domainnameapi.com", "web.domainnameapi.com"],$thieftProtection = true
    ,$privacyProtection = false,$addionalAttributes=[]) {

        $payload = [
            'domainName'  => $domainName,
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

    public function modifyPrivacyProtectionStatus($domainName, $Status) {
        $payload = [
            'domainName'    => $domainName,
            'privacyStatus' => $Status,
        ];

        $resp = $this->request('POST', "domains/privacy", $payload);

        return $resp;
    }

    public function getForward($domainName) {
        $payload = [
            'domainName' => $domainName,
        ];

        $resp = $this->request('GET', "domains/forwards", $payload);

        return $resp;
    }

    public function setForward($domainName, $forwardto) {
        $payload = [
            'domainName'      => $domainName,
            'redirectAddress' => $forwardto,
            'forwardType'     => 'Temporary',
        ];

        $resp = $this->request('POST', "domains/forwards", $payload);

        return $resp;
    }

    public function getZoneRecords($domainName) {
        $payload = [
            'domainName' => $domainName,
        ];

        $resp = $this->request('GET', "domains/zones", $payload);

        return $resp;
    }

    public function addZoneRecord($domainName, $Name, $Type, $Value, $TTL = 3600) {
        $payload = [
            "zoneStruct" => [
                "name"     => $Name,
                "ttl"      => $TTL,
                "type"     => $Type,
                "contents" => [$Value],
            ]
        ];

        $resp = $this->request('POST', "domains/zones?domainName={$domainName}", $payload);

        return $resp;
    }

    public function modifyZoneRecord($domainName, $OldName, $Name, $Type, $Value, $TTL = 3600) {
        $payload = [
            "zoneStruct" => [
                "name"     => $Name,
                "ttl"      => $TTL,
                "type"     => $Type,
                "contents" => [$Value],
            ]
        ];

        $resp = $this->request('PUT', "domains/zones?domainName={$domainName}&recordName={$OldName}", $payload);

        return $resp;
    }

    public function deleteZoneRecord($domainName, $Name, $Type, $Value) {
        $payload = [
            "domainName" => $domainName,
            "Name"       => $Name.'.' . $domainName,
            "RecordType" => $Type,
            "Record"     => $Value,
        ];

        $resp = $this->request('DELETE', "domains/zones", $payload);

        return $resp;
    }


}