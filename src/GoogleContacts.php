<?php

namespace amaughan81;

use amaughan81\GoogleAuth;

class GoogleContacts extends GoogleContactsHelper {

    protected $client;
    protected $httpClient;
    protected $query = "https://www.google.com/m8/feeds/contacts/default/full";

    public function __construct($user = null)
    {
       $this->client = GoogleAuth::getClient($user);
       $this->httpClient = $this->client->authorize();
    }

    /**
     *
     * Get all contacts in the users' account
     *
     * @return array of contacts
     */
    public function getAllContacts() {

        $this->setMaxResults(10000);

        $response = $this->httpClient->get($this->getQuery());

        $dom = new \DOMDocument();
        $xml = simplexml_load_string($response->getBody());
        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contacts = array();

        foreach($xml->entry as $key => $entry) {

            // Get email address and phone number
            $contactInfo = $this->getContactDetails($entry->children('http://schemas.google.com/g/2005'));
            // Get the photo uri of the user
            $photoUri = $this->getPhotoUri($entry->link);
            // Get contact group memberships for the user
            $cGroups = $this->getGroupMembership($entry->xpath('gContact:groupMembershipInfo'));

            // Add entry information to an array
            $contacts[] = [
                'id'=>(string)$entry->id,
                'title'=>(string)$entry->title,
                'emailAddress'=>$contactInfo['emailAddress'],
                'phoneNumber'=>$contactInfo['phoneNumber'],
                'photoUri'=>$photoUri,
                'groups'=>$cGroups
            ];
        }
        // return all contact info
        return $contacts;
    }

    /**
     * Search Google contacts - provide an array of terms to search on
     *
     * @param array $terms
     * @return array
     */
    public function searchContacts($terms = []) {
        if(count($terms) > 0) {
            $this->params['q'] = '';
            if(is_array($terms)) {
                foreach ($terms as $term) {
                    $this->params['q'] .= $term . ' ';
                }
                $this->params['q'] = rtrim($this->params['q']);
            } else {
                $this->params['q'] = $terms;
            }
            // force version 3 if doing full text queries
            $this->params['v'] = '3.0';
        }
        return $this->getAllContacts();
    }


    /**
     * Get the user's profile photo
     *
     * @param $photoURL
     * @return mixed
     */
    public function getPhoto($photoURL)
    {
        $request = $this->httpClient->get($photoURL);
        $response = $request->getBody();
        return $response;
    }

    /**
     * Get contact details from the entry id
     *
     * @param $id
     * @return array
     */
    public function getContact($id, $raw=false) {
        $request = $this->httpClient->get($id);

        // return the xml if raw is true, this is generally used for updating a contact
        if($raw) {
            return $request->getBody();
        }

        $xml = simplexml_load_string($request->getBody());

        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $contactInfo = $this->getContactDetails($xml->children('http://schemas.google.com/g/2005'));

        $contact = [];
        $contact['id'] = (string)$xml->id;
        $contact['title'] = (string)$xml->title;
        $contact['emailAddress'] = $contactInfo['emailAddress'];
        $contact['phoneNumber'] = $contactInfo['phoneNumber'];
        $contact['photoUri'] = $this->getPhotoUri($xml->link);
        $contact['groups'] = $this->getGroupMembership($xml->xpath('gContact:groupMembershipInfo'));

        return $contact;
    }

    /**
     * Delete a contact based on their id
     *
     * @param $id
     */
    public function deleteContact($id) {
        $this->headers['If-Match'] = '*';

        $request = new \GuzzleHttp\Psr7\Request(
            'DELETE',
            $id,
            $this->headers
        );
        $response = $this->httpClient->send($request);
    }


    /**
     * Create a contact in the user's contacts
     *
     * @param $forename
     * @param $surname
     * @param $emailAddress
     * @param $phoneNumber
     * @param array $groups
     * @return \SimpleXMLElement[]|string
     */
    public function createContact($forename, $surname, $emailAddress, $phoneNumber, $groups = []) {

        $doc = new \DOMDocument();
        $doc->formatOutput = true;

        // add name element
        $doc->appendChild(
            $this->setContactAttributes(
                $doc,
                'single',
                $forename,
                $surname,
                $emailAddress,
                $phoneNumber,
                $groups
            )
        );

        echo $newContact = $doc->saveXML();

        try {
            $this->headers['Content-type'] = 'application/atom+xml; charset=UTF-8; type=entry';

            $request = new \GuzzleHttp\Psr7\Request(
                'POST',
                $this->getQuery(),
                $this->headers,
                $newContact
            );

            $response = $this->httpClient->send($request);

            $xml = simplexml_load_string($response->getBody());
            $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

            return $xml->id;
        }
        catch(\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Update a User's a contact
     *
     * @param $id
     * @param $forename
     * @param $surname
     * @param $emailAddress
     * @param $phoneNumber
     * @param array $groups
     */
    public function updateContact($id, $forename, $surname, $emailAddress, $phoneNumber, $groups=[]) {
        // Get the user - we need the full xml response so pass raw=true
        $xmlContact = $this->getContact($id, true);

        $xml = simplexml_load_string($xmlContact);
        //$xml->registerXPathNamespace('gContact', 'http://schemas.google.com/contact/2008');

        $entry = $xml;

        $entry->title = $forename.' '.$surname;

        $contactGDNodes = $entry->children('http://schemas.google.com/g/2005');
        foreach($contactGDNodes as $key => $value) {
            $attribs = $value->attributes();

            if($key == 'email') {
                $attribs['address'] = $emailAddress;
            } else  if($key == 'phoneNumber') {
                $attribs['key'] = $phoneNumber;
            }
        }

        $groupNodes = $entry->xpath('gContact:groupMembershipInfo');

        // Remove any existing groups
        foreach($groupNodes as $group) {
            unset($group[0]);
        }

        // Add the new groups
        foreach($groups as $newGroup) {
            $gContact = $entry->addChild('gContact:groupMembershipInfo', null, 'http://schemas.google.com/contact/2008');
            $gContact->addAttribute('deleted', 'false');
            $gContact->addAttribute('href', $newGroup);
        }


        $updatedXml = $entry->saveXML();

        $this->headers['If-Match'] = '*';
        $this->headers['Content-type'] = 'application/atom+xml';

        $request = new \GuzzleHttp\Psr7\Request(
            'PUT',
            $id,
            $this->headers,
            $updatedXml
        );
        $response = $this->httpClient->send($request);
        $response->getBody();
    }

    /**
     * Public interface to batch Create Contacts
     *
     * @param $contacts
     */
    public function batchCreateContact($contacts) {
        $this->batchOperation('create', $contacts);
    }

    /**
     * Public interface to batch Update Contacts
     *
     * @param $contacts
     */
    public function batchUpdateContact($contacts) {
        $this->setMajorProtocolVersion(3);
        $this->batchOperation('update', $contacts);
    }


    /**
     * Batch Delete a bunch of users from user's account
     *
     * @param $contacts ['id1, id2 ...']
     */
    public function batchDeleteContacts($contacts) {
        $doc = new \DOMDocument ();
        $doc->formatOutput = true;
        $feed = $this->batchHeader($doc);

        foreach($contacts as $contact) {
            $entry = $doc->createElement('entry');
            $entry->setAttribute('gd:etag', '*');

            $batchId = $doc->createElement('batch:id', 'delete');
            $batchOp = $doc->createElement('batch:operation');
            $batchOp->setAttribute('type','delete');
            $entry->appendChild($batchId);
            $entry->appendChild($batchOp);

            $idTag = $doc->createElement('id', $contact);
            $entry->appendChild($idTag);
            $feed->appendChild($entry);
        }

        $doc->appendChild ( $feed );

        $this->postBatchData($doc->saveXML());
    }

    /**
     * Batch Operations for contacts
     * An array of contacts must be supplied
     *
     * @param $contacts
     * keys: forename, surname, emailAddress, phoneNumber, groups, id (optional)
     * @param $type
     */
    private function batchOperation($type, $contacts) {
        $doc = new \DOMDocument ();
        $doc->formatOutput = true;

        $feed = $this->batchHeader($doc);

        foreach($contacts as $contact) {

            $contactID = null;
            $forename = $contact['forename'];
            $surname = $contact['surname'];
            $emailAddress = $contact['emailAddress'];
            $phoneNumber = $contact['phoneNumber'];
            $groups = $contact['groups'];
            if(array_key_exists('id', $contact)) {
                $contactID = $contact['id'];
            }

            $entry = $this->setContactAttributes(
                $doc,
                $type,
                $forename,
                $surname,
                $emailAddress,
                $phoneNumber,
                $groups,
                $contactID
            );
            $feed->appendChild($entry);
        }

        $doc->appendChild($feed);

        $this->postBatchData($doc->saveXML());

    }

    /**
     * Takes the XML string of the batch request and process it
     *
     * @param $xml
     */
    private function postBatchData($xml) {
        try {
            $request = new \GuzzleHttp\Psr7\Request(
                'POST',
                $this->query.'/batch',
                ['Content-Type' => 'application/atom+xml; charset=UTF-8; type=entry', 'If-Match'=>'*'],
                $xml
            );

            $response = $this->httpClient->send($request);
            echo $response->getBody();
        }
        catch(Exception $e) {
            echo $e->getMessage();
        }
    }


    /**
     * Get the Photo URI from a link entry
     *
     * @param $links
     * @return string
     */
    private function getPhotoUri($links) {
        $photoUri = "";
        foreach($links as $link) {
            if($link->attributes()->rel == "http://schemas.google.com/contacts/2008/rel#edit-photo") {
                $photoUri = (string)$link->attributes()->href;
            }
        }
        return $photoUri;
    }

    /**
     * Get group memberships from gContact:groupMembershipInfo
     * Return an array of group ids
     *
     * @param $membership
     * @return array
     */
    private function getGroupMembership($membership) {
        $cGroups = [];
        foreach($membership as $gEntry) {
            $cGroups[] = (string)$gEntry['href'];
        }
        return $cGroups;
    }

    /**
     * Get basic contact details emailAdress and phoneNumber
     *
     * @param $contactGDNodes
     * @return array
     */
    private function getContactDetails($contactGDNodes) {

        $contact = ['emailAddress' => '', 'phoneNumber'=>''];

        foreach ($contactGDNodes as $key => $value) {
            // Get the email
            $attribs = $value->attributes();
            switch ($key) {
                case 'email':
                    $contact['emailAddress'] = (string)$attribs['address'];
                    break;
                case 'phoneNumber' :
                    $uri = (string) $attribs['uri'];
                    $contact['phoneNumber'] = str_replace(['tel:','-'],'', $uri);
                    break;

            }
        }
        return $contact;
    }

    private function setContactAttributes(\DOMDocument $doc, $type, $forename, $surname, $emailAddress, $phoneNumber, $groups=[], $id=null) {
        if($type == "single") {
            $entry = $doc->createElement('atom:entry');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom');
            $entry->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005');
            $doc->appendChild($entry);

            $cat = $doc->createElement('atom:category');
            $cat->setAttribute('scheme','http://schemas.google.com/g/2005#kind');
            $cat->setAttribute('term','http://schemas.google.com/g/2008#contact');
            $entry->appendChild($cat);
        } else {
            $entry = $doc->createElement('entry');

            $batchId = $doc->createElement('batch:id', $type);
            $batchOp = $doc->createElement('batch:operation');
            if($type == "update") {

                $entry->setAttribute('gd:etag', '*');

                $batchOp->setAttribute('type', $type);
                $idTag = $doc->createElement('id', $id);
                $entry->appendChild($idTag);
            }
            else {
                $batchOp->setAttribute('type', 'insert');
            }
            $entry->appendChild($batchId);
            $entry->appendChild($batchOp);

            $cat = $doc->createElement('category');
            $cat->setAttribute('scheme','http://schemas.google.com/g/2005#kind');
            $cat->setAttribute('term','http://schemas.google.com/g/2008#contact');
            $entry->appendChild($cat);
        }

        /*$name = $doc->createElement('gd:name');

        $givenName = $doc->createElement('gd:givenName',$forename);
        $name->appendChild($givenName);
        $familyName = $doc->createElement('gd:familyName',$surname);
        $name->appendChild($familyName);
        $fullName = $doc->createElement('gd:fullName', $forename.' '.$surname);
        $name->appendChild($fullName);

        $entry->appendChild($name);*/

        // Set then contact title
        $title = $doc->createElement('title', $forename.' '.$surname);
        $entry->appendChild($title);

        // add email element
        $email = $doc->createElement('gd:email');
        $email->setAttribute('address' ,$emailAddress);
        $email->setAttribute('primary', 'true');
        $email->setAttribute('displayName', $forename.' '.$surname);
        $email->setAttribute('rel' ,'http://schemas.google.com/g/2005#work');
        $entry->appendChild($email);

        // add email element
        $email2 = $doc->createElement('gd:email');
        $email2->setAttribute('address' ,$emailAddress);
        $email2->setAttribute('rel' ,'http://schemas.google.com/g/2005#home');
        $entry->appendChild($email2);

        // add phoneNUmber element
        if($phoneNumber != null) {
            $phone = $doc->createElement('gd:phoneNumber', $phoneNumber);
            $phone->setAttribute('rel', 'http://schemas.google.com/g/2005#work');
            $entry->appendChild($phone);
        }

        if(count($groups) > 0) {
            foreach($groups as $g) {
                $group = $doc->createElement('gContact:groupMembershipInfo');
                $group->setAttribute('href', $g);
                $group->setAttribute('deleted', 'false');
                $entry->appendChild($group);
            }
        }

        return $entry;

    }

    /**
     * Create the header for batch Requests
     *
     * @param \DOMDocument $doc
     * @return \DOMElement
     */
    private function batchHeader(\DOMDocument $doc) {
        $feed = $doc->createElement('feed');
        $feed->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom' );
        $feed->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005' );
        $feed->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:gContact", 'http://schemas.google.com/contact/2008');
        $feed->setAttributeNS('http://www.w3.org/2000/xmlns/',"xmlns:batch", 'http://schemas.google.com/gdata/batch' );

        return $feed;
    }
}