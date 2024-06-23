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

    domainnameapi_checkV2($params);

    $is_v2       = domainnameapi_checkV2($params);
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
    $is_v2       = domainnameapi_checkV2($params);


    foreach ([1, 2, 3, 4, 5] as $k => $v) {
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
                    $values["ns" . ($v + 1)] = $result["data"]["NameServers"][$v];
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
    $values      = [];
    $domain_name = $params['sld'] . '.' . $params['tld'];
    $is_v2       = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna    = domainnameapi_service2($params);
        $result = $dna->getDomainDetails($domain_name);

        if ($result["success"] === true) {
            if (isset($result["lockStatus"])) {
                $values = $result["lockStatus"] === true ? "locked" : "unlocked";
            }
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->GetDetails($domain_name);

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
    }



    // Log request

    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values,
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
    $is_v2 = domainnameapi_checkV2($params);
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

    $domainName =$params["sld"] . "." . $params["tld"];

    $contacts = [
            "Administrative" => domainnameapi_parse_clientinfo($params, $is_v2),
            "Billing"        => domainnameapi_parse_clientinfo($params, $is_v2),
            "Technical"      => domainnameapi_parse_clientinfo($params, $is_v2),
            "Registrant"     => domainnameapi_parse_clientinfo($params, $is_v2),
    ];

    // if last 3 char is ".tr"



    $additionalfields = substr($domainName, -3) == ".tr" ? domainnameapi_parse_trcontact($params) : [];


    // API sürümüne göre işlemleri ayarlama
    if ($is_v2) {
        $dna                 = domainnameapi_service2($params);
        $contactInfoFunction = 'domainnameapiv2_parse_clientinfo';
        $trContactFunction   = 'domainnameapiv2_parse_trcontact';
    } else {
        $dna    = domainnameapi_service($params);
        $result = $dna->RegisterWithContactInfo($domainName, $period, $contacts, $nameServers, false, $privacyProtection, $additionalfields);

        // Sonuç işleme
        if ($result["result"] == "OK") {
            $values = ["success" => true];
        } else {
            $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
        }
    }


    // Alan adı kaydı




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
    $is_v2 = domainnameapi_checkV2($params);

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
    $is_v2  = domainnameapi_checkV2($params);

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
    $is_v2  = domainnameapi_checkV2($params);

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
    $is_v2  = domainnameapi_checkV2($params);

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
    $is_v2  = domainnameapi_checkV2($params);

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

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        // Process request
        $result = $dna->AddChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"], $params["ipaddress"]);

        if ($result["success"] === true) {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->AddChildNameServer($params["sld"] . "." . $params["tld"], $params["nameserver"], $params["ipaddress"]);

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
    $is_v2  = domainnameapi_checkV2($params);

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
    $is_v2 = domainnameapi_checkV2($params);

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
    $domain_name = $params["sld"] . "." . $params["tld"];

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);
        $result= $dna->modifyPrivacyProtectionStatus($domain_name, $params["protectenable"]);

        if ($result["success"] === true) {
            $values["success"] = true;
        } else {
            $values["error"] = $result["error"]["code"] . " - " . $result["error"]["message"];
        }
    } else {
        $dna = domainnameapi_service($params);

        $result = $dna->ModifyPrivacyProtectionStatus($domain_name, $params["protectenable"]);

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

function domainnameapi_GetDNS($params)
{
    $values = [];

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $_domain = $params["sld"] . "." . $params["tld"];

        // Process request
        $forward = $dna->getForward($_domain);

        $zone_records = $dna->getZoneRecords($_domain);



        $ri = 0;
        if ($zone_records["success"] === true) {

            foreach ($zone_records as $k => $v) {
                if (isset($v['records']) && count($v['records']) > 1) {
                    array_shift($v['records']);  // İlk elemanı dizi içerisinden çıkar
                    foreach ($v['records'] as $record) {
                        $zone_records[] = ['records' => [$record]] + $v;
                    }
                }
            }


            foreach ($zone_records as $k => $v) {
                if (is_numeric($k)) {
                    if (in_array($v['type'],['SOA','NS']) || strpos($v["records"][0]['content'],'forward-verification:' ) !== false) {
                        continue;
                    }

                    $hostname = $v["name"];
                    if($hostname != $_domain."."){
                        $hostname = str_replace(".{$_domain}.",'', $hostname);
                    }else{
                        $hostname = '@';
                    }

                    $values[$ri]["hostname"] = $hostname;
                    $values[$ri]["ttl"]      = $v["ttl"];
                    $values[$ri]["type"]     = $v["type"];
                    $values[$ri]["address"]  = $v["records"][0]['content'];
                    $values[$ri]["recid"]    = md5($v["name"] . $v["type"]);

                    if($v["type"] == 'MX'){
                        $rec_pattern = explode(' ',$v["records"][0]['content']);
                        $values[$ri]["priority"] = $rec_pattern[0];
                        $values[$ri]["address"] = $rec_pattern[1];
                    }
                    if($v["type"] == 'TXT'){
                        $values[$ri]["address"] = str_replace('"','',$v["records"][0]['content']);
                    }

                    $ri++;
                }
            }
        } else {
            $values["error"] = $zone_records["error"]["code"] . " - " . $zone_records["error"]["message"];
        }

        if($forward["success"] === true) {
            if(isset($forward['redirectAlias'])){
                $values[$ri]["type"] = 'URL';
                $values[$ri]["hostname"] = '';
                $values[$ri]["ttl"]      = 100;
                $values[$ri]["address"] = $forward['redirectAlias'];
                $values[$ri]["recid"]    = 'redirect';
            }
        }
        // Log request
    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );

    } else {
        $values["error"] = "DNS Management does not supported by Domain Name API.";
    }


    return $values;
}

function domainnameapi_SaveDNS($params)
{
    $values = $records = $requests = $responses =$pointers = [];

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $_domain = $params["sld"] . "." . $params["tld"];

        $requests['domain'] = $params["sld"] . "." . $params["tld"];

        // Process request
        $result_domainlist = $dna->getZoneRecords($_domain);



        if ($result_domainlist["success"] === true) {

            foreach ($result_domainlist as $k => $v) {
                if (is_numeric($k)) {
                    $records[md5($v["name"] . $v["type"])] = $k;
                    $pointers[$v["name"] . '__' . $v["type"]] = implode('|', array_column($v['records'], 'content'));

                }
            }

            $record_bulk =$processed = [];

            foreach ($params['dnsrecords'] as $k => $v) {
                // Hostname işlemleri
                if ($v['hostname'] == '@') {
                    $host = $_domain . '.';
                } else {
                    $host = ltrim($v['hostname'] . '.' . $_domain . '.', '.');
                }
                $params['dnsrecords'][$k]['hostname'] =$v['hostname']= $host;

                // MX kayıtları için priority düzenlemesi
                if ($v['type'] == 'MX') {
                    $v['priority'] = $v['priority'] > 0 ? $v['priority'] : 10;
                    if(strlen($v['address'])>0){
                       $v['address']  = $v['priority'] . ' ' . $v['address'];
                    }else{
                        $v['address']  ='';
                    }

                }

                // TXT kayıtları için adres düzenlemesi
                if ($v['type'] == 'TXT') {
                    $v['address'] = str_replace('"', '', $v['address']);
                }
                $v['key'] = $v["hostname"] .'__'. $v["type"];

                // Gruplama ve adres birleştirme
                $key = $host . '|' . $v['type'];
                if (isset($processed[$key])) {
                    if(strlen($v['address'])>0){
                       $processed[$key]['address'] .= '|' . $v['address'];
                    }
                } else {
                    $processed[$key] = $v;
                }
            }

            $params['dnsrecords'] = array_values($processed);



            $values = [];

            foreach ($params['dnsrecords'] as $k => $v) {

                $hostname = $v['hostname'];

                $_name    = rtrim(ltrim(str_replace($_domain, '', $hostname), '.'), '.');
                $type     = $v['type'];
                $address  = $v['address'];
                $priority = $v['priority'];
                $recid    = $v['recid'];
                $key      = $v['key'];


                $api_response = $request = $response = [];


                if(!in_array($type,['URL','FRAME','REDIRECT'])) {

                    $request['recid']=$recid;
                    $api_parsed=[];

                    $_t =false;
                    $current_record=null;

                    $record_name=null;


                    if (!in_array($key,array_keys($pointers)) && $address != '' ) {
                        //add record
                        $api_response = $dna->addZoneRecord($_domain, $_name, $type, $address);
                        $_t=true;
                        $request['type']='add';
                    }else{

                        $current_record = $result_domainlist[$records[$recid]];
                        $record_name    = rtrim(ltrim(str_replace([".{$_domain}",$_domain], '', $current_record['name']), '.'), '.');


                        if ($address == '' && in_array($key, array_keys($pointers))) {
                            //Sil
                            $api_response    = $dna->deleteZoneRecord($_domain, $record_name, $type, $current_record['records'][0]['content']);
                            $_t              = true;
                            $request['type'] = 'delete';
                        } elseif ($address != $pointers[$key]) {
                            //Güncelle
                            $api_response    = $dna->modifyZoneRecord($_domain, $record_name, $_name, $type, $address);
                            $_t              = true;
                            $request['type'] = 'modify';
                        }

                    }
                    /*

                    elseif (in_array($recid, array_keys($records))) {
                        //get map record by finding name and type

                        $current_record = $result_domainlist[$records[$recid]];

                        if(isset($current_record['name'])) {


                            $record_name = rtrim(ltrim(str_replace('.' . $_domain, '', $current_record['name']), '.'), '.');

                            if ($hostname == '' && $address == '') {
                                //Sil
                                $api_response = $dna->deleteZoneRecord($_domain, $record_name, $type, $current_record['records'][0]['content']);

                                $request['type'] = 'delete';
                                $_t              = true;
                            } else {
                                //Güncelle

                                //if($hostname!=$current_name || str_replace($priority.' ','',$address)!=$current_value ||$priority!=$current_priority){

                                    if ($record_name == $_domain) {
                                        $record_name = '';
                                    }
                                    if ($_name == $_domain . '.') {
                                        $_name = '';
                                    }

                                    $api_response = $dna->modifyZoneRecord($_domain, $record_name, $_name, $type, $address);

                                    $request['type'] = 'modify';
                                    $_t              = true;
                                //}

                            }

                        }

                    }
                    */

                    if($_t===true){

                        $api_parsed['result'] = $api_response["success"] === true ? ['result'=>'success'] : ($api_response["error"]["code"] . " - " . $api_response["error"]["message"]);

                        $request_data =['domain'=>$_domain,'record_old'=>$record_name,'record_new'=>$_name] + $dna->getRequestData();

                        logModuleCall("domainnameapi",
                            substr(__FUNCTION__, 14).'_'.$request['type'],
                            $request_data,
                            $dna->getResponseData(),
                            $api_parsed
                        );

                    }

                }

                if ($api_response["success"] === true) {
                    $values["success"] = 'success';
                }


            }

            $forward = $dna->getForward($_domain);

            $redirectRecord = null;
            foreach ($params['dnsrecords'] as $record) {
                if (in_array($record['type'],['URL','FRAME','REDIRECT'])) {
                    $redirectRecord = $record;
                    break;
                }
            }


            $_t = '';
            if ($forward["success"] !== true && $redirectRecord['recid'] == '' && isset($redirectRecord['address']) && $redirectRecord['address'] != '') {
                $_f = $dna->setForward($_domain, $redirectRecord['address']);
                $_t='add';
            } elseif ($forward["success"] === true && isset($redirectRecord['address']) && $redirectRecord['address'] != $forward['redirectAlias'] && $redirectRecord['address'] != '') {
                $_f = $dna->setForward($_domain, $redirectRecord['address']);
                $_t='update';
            } elseif ($forward["success"] === true && ($redirectRecord == null || (isset($redirectRecord['address']) && $redirectRecord['address'] == ''))) {
                $_f = $dna->deleteForward($_domain);
                $_t='delete';
            }
            if($_t!==''){

                logModuleCall("domainnameapi",
                    substr(__FUNCTION__, 14).'_forward_'.$_t,
                    $dna->getRequestData(),
                    $dna->getResponseData(),
                    $_f["success"] === true ? ['result'=>'success'] : ($_f["error"]["code"] . " - " . $_f["error"]["message"])
                );

            }



        }
        else {
            $values["error"] = $result_domainlist["error"]["code"] . " - " . $result_domainlist["error"]["message"];
        }
    } else {
        $values["error"] = "DNS Management does not supported by Domain Name API.";
    }

    return $values;
}

function domainnameapi_CheckAvailability($params) {
    $values=[];

    $is_v2 = domainnameapi_checkV2($params);


    if ($params['isIdnDomain']) {
        $label = empty($params['punyCodeSearchTerm']) ? strtolower($params['searchTerm']) : strtolower($params['punyCodeSearchTerm']);
    } else {
        $label = strtolower($params['searchTerm']);
    }

    $tldslist       = $params['tldsToInclude'];
    $premiumEnabled = (bool)$params['premiumEnabled'];
    $domainslist    = [];
    $results        = new \WHMCS\Domains\DomainLookup\ResultsList();

    $result   = null;
    $all_tlds = [];
    foreach ($tldslist as $k => $v) {
        $all_tlds[] = ltrim($v, '.');
    }


    if($is_v2){

        $dna = domainnameapi_service2($params);

        $result = $dna->checkAvailability([$label],$all_tlds);

        foreach ($result as $k => $v) {

            if(isset($v['info'])){

                $_dom = $v['info']['domainName'];

                $firstDotPos = strpos($_dom, '.');
                $sld = substr($_dom, 0, $firstDotPos);
                $tld = substr($_dom, $firstDotPos);


                $searchResult = new SearchResult($sld, $tld);

                $register_price = $v['info']['price'];
                $renew_price = $v['info']['price'];

                $values[]=$v['info'];

                if ($v['info']['status'] == 'AVAILABLE') {
                    $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);
                }elseif($v['info']['status'] == 'NOTAVAILABLE'){
                    $searchResult->setStatus(SearchResult::STATUS_TLD_NOT_SUPPORTED);
                }else{
                    $searchResult->setStatus(SearchResult::STATUS_REGISTERED);
                }

                if ($v['info']['isPremium'] == '1') {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing(['register'=> $register_price, 'renew'=> $renew_price, 'CurrencyCode' => 'USD',]);
                }
                $results->append($searchResult);

            }

        }

    }
    else{
        $dna = domainnameapi_service($params);


        //$tld=str_replace(".","",$domain['tld']);
        $result = $dna->CheckAvailability([$label],$all_tlds,"1","create");

        $exchange_rates = domainnameapi_exchangerates();


        foreach ($result as $k => $v) {
            $searchResult = new SearchResult($label, '.'.$v['TLD']);

            $register_price = $v['Price'];
            $renew_price = $v['Price'];

            if(strpos($v['TLD'],'.tr' ) !== false){
                $register_price = $register_price / $exchange_rates['TRY'];
                $renew_price = $renew_price / $exchange_rates['TRY'];
            }



            if ($v['Status'] == 'available') {

                $status = SearchResult::STATUS_NOT_REGISTERED;
                $searchResult->setStatus($status);

                if ($v['IsFee'] == '1') {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing(['register'     => $register_price, 'renew'        => $renew_price, 'CurrencyCode' => 'USD',]);
                }

            }else{
                $status = SearchResult::STATUS_REGISTERED;
                $searchResult->setStatus($status);
            }
          $results->append($searchResult);
        }
    }


    logModuleCall("domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );


    return $results;

}


function domainnameapi_GetDomainSuggestions($params) {
    $values = [];

    // Sistem versiyonunu kontrol et
    $is_v2 = domainnameapi_checkV2($params);

    if ($params['isIdnDomain']) {
        $label = empty($params['punyCodeSearchTerm']) ? strtolower($params['searchTerm']) : strtolower($params['punyCodeSearchTerm']);
    } else {
        $label = strtolower($params['searchTerm']);
    }

    $tldslist       = $params['tldsToInclude'];
    $premiumEnabled = (bool)$params['premiumEnabled'];
    $domainslist    = [];
    $results        = new \WHMCS\Domains\DomainLookup\ResultsList();

    $result   = null;
    $all_tlds = [];
    foreach ($tldslist as $k => $v) {
        $all_tlds[] = ltrim($v, '.');
    }


    if($is_v2){

        $dna = domainnameapi_service2($params);

        $result = $dna->checkAvailability([$label],$all_tlds);

        foreach ($result as $k => $v) {

            if(isset($v['info'])){

                $_dom = $v['info']['domainName'];

                $firstDotPos = strpos($_dom, '.');
                $sld = substr($_dom, 0, $firstDotPos);
                $tld = substr($_dom, $firstDotPos);

                $searchResult = new SearchResult($sld, $tld);

                $register_price = $v['info']['price'];
                $renew_price = $v['info']['price'];

                $values[]=$v['info'];

                if ($v['info']['status'] == 'AVAILABLE') {
                    $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);
                }elseif($v['info']['status'] == 'NOTAVAILABLE'){
                    $searchResult->setStatus(SearchResult::STATUS_TLD_NOT_SUPPORTED);
                }else{
                    $searchResult->setStatus(SearchResult::STATUS_REGISTERED);
                }

                if ($v['info']['isPremium'] == '1') {
                    $searchResult->setPremiumDomain(true);
                    $searchResult->setPremiumCostPricing(['register'=> $register_price, 'renew'=> $renew_price, 'CurrencyCode' => 'USD',]);
                }

                $results->append($searchResult);

            }


        }

    }
    else {
        $dna = domainnameapi_service($params);


        $result = $dna->CheckAvailability([$label], $all_tlds, "1", "create");

        $exchange_rates = domainnameapi_exchangerates();


        foreach ($result as $k => $v) {
            $searchResult = new SearchResult($label, '.' . $v['TLD']);

            $register_price = $v['Price'];
            $renew_price    = $v['Price'];

            if (strpos($v['TLD'], '.tr') !== false) {
                $register_price = $register_price / $exchange_rates['TRY'];
                $renew_price    = $renew_price / $exchange_rates['TRY'];
            }


            if ($v['Status'] == 'available') {
                $status = SearchResult::STATUS_NOT_REGISTERED;
                $searchResult->setStatus($status);

                if ($v['IsFee'] == '1') {
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
    }




    logModuleCall(
        "domainnameapi",
        substr(__FUNCTION__, 14),
        $dna->getRequestData(),
        $dna->getResponseData(),
        $values
    );


    return $results;
}


function domainnameapi_GetTldPricing($params) {

    $values = [];
    $results = new ResultsList;

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $tldlist = $dna->getTldList();

        if(isset($tldlist['items'])){

            foreach ($tldlist['items'] as $k => $v) {


                $prices = [];

                foreach ($v['prices'] as $kp => $vp) {
                    $prices[$vp['orderType']]=$vp;
                }

                if(isset($prices['Register']['price'])){
                     $item = (new ImportItem)->setExtension('.'.trim($v['name']))
                                            ->setMinYears(min($v['registrationPeriods']))
                                            ->setMaxYears(max($v['registrationPeriods']))
                                            ->setRegisterPrice($prices['Register']['price'])
                                            ->setRenewPrice($prices['Renew']['price'])
                                            ->setTransferPrice($prices['Transfer']['price'])
                                            ->setCurrency('USD');

                    $results[] = $item;
                }



            }

        }


    } else {
        $dna = domainnameapi_service($params);

        $tldlist = $dna->GetTldList(1200);

        $convertable_currencies = domainnameapi_exchangerates();


        if ($tldlist['result'] == 'OK') {
            foreach ($tldlist['data'] as $extension) {
                if (strlen($extension['tld']) > 1) {
                    $price_registration = $extension['pricing']['registration'][1];
                    $price_renew        = $extension['pricing']['renew'][1];
                    $price_transfer     = $extension['pricing']['transfer'][1];
                    $current_currency   = $extension['currencies']['registration'];

                    if ($current_currency == 'TL') {
                        $current_currency = 'TRY';
                    }


                    if (in_array($params["basecurrency"], array_keys($convertable_currencies))) {
                        $exchange_rate     = $convertable_currencies[$params["basecurrency"]];
                        $exchange_rate_rev = $convertable_currencies['TRY'];

                        if ($current_currency == 'USD') {
                            $exchange_rate_rev = 1;
                        }

                        $price_registration = $price_registration * $exchange_rate / $exchange_rate_rev;
                        $price_renew        = $price_renew * $exchange_rate / $exchange_rate_rev;
                        $price_transfer     = $price_transfer * $exchange_rate / $exchange_rate_rev;

                        $current_currency = $params["basecurrency"];
                    }


                    $tlds[] = $extension['tld'];

                    $item = (new ImportItem)->setExtension(trim($extension['tld']))
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
    }




    return $results;
}

function domainnameapi_Sync($params) {

    $values = [];
    $domain = $params["sld"] . "." . $params["tld"];


    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $details = $dna->getDomainDetails($domain);

        if ($details["success"] === true) {
            $active       = '';
            $expired      = '';
            $expiration   = '';
            $transferaway = '';

            if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $details["expirationDate"])) {
                $expiration = substr($details["expirationDate"], 0, 10);
            }
            if ($details["status"] == "Active") {
                $active       = true;
                $expired      = false;
                $transferaway = false;
            }
            if (in_array($details["status"], ['PendingDelete', 'Deleted'])) {
                $expired      = true;
                $active       = false;
                $transferaway = false;
            }
            if ($details["status"] == "TransferredOut") {
                $expired      = true;
                $active       = false;
                $transferaway = true;
            }

            if (is_bool($active) && is_bool($expired) && trim($expiration) != "" && is_bool($transferaway)) {
                $values["active"]     = $active;
                $values["expired"]    = $expired;
                $values["expirydate"] = $expiration;
                //$values["success"] = true;
            } else {
                $values["error"] = "Unexpected result returned from registrar" . "\nActive: " . $active . "\nExpired: " . $expired . "\nExpiryDate: " . $expiration;
            }


        } else {
            $values["error"] = $details["error"]["code"] . " - " . $details["error"]["message"];
        }

    }else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->SyncFromRegistry($domain);


        if ($result["result"] == "OK") {
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


            $result2 = $dna->GetDetails($params["sld"] . "." . $params["tld"]);

            if ($result2["result"] == "OK") {
                $active       = '';
                $expired      = '';
                $expiration   = '';
                $transferaway = '';

                // Check results
                if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $result2["data"]["Dates"]["Expiration"])) {
                    $expiration = substr($result2["data"]["Dates"]["Expiration"], 0, 10);
                }
                if ($result2["data"]["Status"] == "Active") {
                    $active       = true;
                    $expired      = false;
                    $transferaway = false;
                }
                if (in_array($result2["data"]["Status"], ['PendingDelete', 'Deleted'])) {
                    $expired      = true;
                    $active       = false;
                    $transferaway = false;
                }
                if ($result2["data"]["Status"] == "TransferredOut") {
                    $expired      = true;
                    $active       = false;
                    $transferaway = true;
                }


                // If result is valid set it to WHMCS
                if (is_bool($active) && is_bool($expired) && trim($expiration) != "" && is_bool($transferaway)) {
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

function domainnameapi_TransferSync($params) {
 $values = [];
    $domain = $params["sld"] . "." . $params["tld"];


    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {
        $dna = domainnameapi_service2($params);

        $details = $dna->getDomainDetails($domain);


        if ($details["success"] === true) {

            if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $details["expirationDate"])) {
                $values['expirydate'] = substr($details["expirationDate"], 0, 10);
            }
            if ($details["status"] == "Active") {
                $values['completed'] = true;
                $values['failed']    = false;
            }
            if ($details["status"] == "TransferCancelledFromClient") {
                $values['completed'] = true;
                $values['failed']    = false;
                $values['reason']    = 'Transfer Cancelled From Client';
            }
        } else {
            $values["error"] = $details["error"]["code"] . " - " . $details["error"]["message"];
        }



    }else {
        $dna = domainnameapi_service($params);

        // Process request
        $result = $dna->SyncFromRegistry($params["sld"] . "." . $params["tld"]);


        // Log request
        logModuleCall("domainnameapi", substr(__FUNCTION__, 14), $dna->getRequestData(), $dna->getResponseData(),
            $values, [$username, $password]);

        if ($result["result"] == "OK") {
            $result2 = $dna->GetDetails($params["sld"] . "." . $params["tld"]);

            if ($result2["result"] == "OK") {
                // Check results
                if (preg_match("/\d{4}\-\d{2}\-\d{2}T\d{2}\:\d{2}\:\d{2}/", $result2["data"]["Dates"]["Expiration"])) {
                    $values['expirydate'] = date('Y-m-d', strtotime($result2["data"]["Dates"]["Expiration"]));
                }
                if ($result2["data"]["Status"] == "Active") {
                    $values['completed'] = true;
                    $values['failed']    = false;
                }
                if ($result2["data"]["Status"] == "TransferCancelledFromClient") {
                    $values['completed'] = true;
                    $values['failed']    = false;
                    $values['reason']    = 'Transfer Cancelled From Client';
                }
            } else {
                $values["error"] = $result["error"]["Message"] . " - " . $result["error"]["Details"];
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

function domainnameapi_AdminDomainsTabFields($params){


    $regs = Illuminate\Database\Capsule\Manager::table('tblregistrars')->where('registrar', 'domainnameapi')->get();

    foreach ($regs as $k => $v) {
        $results = localAPI('DecryptPassword', ['password2'=>$v->value]);
        $params[$v->setting] = $results['password'];
    }

    $status=$startdate=$expry=$remaining=$addionals =$nameservers=$dnsrecs= '';
    $domain = $params["sld"] . "." . $params["tld"];

    $is_v2 = domainnameapi_checkV2($params);

    if ($is_v2) {

        $dna= domainnameapi_service2($params);

        $result = $dna->getDomainDetails($domain);

        $status    = $result['status'];
        $startdate = date('Y-m-d H:i:s',strtotime($result['startDate']));
        $expry     = date('Y-m-d H:i:s',strtotime($result['expirationDate']));
        $remaining = round((strtotime($expry) - strtotime(date('Y-m-d H:i:s'))) / 86400);;

        if(is_array($result['hosts'])){
            $nameservers.='<table border="1" width="500"> <tr> <th>IP</th> <th>Name</th> </tr>';
            foreach ($result['hosts'] as $k => $v) {
                $nameservers .= '<tr> <td>' . $v['ipAddresses'][0]['ipAddress'] . '</td> <td>' . $v['name'] . '</td> </tr>';
            }
            $nameservers.='</table>';
        }

    }else{
        $dna = domainnameapi_service($params);


        // Process request
        $result = $dna->GetDetails($domain);

        $status    = $result['data']['Status'];
        $startdate = $result['data']['Dates']['Start'];
        $expry     = $result['data']['Dates']['Expiration'];
        $remaining = $result['data']['Dates']['RemainingDays'];

        foreach ($result['data']['Additional'] as $k => $v) {
            $addionals .= $k . ' : ' . $v . '<br>';
        }
        foreach ($result['data']['ChildNameServers'] as $k => $v) {
            $nameservers .= '[' . $v['ip'] . '] : ' . $v['ns'] . '<br>';
        }

    }

    $dns_records = domainnameapi_GetDNS($params);

    if(isset($dns_records['error'])) {
        $dnsrecs = $dns_records['error'];
    }else{
        $dnsrecs = '<table border="1" width="500"> <tr> <th>Hostname</th> <th>Type</th> <th>Address</th> </tr>';

        foreach ($dns_records as $k => $v) {
            $dnsrecs.= '<tr> <td>'.$v['hostname'].'</td> <td>'.$v['type'].'</td> <td>'.$v['address'].'</td> </tr>';
            //$dnsrecs .= $v['hostname'] . ' : ' . $v['type'] . ' : ' . $v['address'] . '<br>';
        }
        $dnsrecs .= '</table>';
    }


    return [
        'Current State'      => $status,
        'Start'              => $startdate,
        'Expiring'           => $expry,
        'Remaining Days'     => $remaining,
        'Child Nameservers'  => $nameservers,
        'AddionalParameters' => $addionals,
        'DNS Records'        => $dnsrecs
    ];

}


function domainnameapi_parse_contact($contacts,$contacttype='Registrant',$is_v2=false) {

     if ($is_v2) {
        // Yeni API için iletişim bilgileri yapısı
        $filteredContacts = array_filter($contacts, function ($contact) use ($contacttype) {
            return $contact['contactType'] == $contacttype;
        });
        $contact = reset($filteredContacts) ?: []; // Eğer $filteredContacts boşsa, boş bir dizi döndür
    } else {
        // Eski API için iletişim bilgileri yapısı
        $contact = $contacts[$contacttype];
    }



    // Ortak dönüş yapısı, veri kaynağına göre ayarlanır
    $contact_data = [
        "First Name"         => $contact["firstName"] ?? $contact["FirstName"],
        "Last Name"          => $contact["lastName"] ?? $contact["LastName"],
        "Company Name"       => $contact["companyName"] ?? $contact["Company"],
        "Email"              => $contact["eMail"] ?? $contact["EMail"],
        "Phone Country Code" => $is_v2 ? $contact["phoneCountryCode"] : $contact["Phone"]["Phone"]["CountryCode"],
        "Phone"              => $is_v2 ? $contact["phone"] : $contact["Phone"]["Phone"]["Number"],
        "Fax Country Code"   => $is_v2 ? $contact["faxCountryCode"] : $contact["Phone"]["Fax"]["CountryCode"],
        "Fax"                => $is_v2 ? $contact["fax"] : $contact["Phone"]["Fax"]["Number"],
        "Address 1"          => $contact["address"] ?? $contact["Address"]["Line1"],
        "Address 2"          => $is_v2 ?'': $contact["Address"]["Line2"],
        "State"              => $contact["state"] ?? $contact["Address"]["State"],
        "City"               => $contact["city"] ?? $contact["Address"]["City"],
        "Country"            => $contact["country"] ?? $contact["Address"]["Country"],
        "ZIP Code"           => $contact["postalCode"] ?? $contact["Address"]["ZipCode"]
    ];

   if($is_v2){
       unset($contact_data['Address 2']);
   }
   return $contact_data;

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

    //OVERRIDE

    $username   = 'dnatest';
    $password   = 'Dnatest123*';
    $resellerid = '2bf2ba09-6c9d-4012-9cfe-8b4c10e7e6e5';



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

function domainnameapi_checkV2($params){
    $is_v2 = $params["API_SYSType"] === true || $params["API_SYSType"] == 'on';

    if(isset($params['domainid'])){
        $this_domain = Capsule::table('tbldomains')
                        ->where('id', $params['domainid'])
                        ->first();

        if(isset($this_domain->additionalnotes)){
            if(strpos('#SYSV1#',$this_domain->additionalnotes ) !== false){
                $is_v2 = false;
            }
            if(strpos('#SYSV2#',$this_domain->additionalnotes ) !== false){
                $is_v2 = true;
            }
        }
    }




    return $is_v2;
}

