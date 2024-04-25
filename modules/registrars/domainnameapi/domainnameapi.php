<?php
/**
 * Module WHMCS-DNA
 * @package DomainNameApi
 * @version 2.0.17
 */

use \WHMCS\Domain\TopLevel\ImportItem;
use \WHMCS\Domains\DomainLookup\ResultsList;
use \WHMCS\Domains\DomainLookup\SearchResult;
use \WHMCS\Module\Registrar\Registrarmodule\ApiClient;
use \WHMCS\Database\Capsule;


function domainnameapi_getConfigArray($params) {
    $configarray = [];

    $customfields = domainnameapi_parse_cache('config_settings', 512, function () {

        $customfields['no_field'] = 'There is no such as field in my system';

        $fields = Illuminate\Database\Capsule\Manager::table('tblcustomfields')
                                                     ->where('type', 'client')
                                                     ->pluck('fieldname', 'id');

        foreach ($fields as $k => $v) {
            $customfields[$k] = $v;
        }
        return $customfields;
    });

    if (class_exists("SoapClient")) {

        $sysMsg ='';

        if(strlen($params['API_UserName'])<1 ||  strlen($params['API_Password'])<1) {
            $sysMsg = 'Please enter your username and password';
        }else{

            $username = $params["API_UserName"];

            $password = $params["API_Password"];
            $resellerid = $params["API_ResellerID"];
            $sys = $params["API_SYSType"];


            $sysMsg = domainnameapi_parse_cache('user_'.$username.md5($password.$password.$resellerid.$sys), 100, function () use ($username, $password, $sys,$params) {
                $is_v2 = $sys === true || $sys == 'on';

                $oops_msg = "Username and password combination not correct<br>Don't have an Domain Name API account yet? Get one here: <a href='https://www.domainnameapi.com/become-a-reseller' target='_blank'>https://www.domainnameapi.com/become-a-reseller</a>";

                if (!$is_v2) {
                    $dna = domainnameapi_service($params);

                    $details = $dna->GetResellerDetails();


                    $sysMsg = '';

                    if ($details['result'] != 'OK') {
                        $sysMsg = $oops_msg;
                    } else {
                        $balances = [];
                        $sysMsg   = "<span style='color: brown;'>SYSv1</span> | User: <b>{$details['name']}({$details['id']})</b> , Balance: ";
                        foreach ($details['balances'] as $k => $v) {
                            $balances[] = "<b>{$v['balance']}{$v['symbol']}</b>";
                        }
                        $sysMsg .= implode(' | ', $balances);
                    }
                } else {
                    $dna = domainnameapi_service2($params);

                    $sysMsg = '';

                    //if ($dna === null) {
                    //    $sysMsg = $oops_msg;
                    //} else {
                        $details = $dna->getCurrentBalance();


                        if ($details['success'] !== true) {
                            $sysMsg = $oops_msg;
                        } else {
                            $balances = [];
                            $sysMsg   = "<span style='color: brown;'>SYSv2</span> | User: <b>{$details['resellerName']}({$details['id']})</b> , Balance: ";

                            if ($details['usdBalance'] > 0) {
                                $balances[] = "<b>{$details['usdBalance']}$</b>";
                            }
                            if ($details['tryBalance'] > 0) {
                                $balances[] = "<b>{$details['tryBalance']}₺</b>";
                            }


                            $sysMsg .= implode(' | ', $balances);
                        }
                    //}
                }

                return $sysMsg;

            });

        }

        $configarray = [
            "FriendlyName" => [
                "Type"  => "System",
                "Value" => "DomainNameAPI"
            ],
            "Description"  => [
                "Type"  => "System",
                "Value" => $sysMsg
            ],
            "API_UserName" => [
                "FriendlyName" => "Legacy System UserName",
                "Type"         => "text",
                "Size"         => "20",
                "Default"      => "ownername",
                'Description'  => 'Require for legacy system. It can be ignored after switching to the new system.',
            ],
            "API_Password" => [
                "FriendlyName" => "Legacy System Password",
                "Type"         => "password",
                "Size"         => "20",
                "Default"      => "ownerpass"
            ],
            "API_ResellerID" => [
                "FriendlyName" => "New System ResellerId",
                "Type"         => "text",
                "Size"         => "20",
                "Default"      => "",
                'Description'  => '',
            ],
            "API_SYSType" => [
                "FriendlyName" => "New System",
                "Type"         => "yesno",
                "Default"      => "no",
                "Description"  => "Have you migrated or using new system ? Then fill informations below."
            ],


            'TrIdendity'   => [
                'FriendlyName' => 'Turkish Identity',
                'Type'         => 'dropdown',
                'Options'      => $customfields,
                'Description'  => 'Turkish Identity Custom Field , required only .tr tld',
            ],
            'TrTaxOffice'  => [
                'FriendlyName' => 'Turkish Tax Office',
                'Type'         => 'dropdown',
                'Options'      => $customfields,
                'Description'  => 'Turkish Tax Office Custom Field , required only .tr tld',
            ],
            'TrTaxNumber'  => [
                'FriendlyName' => 'Turkish TaxNumber',
                'Type'         => 'dropdown',
                'Options'      => $customfields,
                'Description'  => 'Turkish TaxNumber Custom Field , required only .tr tld',
            ],
            'basecurrency' => [
                'FriendlyName' => 'Exchange Convertion For TLD Sync',
                'Type'         => 'dropdown',
                'Options'      => [
                    'no'  => 'Do Not Convert',
                    'TRY' => 'to TRY',
                    'EUR' => 'to EUR',
                    'IRR' => 'to IRR',
                    'INR' => 'to INR',
                    'PKR' => 'to PKR',
                    'CNY' => 'to CNY',
                    'AED' => 'to AED',
                ],
                'Description'  => 'Base Currency Convertion. <br><b>Strongly advice to not use this feature</b>. Using this feature means that you have read and fully understood the  <a href="https://github.com/domainreseller/whmcs-dna/blob/main/DISCLAIMER.md" target="_blank">DISCLAIMER AND WAIVER OF LIABILITY</a>'
            ],
        ];
    } else {
        return [
            "FriendlyName" => [
                "Type"  => "System",
                "Value" => "Domain Name API - ICANN Accredited Domain Registrar from TURKEY"
            ],
            "Description"  => [
                "Type"  => "System",
                "Value" => "<span style='color:red'>Your server does not support SOAPClient. Please install and activate it. <a href='http://php.net/manual/en/class.soapclient.php' target='_blank'>Detailed informations</a></span>"
            ],
        ];
    }

    return $configarray;
}

function domainnameapi_GetNameservers($params) {

    $is_v2       = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';
    $domain_name = $params['sld'] . '.' . $params['tld'];
    $values      = [];

    if ($is_v2) {
        $dna         = domainnameapi_service2($params);
        $result      = $dna->getDomainDetails($domain_name);
        $success     = $result["success"];
        $nameservers = $result["nameservers"] ?? null;
        $error       = $success ? null : $result["error"]["code"] . " - " . $result["error"]["message"];
    } else {
        $dna         = domainnameapi_service($params);
        $result      = $dna->GetDetails($domain_name);
        $success     = $result["result"] == "OK";
        $nameservers = $result["data"]["NameServers"] ?? null;
        $error       = $success ? null : $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }

    if ($success) {
        if (is_array($nameservers)) {
            foreach ([0,1,2,3,4] as $v) {
                if (isset($nameservers[$v])) {
                    $values["ns".($v+1)] = $nameservers[$v];
                }
            }
        } elseif ($nameservers !== null) {
            // Tek bir nameserver varsa
            $values["ns1"] = $nameservers;
        }
    } else {
        $values["error"] = $error;
    }

    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    return $values;
}

function domainnameapi_SaveNameservers($params) {
    $values      = $nsList = [];
    $domain_name = $params['sld'] . '.' . $params['tld'];
    $is_v2       = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    // Nameserver listesini hazırla
    foreach ([1, 2, 3, 4, 5] as $v) {
        if (isset($params["ns{$v}"]) && is_string($params["ns{$v}"]) && strlen(trim($params["ns{$v}"])) > 0) {
            $nsList[] = $params["ns{$v}"];
        }
    }

    // API sürümüne göre işlem yap
    if ($is_v2) {
        $dna    = domainnameapi_service2($params);
        $result = $dna->ModifyNameserver($domain_name, $nsList);

        if ($result["success"] === true) {
            $values = ["success" => true, "ns"      => $nsList];
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna    = domainnameapi_service($params);
        $result = $dna->ModifyNameserver($domain_name, $nsList);

        if ($result["result"] == "OK") {
            foreach ([0, 1, 2, 3, 4] as $v) {
                if (isset($result["data"]["NameServers"][0][$v])) {
                    $values["ns" . ($v + 1)] = $result["data"]["NameServers"][0][$v];
                }
            }
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    return $values;
}

function domainnameapi_GetRegistrarLock($params) {
    $values=[];

     $dna = domainnameapi_service($params);

    // Process request
    $result = $dna->GetDetails($params["sld"].".".$params["tld"]);

    if ($result["result"] == "OK") {
        if (isset($result["data"]["LockStatus"])) {

            if ($result["data"]["LockStatus"] == "true") {
                $values = "locked";
            } else {
                $values = "unlocked";
            }

        }
    } else {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }

    // Log request

    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );
    return $values;
}

function domainnameapi_SaveRegistrarLock($params) {
  $values=[];

    $dna = domainnameapi_service($params);

    // Get current lock status from registrar, Process request
    $result = $dna->GetDetails($params["sld"].".".$params["tld"]);


    if($result["result"] == "OK") {
        if(isset($result["data"]["LockStatus"])) {
            if($result["data"]["LockStatus"] == "true")
            {
                $kilit = "locked";
            } else {
                $kilit = "unlocked";
            }

            if($kilit == "unlocked") {
                // Process request
                $result = $dna->EnableTheftProtectionLock($params["sld"].".".$params["tld"]);
            } else {
                // Process request
                $result = $dna->DisableTheftProtectionLock($params["sld"].".".$params["tld"]);
            }

            if($result["result"] == "OK") {
                $values = ["success" => true];
            } else {
                $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
            }

        }
    }
    else
    {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }

    // Log request

    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );

    return $values;
}

function domainnameapi_RegisterDomain($params) {
 $values = [];
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';
    $nameServers = [];
    $period = 1;
    $privacyProtection = false;

    // Nameservers ayarlama
    foreach ([1,2,3,4,5] as $v) {
        if (isset($params["ns{$v}"]) && trim($params["ns{$v}"]) != "") {
            $nameServers[] = $params["ns{$v}"];
        }
    }

    // Kayıt süresini ve gizlilik korumasını ayarlama
    if (isset($params["regperiod"]) && is_numeric($params["regperiod"])) {
        $period = intval($params["regperiod"]);
    }
    if (isset($params["idprotection"]) && ($params["idprotection"] == true || trim($params["idprotection"]) == "1")) {
        $privacyProtection = true;
    }

    // API sürümüne göre işlemleri ayarlama
    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        $contactInfoFunction = 'domainnameapiv2_parse_clientinfo';
        $trContactFunction = 'domainnameapiv2_parse_trcontact';
    } else {
        $dna = domainnameapi_service($params);
        $contactInfoFunction = 'domainnameapi_parse_clientinfo';
        $trContactFunction = 'domainnameapi_parse_trcontact';
    }

    $additionalfields = $dna->isTrTLD($params["sld"] . "." . $params["tld"]) ? $trContactFunction($params) : [];

    // Alan adı kaydı
    $result = $dna->RegisterWithContactInfo(
        $params["sld"] . "." . $params["tld"],
        $period,
        [
            "Administrative" => $contactInfoFunction($params),
            "Billing" => $contactInfoFunction($params),
            "Technical" => $contactInfoFunction($params),
            "Registrant" => $contactInfoFunction($params),
        ],
        $nameServers,
        false,
        $privacyProtection,
        $additionalfields
    );

    // Sonuç işleme
    if ($result["result"] == "OK") {
        $values = ["success" => true];
    } else {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }



    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$params["API_UserName"], $params["API_Password"]]
    );

    return $values;
}

function domainnameapi_TransferDomain($params) {
$values = [];
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna    = domainnameapi_service2($params);
        $result = $dna->transfer($params["sld"] . "." . $params["tld"], $params["transfersecret"],
            $params['regperiod']);
        if ($result["success"] === true) {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna    = domainnameapi_service($params);
        $result = $dna->Transfer($params["sld"] . "." . $params["tld"], $params["transfersecret"],
            $params['regperiod']);
        if ($result["result"] == "OK") {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$params["API_UserName"], $params["API_Password"]]
    );


    return $values;
}

function domainnameapi_RenewDomain($params) {
    $values = [];
    $is_v2  = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $result = $dna->renew($params["sld"] . "." . $params["tld"], $params["regperiod"]);

        if ($result["success"] === true) {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna    = domainnameapi_service($params);
        $result = $dna->Renew($params["sld"] . "." . $params["tld"], $params["regperiod"]);

        if ($result["result"] == "OK") {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    return $values;
}

function domainnameapi_GetContactDetails($params) {
    $values = [];
    $is_v2  = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna    = domainnameapi_service2($params);
        $result = $dna->getDomainDetails($params['sld'] . '.' . $params['tld']);

        if ($result["success"] === true) {
            $values = [
                'RegistrantContact'     => domainnameapi_parse_contact($result["contacts"], "Registrant",true),
                'AdministrativeContact' => domainnameapi_parse_contact($result["contacts"], "Administrative",true),
                'BillingContact'        => domainnameapi_parse_contact($result["contacts"], "Billing",true),
                'TechnicalContact'      => domainnameapi_parse_contact($result["contacts"], "Technical",true),
            ];
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }

    }else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->GetContacts($params["sld"] . "." . $params["tld"]);

        if ($result["result"] == "OK") {
            $contact_arr = $result["data"]["contacts"];
            $values = [
                'RegistrantContact'     => domainnameapi_parse_contact($contact_arr,"Registrant"),
                'AdministrativeContact' => domainnameapi_parse_contact($contact_arr,"Administrative"),
                'BillingContact'        => domainnameapi_parse_contact($contact_arr,"Billing"),
                'TechnicalContact'      => domainnameapi_parse_contact($contact_arr,"Technical"),
            ];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    return $values;
}

function domainnameapi_SaveContactDetails($params) {
    $values = [];
    $is_v2  = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $result = $dna->saveContacts(

        // DOMAIN NAME
            $params["sld"] . "." . $params["tld"], [
                "Registrant"     => domainnameapi_parse_clientinfo($params["contactdetails"]["RegistrantContact"],true),
                "Administrative" => domainnameapi_parse_clientinfo($params["contactdetails"]["AdministrativeContact"],true),
                "Billing"        => domainnameapi_parse_clientinfo($params["contactdetails"]["BillingContact"],true),
                "Technical"      => domainnameapi_parse_clientinfo($params["contactdetails"]["TechnicalContact"],true),

            ]);

        if ($result["success"] === true) {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);
        // Process request
        $result = $dna->SaveContacts(

        // DOMAIN NAME
            $params["sld"] . "." . $params["tld"], [
                "Administrative" => domainnameapi_parse_clientinfo($params["contactdetails"]["AdministrativeContact"]),
                "Billing"        => domainnameapi_parse_clientinfo($params["contactdetails"]["BillingContact"]),
                "Technical"      => domainnameapi_parse_clientinfo($params["contactdetails"]["TechnicalContact"]),
                "Registrant"     => domainnameapi_parse_clientinfo($params["contactdetails"]["RegistrantContact"])

            ]);

        if ($result["result"] == "OK") {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );
    return $values;
}

function domainnameapi_GetEPPCode($params) {
    $values = [];
    $is_v2  = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        // Process request
        $result = $dna->getDomainDetails($params["sld"] . "." . $params["tld"]);

        if ($result["success"] === true) {
            if (isset($result["authCode"])) {
                $values["eppcode"] = $result["authCode"];
            } else {
                $values["error"] = "EPP Code can not reveived from registrar!";
            }
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->GetDetails($params["sld"] . "." . $params["tld"]);

        if ($result["result"] == "OK") {
            if (isset($result["data"]["AuthCode"])) {
                $values["eppcode"] = $result["data"]["AuthCode"];
            } else {
                $values["error"] = "EPP Code can not reveived from registrar!";
            }
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }




    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );
    return $values;
}

function domainnameapi_RegisterNameserver($params) {
    $values=[];

    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        // Process request
        $result = $dna->AddChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"],
            $params["ipaddress"]);

        if ($result["success"] === true) {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->AddChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"],
            $params["ipaddress"]);

        if ($result["result"] == "OK") {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }



    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );
    return $values;
}

function domainnameapi_ModifyNameserver($params)
{
    $values = [];
    $is_v2  = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        // Process request
        // Process request
        $result = $dna->ModifyChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"],
            $params["newipaddress"]);

        if ($result["success"] === true) {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        $result = $dna->ModifyChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"],
            $params["newipaddress"]);

        if ($result["result"] == "OK") {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }


    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );
    return $values;
}

function domainnameapi_DeleteNameserver($params) {
    $values=[];
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        // Process request
        $result = $dna->DeleteChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"]);


        if ($result["success"] === true) {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->DeleteChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"]);

        if ($result["result"] == "OK") {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }



    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    return $values;
}

function domainnameapi_IDProtectToggle($params) {
 $values=[];

    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $values["error"] = 'This version not supported ID Protect';
    } else {
        $dna = domainnameapi_service($params);

        if ($params["protectenable"]) {
            // Process request
            $result = $dna->ModifyPrivacyProtectionStatus($params["sld"] . "." . $params["tld"], true,
                "Owner\'s request");
        } else {
            // Process request
            $result = $dna->ModifyPrivacyProtectionStatus($params["sld"] . "." . $params["tld"], false,
                "Owner\'s request");
        }

        if ($result["result"] == "OK") {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }

    // Log request



    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );
    return $values;
}

function domainnameapi_GetDNS($params)
{
    $values = [];

    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if($is_v2){

    }

    $values["error"] = "DNS Management does not supported by Domain Name API.";


    return $values;
}

function domainnameapi_SaveDNS($params)
{
    $values["error"] = "DNS Management does not supported by Domain Name API!!!";


    return $values;
}

function domainnameapi_CheckAvailability($params) {
    $values=[];

    // Sistem versiyonunu kontrol et
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    // Versiyona göre uygun servis fonksiyonunu başlat
    $dna = $is_v2 ? domainnameapiv2_service($params) : domainnameapi_service($params);

    if($params['isIdnDomain']){
        $label = empty($params['punyCodeSearchTerm']) ? strtolower($params['searchTerm']) : strtolower($params['punyCodeSearchTerm']);
    }else{
        $label = strtolower($params['searchTerm']);
    }

    $tldslist = $params['tldsToInclude'];
    $premiumEnabled = (bool) $params['premiumEnabled'];
    $domainslist = [];
    $results = new \WHMCS\Domains\DomainLookup\ResultsList();
    $all_tlds = [];

    foreach ($tldslist as $tld) {
        $all_tlds[] = ltrim($tld, '.');
    }

    // Versiyona göre uygun CheckAvailability fonksiyonunu çağır
    if ($is_v2) {
        $result = $dna->CheckAvailability([$label], $all_tlds);
    } else {
    $result = $dna->CheckAvailability([$label],$all_tlds,"1","create");

    $exchange_rates = domainnameapi_exchangerates();
    }

    foreach ($result as $v) {
        $searchResult = new SearchResult($label, '.'.$v['TLD']);

        $register_price = $v['Price'];
        $renew_price = $v['Price'];

        if (!$is_v2 && strpos($v['TLD'], '.tr') !== false) {
            $register_price = $register_price / $exchange_rates['TRY'];
            $renew_price = $renew_price / $exchange_rates['TRY'];
        }



        if ($v['Status'] == 'available') {

            $status = SearchResult::STATUS_NOT_REGISTERED;
            $searchResult->setStatus($status);

            if ($v['IsFee'] == '1' || ($is_v2 && $v['info']['isPremium'] == '1')) {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing([
                        'register'     => $register_price,
                        'renew'        => $renew_price,
                        'CurrencyCode' => 'USD',
                    ]);
            }

        }else{
            $status = SearchResult::STATUS_REGISTERED;
            $searchResult->setStatus($status);
        }
      $results->append($searchResult);
    }


    logModuleCall(
        "domainnameapi",
        substr(__FUNCTION__, 16),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );


    return $results;
}


function domainnameapi_GetDomainSuggestions($params) {
    $values = [];

    // Sistem versiyonunu kontrol et
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    // Versiyona göre uygun servis fonksiyonunu başlat
    $dna = $is_v2 ? domainnameapiv2_service($params) : domainnameapi_service($params);

    if ($params['isIdnDomain']) {
        $label = empty($params['punyCodeSearchTerm']) ? strtolower($params['searchTerm']) : strtolower($params['punyCodeSearchTerm']);
    } else {
        $label = strtolower($params['searchTerm']);
    }

    $tldslist = $params['tldsToInclude'];
    $premiumEnabled = (bool) $params['premiumEnabled'];
    $domainslist = [];
    $results = new \WHMCS\Domains\DomainLookup\ResultsList();
    $all_tlds = [];

    foreach ($tldslist as $tld) {
        $all_tlds[] = ltrim($tld, '.');
    }

    // Versiyona göre uygun CheckAvailability fonksiyonunu çağır
    $result = $dna->CheckAvailability([$label], $all_tlds, "1", "create");

    if (!$is_v2) {
        $exchange_rates = domainnameapi_exchangerates();
    }

    foreach ($result as $v) {
        $searchResult = new SearchResult($label, '.' . $v['TLD']);
        $register_price = isset($v['Price']) ? $v['Price'] : $v['info']['price'];
        $renew_price = isset($v['Price']) ? $v['Price'] : $v['info']['price'];

        if (!$is_v2 && strpos($v['TLD'], '.tr') !== false) {
            $register_price = $register_price / $exchange_rates['TRY'];
            $renew_price = $renew_price / $exchange_rates['TRY'];
        }

        $available = $is_v2 ? ($v['info']['status'] == 'AVAILABLE') : ($v['Status'] == 'available');
        $isPremium = $is_v2 ? $v['info']['isPremium'] : $v['IsFee'];

        if ($available) {
            $status = SearchResult::STATUS_NOT_REGISTERED;
            $searchResult->setStatus($status);

            if ($isPremium == '1') {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing([
                    'register'     => $register_price,
                    'renew'        => $renew_price,
                    'CurrencyCode' => 'USD',
                ]);
            }
        } else {
            $status = SearchResult::STATUS_REGISTERED;
            $searchResult->setStatus($status);
        }

        $results->append($searchResult);
    }


    logModuleCall(
        "domainnameapi",
        substr(__FUNCTION__, 16),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );


    return $results;
}


function domainnameapi_GetTldPricing($params) {
    // Perform API call to retrieve extension information
    // A connection error should return a simple array with error key and message
    // return ['error' => 'This error occurred',];
$values = [];
     $dna = domainnameapi_service($params);

    $tldlist = $dna->GetTldList(1200);

    $convertable_currencies = domainnameapi_exchangerates();

    $results = new ResultsList;

    if ($tldlist['result'] == 'OK') {
        foreach ($tldlist['data'] as $extension) {
            if(strlen($extension['tld'])>1){

                $price_registration = $extension['pricing']['registration'][1];
                $price_renew        = $extension['pricing']['renew'][1];
                $price_transfer     = $extension['pricing']['transfer'][1];
                $current_currency   = $extension['currencies']['registration'];

                if($current_currency=='TL'){
                    $current_currency='TRY';
                }


                if (in_array($params["basecurrency"],array_keys($convertable_currencies) )) {

                    $exchange_rate     = $convertable_currencies[$params["basecurrency"]];
                    $exchange_rate_rev = $convertable_currencies['TRY'];

                    if ($current_currency == 'USD') {
                        $exchange_rate_rev = 1;
                    }

                    $price_registration = $price_registration * $exchange_rate / $exchange_rate_rev;
                    $price_renew        = $price_renew * $exchange_rate / $exchange_rate_rev;
                    $price_transfer     = $price_transfer * $exchange_rate / $exchange_rate_rev;

                    $current_currency   = $params["basecurrency"];
                }


                $tlds[] = $extension['tld'];

                $item      = (new ImportItem)->setExtension(trim($extension['tld']))
                                             ->setMinYears($extension['minperiod'])
                                             ->setMaxYears($extension['maxperiod'])
                                             ->setRegisterPrice($price_registration)
                                             ->setRenewPrice($price_renew)
                                             ->setTransferPrice($price_transfer)
                                             ->setCurrency($current_currency);

                $results[] = $item;

            }
        }
    }

    return $results;
}

function domainnameapi_Sync($params) {

$values=[];
    $dna = domainnameapi_service($params);

    // Process request
    $result = $dna->SyncFromRegistry($params["sld"].".".$params["tld"]);


    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );

    if($result["result"] == "OK") {
        // Process request

        //Active
        //ConfirmationEmailSend
        //WaitingForDocument
        //WaitingForIncomingTransfer
        //WaitingForOutgoingTransfer
        //WaitingForRegistration
        //PendingDelete
        //PreRegistiration
        //PendingHold
        //MigrationPending
        //ModificationPending



        $result2 = $dna->GetDetails($params["sld"].".".$params["tld"]);

        if ($result2["result"] == "OK") {
            $active       ='';
            $expired      ='';
            $expiration   ='';
            $transferaway ='';

            // Check results
            if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $result2["data"]["Dates"]["Expiration"])) {
                $expiration = substr($result2["data"]["Dates"]["Expiration"], 0, 10);
            }
            if ($result2["data"]["Status"] == "Active") {
                $active  = true;
                $expired = false;
                $transferaway=false;
            }
            if (in_array($result2["data"]["Status"],['PendingDelete','Deleted'])) {
                $expired = true;
                $active  = false;
                $transferaway=false;
            }
            if ($result2["data"]["Status"] == "TransferredOut") {
                $expired = true;
                $active  = false;
                $transferaway=true;
            }


            // If result is valid set it to WHMCS
            if (is_bool($active) && is_bool($expired)&& trim($expiration) != ""&&is_bool($transferaway) ) {
                $values["active"]     = $active;
                $values["expired"]    = $expired;
                $values["expirydate"] = $expiration;
                //$values["success"] = true;
            } else {
                $values["error"] = "Unexpected result returned from registrar" . "\nActive: " . $active . "\nExpired: " . $expired . "\nExpiryDate: " . $expiration;
            }

        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }

    } else {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }


    // Log request
    logModuleCall("domainnameapi",
        "GetDetails_FROM_".substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );

    return $values;
}

function domainnameapi_TransferSync($params) {
 $values=[];

     $dna = domainnameapi_service($params);

    // Process request
    $result = $dna->SyncFromRegistry($params["sld"].".".$params["tld"]);


    // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );

    if($result["result"] == "OK") {




        $result2 = $dna->GetDetails($params["sld"].".".$params["tld"]);

        if ($result2["result"] == "OK") {



            // Check results
            if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $result2["data"]["Dates"]["Expiration"])) {
                $values['expirydate'] = date('Y-m-d',strtotime($result2["data"]["Dates"]["Expiration"]));
            }
            if ($result2["data"]["Status"] == "Active") {
                $values['completed']=true;
                $values['failed']=false;
            }
            if (in_array($result2["data"]["Status"],['PendingDelete','Deleted'])) {
                $expired = true;
                $active  = false;
                $transferaway=false;
            }
            if ($result2["data"]["Status"] == "TransferCancelledFromClient") {
                $values['completed']=true;
                $values['failed']=false;
                $values['reason']='Transfer Cancelled From Client';
            }




        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }

    } else {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }


    // Log request
    logModuleCall("domainnameapi",
        "GetDetails_FROM_".substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );

    return $values;
}

function domainnameapi_AdminCustomButtonArray() {
    return [
        "Cancel Transfer" => "canceltransfer",
    ];
}

function domainnameapi_canceltransfer($params) {

    $values=[];
    $dna = domainnameapi_service($params);

    $result = $dna->CancelTransfer($params["sld"] . "." . $params["tld"]);

    if ($result["result"] == "OK") {
        $values["message"] = "Successfully cancelled the domain transfer";
    } else {
        $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
    }

     // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
        [$username,$password]
    );


    return $values;

}

function domainnameapi_AdminDomainsTabFields($params){


    $regs = Illuminate\Database\Capsule\Manager::table('tblregistrars')->where('registrar', 'domainnameapi')->get();

    foreach ($regs as $k => $v) {
        $results = localAPI('DecryptPassword', ['password2'=>$v->value]);
        $params[$v->setting] = $results['password'];
    }

    $values=[];

    $dna = domainnameapi_service($params);


    // Process request
    $result = $dna->GetDetails($params["sld"].".".$params["tld"]);

    $addionals = $nameservers='';

    foreach ($result['data']['Additional'] as $k => $v) {
        $addionals.= $k.' : '.$v.'<br>';
    }
    foreach ($result['data']['ChildNameServers'] as $k => $v) {
        $nameservers.= '['.$v['ip'].'] : '.$v['ns'].'<br>';
    }

    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $result,
        [$username,$password]
    );

    return [
        'Current State'      => $result['data']['Status'],
        'Start'              => $result['data']['Dates']['Start'],
        'Expiring'           => $result['data']['Dates']['Expiration'],
        'Remaining Days'     => $result['data']['Dates']['RemainingDays'],
        'Child Nameservers'  => $nameservers,
        'AddionalParameters' => $addionals,
    ];

}


function domainnameapi_parse_contact($params,$contacttype='Registrant',$is_v2=false) {

     if ($is_v2) {
        // Yeni API için iletişim bilgileri yapısı
        $filteredContacts = array_filter($params['contacts'], function ($contact) use ($contacttype) {
            return $contact['contactType'] == $contacttype;
        });
        $contact = reset($filteredContacts) ?: []; // Eğer $filteredContacts boşsa, boş bir dizi döndür
    } else {
        // Eski API için iletişim bilgileri yapısı
        $contact = $params[$contacttype];
    }

    // Ortak dönüş yapısı, veri kaynağına göre ayarlanır
    return [
        "First Name"         => $contact["firstName"] ?? $contact["FirstName"],
        "Last Name"          => $contact["lastName"] ?? $contact["LastName"],
        "Company Name"       => $contact["companyName"] ?? $contact["Company"],
        "Email"              => $contact["eMail"] ?? $contact["EMail"],
        "Phone Country Code" => $is_v2 ? $contact["phoneCountryCode"] : $contact["Phone"]["CountryCode"],
        "Phone"              => $is_v2 ? $contact["phone"] : $contact["Phone"]["Number"],
        "Fax Country Code"   => $is_v2 ? $contact["faxCountryCode"] : $contact["Fax"]["CountryCode"],
        "Fax"                => $is_v2 ? $contact["fax"] : $contact["Fax"]["Number"],
        "Address 1"          => $contact["address"] ?? $contact["Address"]["Line1"],
        "Address 2"          => $contact["addressLine2"] ?? $contact["Address"]["Line2"], // Opsiyonel, eski API'de yoksa boş geç
        "State"              => $contact["state"] ?? $contact["Address"]["State"],
        "City"               => $contact["city"] ?? $contact["Address"]["City"],
        "Country"            => $contact["country"] ?? $contact["Address"]["Country"],
        "ZIP Code"           => $contact["postalCode"] ?? $contact["Address"]["ZipCode"]
    ];

}

function domainnameapi_parse_clientinfo($params,$is_v2=false) {


    $firstname   = $params["First Name"] ?? $params["firstname"];
    $lastname    = $params["Last Name"] ?? $params["lastname"];
    $companyname = $params["Company Name"] ?? $params["companyname"];
    $email       = $params["Email"] ?? $params["email"];
    $address1    = $params["Address 1"] ?? $params["address1"];
    $address2    = $params["Address 2"] ?? $params["address2"];
    $city        = $params["City"] ?? $params["city"];
    $country     = $params["Country"] ?? $params["countrycode"];
    $fax         = $params["Fax"] ?? $params["phonenumber"];
    $faxcc       = $params["Fax Country Code"] ?? $params["phonecc"];
    $phonecc     = $params["Phone Country Code"] ?? $params["phonecc"];
    $phone       = $params["Phone"] ?? $params["phonenumber"];
    $postcode    = $params["ZIP Code"] ?? $params["postcode"];
    $state       = $params["State"] ?? $params["state"];

    if ($is_v2) {
        // Yeni API için anahtar adlarını kullan
        $arr_client = [
            "firstName"        => $firstname,
            "lastName"         => $lastname,
            "companyName"      => $companyname,
            "eMail"            => $email,
            "address"          => $address1 . " " . $address2, // Adresleri birleştir
            "state"            => $state,
            "city"             => $city,
            "country"          => $country,
            "fax"              => $fax,
            "faxCountryCode"   => $faxcc,
            "phone"            => $phone,
            "phoneCountryCode" => $phonecc,
            "postalCode"       => $postcode,
        ];
    } else {
        // Eski API için anahtar adlarını kullan
        $arr_client = [
            "FirstName"        => $firstname,
            "LastName"         => $lastname,
            "Company"          => $companyname,
            "EMail"            => $email,
            "AddressLine1"     => $address1,
            "AddressLine2"     => $address2,
            "State"            => $state,
            "City"             => $city,
            "Country"          => $country,
            "Fax"              => $fax,
            "FaxCountryCode"   => $faxcc,
            "Phone"            => $phone,
            "PhoneCountryCode" => $phonecc,
            "ZipCode"          => $postcode,
        ];
        if (isset($params['FirstName'])) {
            $arr_client['Status'] = ""; // Eğer FirstName parametresi varsa, Status ekleyin
        }
    }

    // Tüm değerleri UTF-8'e dönüştür
    foreach ($arr_client as $key => $value) {
        $arr_client[$key] = mb_convert_encoding($value, "UTF-8", "auto");
    }

    return $arr_client;
}



function domainnameapi_parse_trcontact($contactDetails) {
    $cf = [];
    foreach ($contactDetails['customfields'] as $k => $v) {
        $cf[$v['id']] = $v['value'];
    }

    $tr_domain_fields = [
        'TRABISDOMAINCATEGORY' => strlen($contactDetails['companyname']) > 0 ? '0' : '1',
        'TRABISNAMESURNAME'    => $contactDetails['firstname'] . ' ' . $contactDetails['lastname'],
        'TRABISCOUNTRYID'      => 215,
        'TRABISCITYID'        => 34,
        'TRABISCOUNTRYNAME'    => $contactDetails['countrycode'],
        'TRABISCITYNAME'       => $contactDetails['city'],
    ];

    $tr_domain_fields['TRABISORGANIZATION'] = $contactDetails['companyname'];
    $tr_domain_fields['TRABISTAXOFFICE']    = is_numeric($contactDetails['TrTaxOffice']) ? $cf[$contactDetails['TrTaxOffice']] : 'Kadikoy V.D.';
    $tr_domain_fields['TRABISTAXNUMBER']    = is_numeric($contactDetails['TrTaxNumber']) ? $cf[$contactDetails['TrTaxNumber']] : '1111111111';
    $tr_domain_fields['TRABISCITIZIENID']   = is_numeric($contactDetails['TrIdendity']) ? $cf[$contactDetails['TrIdendity']] : '11111111111';

    if (strlen($contactDetails['companyname'])<1 ) {
        unset($tr_domain_fields['TRABISORGANIZATION']);
        unset($tr_domain_fields['TRABISTAXOFFICE']);
        unset($tr_domain_fields['TRABISTAXNUMBER']);
    } else {
        unset($tr_domain_fields['TRABISNAMESURNAME']);
        unset($tr_domain_fields['TRABISCITIZIENID']);
    }

    return $tr_domain_fields;
}

function domainnameapi_parse_cache($key,$ttl,$callback){

    //Long usernames can cause issues with the tblconfiguration table setting column
    $cache_key = "domainnameapi_".md5($key); //Exact 46 character

    $token_row = Capsule::table('tblconfiguration')
                        ->where('setting', $cache_key)
                        ->first();

    //if module newly installed, create token row
    //token row could be object pattern or empty, not false
    if (!isset($token_row->setting)) {
        try {
            Capsule::table('tblconfiguration')
                   ->insert([
                       'setting' => $cache_key,
                       'value'   => ''
                   ]);
        } catch (\Exception $e) {
            //throw new Exception('Error: Record enumaration failed.');
        }
    }

    if (strtotime($token_row->updated_at) < (time() - 600)) {

        $data = $callback();

        Capsule::table('tblconfiguration')
               ->where('setting', $cache_key)
               ->update([
                   'value'      => serialize($data),
                   'updated_at' => date('Y-m-d H:i:s', strtotime("+{$ttl} seconds"))
               ]);

        return $data;

    }else{
        return unserialize($token_row->value);
    }

}

/*
function domainnameapi_exchangerates() {
    $url   = 'https://www.tcmb.gov.tr/kurlar/today.xml';
    $xml   = simplexml_load_file($url);
    $rates = [];
    foreach ($xml->Currency as $k => $v) {

        $name = (string)$v->attributes()->Isim;
        $code = (string)$v->attributes()->CurrencyCode;


        $CrossRateUSD   = (string)$v->CrossRateUSD;
        $CrossRateOther = (string)$v->CrossRateOther;
        $forex_selling  = (string)$v->ForexSelling;

        if (!isset($rates[$code])) {
            if (strlen($CrossRateOther) > 0) {
                $rates[$code] = $CrossRateOther;
            } elseif (strlen($CrossRateUSD) > 0) {
                $rates[$code] = $CrossRateUSD;
            } else {
                $rates[$code] = $forex_selling;
            }
        }
    }
    $rates['TRY'] = $rates['USD'];
    unset($rates['USD']);
    unset($rates['XDR']);
    return $rates;
}
*/

function domainnameapi_exchangerates() {
    $rates = [];

    $rates = domainnameapi_parse_cache('currency_data', 1800, function () {

        $url = 'https://open.er-api.com/v6/latest/USD';
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($json);

        if (!isset($data->rates)) {
            throw new Exception('Error: Exchange service is not available . Please Wait few minutes and try again. ');
        }

        return $data->rates;
    });


    $rates = json_decode(json_encode($rates), true);


    return $rates;
}


function domainnameapi_service($params) {
    require_once __DIR__.'/lib/dna.php';

    $username = $params["API_UserName"];
    $password = $params["API_Password"];

    $dna = new \DomainNameApi\DomainNameAPI_PHPLibrary($username,$password);

    return $dna;
}
function domainnameapi_service2($params) {

    if (!trait_exists('DNA\ModifierTrait')) {
        require_once __DIR__ . '/lib/DNA/ModifierTrait.php';
        require_once __DIR__ . '/lib/DNA/DomainTrait.php';
        require_once __DIR__ . '/lib/DNA/Client.php';
        require_once __DIR__ . '/lib/DNA/ServiceFactory.php';
        require_once __DIR__ . '/lib/DNA/SSLTrait.php';
        require_once __DIR__ . '/lib/DNA/Service.php';
    }



    $username   = $params["API_UserName"];
    $password   = $params["API_Password"];
    $resellerid = $params["API_ResellerId"];

    $service=null;


    $reseller_token = domainnameapi_parse_cache('auth_token_'.md5($username.$password.$resellerid), 1800, function () use ($username, $password, $resellerid) {

        $service = \DNA\ServiceFactory::createWithCredentials($username, $password, $resellerid);

        if($service->isAuthenticated()) {
            return $service->getToken();
        }else{
            return false;
        }
    });


    $service = \DNA\ServiceFactory::createWithToken($reseller_token,$resellerid);


    return $service;

}

