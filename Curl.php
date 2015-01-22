<?php

/**
 * Zul Tools
 * General purpose tools created by Ryan Knuesel
 *
 * Curl class makes a saner interface to using Curl
 */

class CurlResponse {
    private $body;
    private $curl;
    private $request;
    private $status;
    private $error;
    private $log_class;

    public function __get($key) {
        return $this->$key;
    }

    public function setCurlHandle( $ch ) {
        $this->curl = $ch;
        foreach( curl_getinfo($ch) as $info => $value ) {
            $this->$info = $value;
        }

        $this->error = curl_errno($ch) . ': ' .curl_error($ch);

        $this->status = Curl::ResponseUnknown;
        if ( isset( $this->http_code ) ) {
            switch( substr($this->http_code, 0, 1) ) {
                case Curl::ResponseSuccess:
                    $this->status = Curl::ResponseSuccess;
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
        $this->raw_body = $body;
        $this->content_type = $content_type;
        
        $this->body = $body;
        foreach( Curl::$defaults->processors as $ppr => $callable ) {
            if ( strpos($this->content_type, $ppr) !== false ) {
                $this->body = call_user_func($callable, $body);
                break;
            }
        }
    }

    public function setResponse($body, $ch)
    {
        $this->setCurlHandle($ch);
        
        // RK: Check for header in the output, if so, trim it off for preparing output.
        $header = '';
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

    public function getTotalTimeMs() {
        return floor(microtime(1) - $this->request->getStartTime());
    }
}

class Curl {
    const ResponseSuccess = 2;
    const ResponseApplicationError = 4;
    const ResponseServerError = 5;
    const ResponseUnknown = -1;

    private $opts = array();
    private $time = 0;
    private $logger = null;
    private $extra_options;
    private static $mock_responses = array();

    static $defaults = array(

        'opts' => array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // vantiv stopgap
            CURLINFO_HEADER_OUT => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
        ),

        'processors' => array(),
    );

    public function __construct()
    {
        if ( is_array(self::$defaults) ) {
            self::$defaults = (object) self::$defaults;
        }
        if ( !self::$defaults->processors ) {
            self::$defaults->processors['application/json'] = function($raw_body) {
                return json_decode($raw_body);
            };
            self::$defaults->processors['application/xml'] = function($raw_body) {
                return new \SimpleXMLElement($raw_body);
            };
            self::$defaults->processors['text/xml'] = self::$defaults->processors['application/xml'];
        }
    }

    public function setOpts(array $opts)
    {
        $this->opts = self::$defaults->opts + $this->opts + $opts;
    }
    
    public function setExtraOptions($extra_opts) 
    {
        $this->extra_options = $extra_opts;
    }

    public function setUrl($url)
    {
        $this->setOpts(array(CURLOPT_URL => $url));
    }

    public function get($url)
    {
        $this->setUrl($url);
        return $this->execute();
    }

    public function setHeaders($headers)
    {
        $this->setOpts(array(CURLOPT_HTTPHEADER => $headers));
    }

    public function setHttpMethod($method)
    {
        $this->opts[CURLOPT_HTTPGET] = $this->opts[CURLOPT_POST] = false;
        if ( isset( $this->opts[CURLOPT_CUSTOMREQUEST] ) ) {
            unset( $this->opts[CURLOPT_CUSTOMREQUEST] );
        }

        switch ( $method ) {
            case 'GET':
                $this->opts[CURLOPT_HTTPGET] = true;
                break;
            case 'POST':
                $this->opts[CURLOPT_POST] = true;
                break;
            default:
                $this->opts[CURLOPT_CUSTOMREQUEST] = $method;
        }
    }

    public function setPostData($data)
    {
        $this->setHttpMethod('POST');
        $this->opts[CURLOPT_POSTFIELDS] = is_string($data) ? $data : http_build_query($data);
    }

    public function getPostData()
    {
        return isset($this->opts[CURLOPT_POSTFIELDS]) ? $this->opts[CURLOPT_POSTFIELDS] : '<empty>';
    }

    public function getStartTime() {
        return $this->time;
    }

    public function getOpt($opt)
    {
        return isset($this->opts[$opt]) ? $this->opts[$opt] : false;
    }
    
    /**
     * Mock Interaction
     * A common pattern of using the Curl object is to create them via *new* whenever
     * they are required. Because of this, they are hard to stub out responses!
     * I propose a static method that takes a URL and a Response, and simply will return
     * the response to the next Curl object that attempts to request the url designated
     * by $url.
     */
    public static function createMockInteraction($url, CurlResponse $response)
    {
        self::$mock_responses[$url] = $response;
    }
    
    private static function getMockResponse($url)
    {
        return @self::$mock_responses[$url];
    }

    /**
     * Sets the curl transfer into SFTP mode. Convenient function so that you don't
     * have to remember the curl opts for SFTP.
     */
    public function setSftp($username, $password) {
        $this->setOpts(array(
            CURLOPT_PROTOCOLS => CURLPROTO_SFTP,
            CURLOPT_USERPWD => "$username:$password",
        ));
    }

    public function execute()
    {
        if ( self::$mock_responses ) {
            $url = $this->getOpt(CURLOPT_URL);
            if ( $r = self::getMockResponse($url) ) {
                return $r;
            }
        }
        $this->time = microtime(1);
        $ch = curl_init();
        curl_setopt_array($ch, $this->opts);
        $response = new CurlResponse;
        $response->setRequest( $this );
        $response->setResponse( curl_exec($ch), $ch );

        return $response;
    }
}