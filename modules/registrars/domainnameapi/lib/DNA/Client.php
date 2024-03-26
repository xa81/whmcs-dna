<?php
/**
 * Created by PhpStorm.
 * User: esh
 * Project name php-dna-new
 * 21.02.2024 06:46
 * Bünyamin AKÇAY <bunyamin@bunyam.in>
 */

namespace DNA;

class Client  {


    private $username;
    private $password;
    public $token=null;
    private $resellerid;

    public $tokenexpire;

    public $authenticated = false;

    public $endpoint , $request , $response;


    /*
    public function __construct(...$credentials) {
        if (count($credentials) === 2) {
            $this->setToken($credentials[0]);
            $this->setResellerid($credentials[1]);
        } elseif (count($credentials) === 3) {
            $this->setCredientials($credentials[0], $credentials[1]);
            $this->setResellerid($credentials[2]);
            $this->authenticate();
        } elseif (count($credentials) === 4) {
            $this->setCredientials($credentials[0], $credentials[1]);
            $this->setToken($credentials[2]);
            $this->setResellerid($credentials[3]);
        }
    }
    */


    public function setToken($token) {
        $this->token = $token;
        return $this;
    }
    public function setCredentials($username,$password) {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    public function setTokenexpire($tokenexpire): void {
        $this->tokenexpire = $tokenexpire;
    }

    public function getResellerid() {
        return $this->resellerid;
    }

    public function setResellerid($resellerid) {
        $this->resellerid = $resellerid;
        return $this;
    }

    public function getToken() {
        return $this->token;
    }

    public function isAuthenticated() {
        return $this->authenticated;
    }

    public function setAuthenticated($authenticated) {
        $this->authenticated = $authenticated;
        return $this;
    }


    public function getRequestData() {
        return $this->request;
    }

    public function setRequest($request) {
        $this->request = $request;
    }

    public function getResponseData() {
        return $this->response;
    }

    public function setResponse($response) {
        $this->response = $response;
    }

    public function getEndpoint() {
        return $this->endpoint;
    }

    public function setEndpoint($endpoint) {
        $this->endpoint = $endpoint;
    }


    public function authenticate() {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://ids-test.domainnameapi.com/connect/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => 'Dna_PublicApi',
            'grant_type'    => 'password',
            'client_secret' => '2b6a1857-2159-4d76-8645-647cc81f2b45',
            'scope'         => 'DomainService ProductService OrderService',
            'username'      => $this->username,
            'password'      => $this->password
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);

        $response_obj = json_decode($response);

        //if (curl_errno($ch)) {
        //    throw new \Exception('Curl error:'.  curl_error($ch)) ;
        //}

        curl_close($ch);

        if (isset($response_obj->error)) {
        //    throw new \Exception('Authentication error: ' . $response_obj->error_description);
        }

        $this->setAuthenticated(!isset($response_obj->error));
        $this->setToken($response_obj->access_token);
        $this->setTokenexpire($response_obj->expires_in);
        return $this;
    }


    public function request($method, $endpoint, $data = []) {

        $url = 'https://rest-test.domainnameapi.com/api/' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (in_array($method, ['GET', 'DELETE'])) {
            if(!empty($data)){
                $url .= '?' . http_build_query($data);
            }
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: text/plain',
            'Authorization: Bearer ' . $this->getToken(),
            '__reseller: ' . $this->getResellerid(),
        ]);





        $response = curl_exec($ch);
        $response_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($response, true);
        $response['status_code'] = $response_status;

        $this->setRequest($data);
        $this->setResponse($response);
        $this->setEndpoint($endpoint);


        $response['status_code'] = $response_status;

        if ($response_status >= 200 && $response_status <= 299) {
            // Başarılı
            $response['success'] = true;
        } elseif (curl_errno($ch)) {
            // Fatal CURL error
            $response['success'] = false;
            $response['error']   = 'Comminication error: ' . curl_error($ch);
        } else {
            // 200-299 arası değilse, yani başarılı bir HTTP durumu değilse
            $response['success']     = false;
            $response['http_status'] = $response_status;
        }

        return $response;

    }






}