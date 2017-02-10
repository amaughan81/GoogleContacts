<?php

namespace amaughan81;

class GoogleContactsHelper {

    protected $params = [];
    protected $query = "https://www.google.com/m8/feeds/contacts/default/full";
    protected $headers = [];

    public function setMaxResults($value) {
        $value = (int)$value;
        if ($value != 0) {
            $this->params['max-results'] = $value;
        } else {
            unset($this->params['max-results']);
        }
        return $this;
    }

    public function getQuery() {
        if(count($this->params) > 0) {
            $this->query .= '?';
            foreach($this->params as $key=>$value) {
                $this->query .= $key.'='.$value.'&';
            }
            $this->query = rtrim($this->query,'&');

        }
        return $this->query;
    }

    public function setMajorProtocolVersion($value) {
        $value = floatval($value);
        $this->headers['GData-Version'] = $value;
    }

}