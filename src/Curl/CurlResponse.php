<?php

namespace ZTools\Curl;

class CurlResponse
{
    private $raw_body;
    private $body;
    private $curl;
    private $request;
    private $status;
    private $error;
    private $error_number;
    private $log_class;
    private $headers = array();

    public function __get($key) {
        return $this->$key;
    }

    public function setCurlHandle( $ch ) {
        $this->curl = $ch;
        foreach( curl_getinfo($ch) as $info => $value ) {
            $this->$info = $value;
        }

        $this->error_number = curl_errno($ch);
        $this->error = curl_error($ch);

        $this->status = Curl::ResponseUnknown;
        if ( isset( $this->http_code ) ) {
            switch( substr($this->http_code, 0, 1) ) {
                case Curl::ResponseSuccess:
                    $this->status = Curl::ResponseSuccess;
                    break;
                    
                case Curl::ResponseRedirect:
                    $this->status = Curl::ResponseRedirect;
                    break;

                // A request the server cannot process has occurred, and our software needs to be fixed.
                case Curl::ResponseApplicationError:
                    $this->status = Curl::ResponseApplicationError;
                    break;

                // A request the server can process caused a problem on the external server. Contact support.
                case Curl::ResponseServerError:
                    $this->status = Curl::ResponseServerError;
                    break;
                default:
            }
        }
    }

    public function setBody($body, $content_type)
    {
        // RK: Set the raw body if it's not set
        if ( !$this->raw_body ) {
            $this->raw_body = $body;
        }
        
        if ( substr($this->raw_body, 0, 5) === 'HTTP/' ) {
            // get response headers
            $packet = explode("\r\n\r\n\r\n", $this->raw_body, 2)[0];
            foreach(explode("\r\n", $packet) as $line) {
                $parts = explode(':', $line, 2);
                if ( count($parts) === 2 ) {
                    $this->headers[$parts[0]] = $parts[1];
                }
            }
        }

        $this->content_type = $content_type;
        
        $this->body = $body;
        if ( $this->getRequest() && $this->getRequest()->shouldPostProcess() ) {
            foreach( Curl::$defaults->processors as $ppr => $callable ) {
                if ( strpos($this->content_type, $ppr) !== false ) {
                    $this->body = call_user_func($callable, $body);
                    break;
                }
            }
        }
    }

    public function setResponse($body, $ch)
    {
        $this->request->stopTimer();
        $this->raw_body = $body;
        $this->setCurlHandle($ch);
        
        // RK: Check for header in the output, if so, trim it off for preparing output.
        if ( $this->request->getOpt(CURLINFO_HEADER_OUT) ) {
            $header = trim(substr($body, 0, strrpos($body, "\r\n\r\n")));
            $body = trim(substr($body, strlen($header)));
        }

        $this->setCurlHandle($ch);
        $this->setBody($body, $this->content_type);
    }

    public function setRequest(Curl $r) {
        $this->request = $r;
    }
    
    public function getRequest()
    {
        return $this->request;
    }

    public function getTotalTimeMs() {
        return floor(microtime(1) - $this->request->getStartTime());
    }
    
    public function wasTimedOut() {
        return $this->error_number == 28;
    }
    
    public function getOriginalUrl()
    {
        return $this->request->getUrl();
    }
}
