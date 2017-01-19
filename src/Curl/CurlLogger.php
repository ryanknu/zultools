<?php

namespace ZTools\Curl;

class CurlLogger
{
    protected static $adapter;
    private $read_only = false;

    protected function getDb() {
        return self::$adapter;
    }

    public function __construct(CurlTable $ado) {
        self::$adapter = $ado;
    }
    
    public function disableWrites()
    {
        $this->read_only = true;
    }

    public function log(CurlResponse $curl_response) {
        $data = array(
            'origination' => gethostname(),
            'txn_start' => $curl_response->request->getReadableStartTime() ?: '2000-01-01 00:00:00',
            'time_spent' => $curl_response->request->getTimer() * 1000,
            'url' => $curl_response->getOriginalUrl() ?: '',
            'http_status' => $curl_response->http_code ?: 0,
            'request' => $curl_response->request_header . $curl_response->request->getRenderedPOSTData(),
            'response' => strlen($curl_response->raw_body) > 1000000 ? substr($curl_response->raw_body, 0, 1000)."..." : $curl_response->raw_body,
            'curl_errno' => $curl_response->error_number ?: 0,
            'curl_error' => $curl_response->error ?: '',
        );
        
        if ( !$this->read_only ) {
            $this->getDb()->insert($data);
            return $this->getDb()->getLastInsertValue();
        }
        
        return 0;
    }
}
