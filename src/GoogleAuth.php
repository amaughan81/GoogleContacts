<?php

namespace amaughan81;

class GoogleAuth {

    protected static $client_token;
    protected static $client_secret;
    protected static $google_user;
    protected $client;

    public static function getClient($user = null) {
        self::getClientTokens();

        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . self::$client_token);

        define('CREDENTIALS_PATH',  self::$client_token);
        define('CLIENT_SECRET_PATH',  self::$client_secret);

        define('SCOPES', implode(' ', [
                \Google_Service_People::CONTACTS,
                \Google_Service_People::CONTACTS_READONLY,
            ]
        ));

        $client = new \Google_Client();
        $client->setApplicationName('Google Contacts Missing API Client');
        $client->addScope(SCOPES);
        if($user != null) {
            $client->setSubject($user);
        } else {
            $client->setSubject(self::$google_user);
        }
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->useApplicationDefaultCredentials();

        return $client;
    }

    private static function getClientTokens() {
        $configPath = dirname(__FILE__)."/../config.json";
        $config = file_get_contents($configPath);
        $json = json_decode($config, true);

        if(array_key_exists('secret_path', $json) &&
            array_key_exists('client_path', $json) &&
            array_key_exists('subject', $json)
        ) {
            self::$client_secret = $json['secret_path'];
            self::$client_token = $json['client_path'];
            self::$google_user = $json['subject'];
        }

    }

}