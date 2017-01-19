<?php

namespace ZTools\Curl;
use Exception;

class Curl
{
    const ResponseSuccess = 2;
    const ResponseApplicationError = 4;
    const ResponseServerError = 5;
    const ResponseRedirect = 3;
    const ResponseUnknown = -1;

    private $opts = array();
    private $time = 0;
    private $time_start;
    private $timer = 0;
    private $logger = null;
    private $extra_options;
    private $download_file;
    private $url;
    
    private static $mock_responses = array();

    static $defaults = array(

        'opts' => array(
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
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

    /**
     * Set Logger
     * @param string $log_class : declares the name of a class that is an ActiveRecord Model.
     * @param array $extra_opts : lets you define custom options for insertion into this log
     *     table. The defaults are: resource, request, and response.
     */
    public function setLogger($log_class, $extra_opts = array())
    {
        if ( class_exists($log_class) ) {
            $this->log_class = $log_class;
            $this->extra_options = $extra_opts;
            $obj = new $log_class;
            if ( $obj instanceof \ActiveRecord\Model ) {
                $this->logger = $obj;
                foreach($extra_opts as $key => $value) {
                    $this->logger->$key = $value;
                }
                return;
            }
        }

        // Trigger error instead of throw excpetion, as a logging problem should hardly
        // halt the execution of the program.
        trigger_error('There is a problem with this logging class: ' . $log_class);
    }
    
    public function downloadToFile($filename)
    {
        $this->download_file = $filename;
    }
    
    public function shouldPostProcess()
    {
        return !$this->download_file;
    }

    public function setOpts(array $opts)
    {
        $this->opts = $opts + $this->opts + self::$defaults->opts;
    }
    
    public function setExtraOptions($extra_opts)
    {
        $this->extra_options = $extra_opts;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        $this->setOpts(array(CURLOPT_URL => $url));
        if ( strpos( $url, '162.243.224.12') !== false ) {
            $this->setOpts(array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ));
        }
    }

    public function get($url)
    {
        $this->setUrl($url);
        return $this->execute();
    }
    
    public function addHeaders($headers)
    {
        $h = $this->getOpt(CURLOPT_HTTPHEADER) ?: [];
        $h = array_merge($h, $headers);
        $this->setHeaders($h);
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
        // remove query part from URL
        if ( $p = strpos($url, '?') ) {
            $url = substr($url, 0, $p);
        }
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
                $r->setRequest($this);
                return $r;
            }
        }
        
        $ch = curl_init();
        $fp = false; // file pointer for file downloads
        $hp = false; // file pointer for headers
        curl_setopt_array($ch, $this->opts);
        
        if ( $this->download_file ) {
            @mkdir(dirname($this->download_file));
            $fp = fopen($this->download_file, 'w+');
            $hp = fopen($this->download_file . '.h', 'w+');
            if ( $fp === false || $hp === false ) {
                throw new \Exception('Cannot access file to write curl response to.');
            }
            
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_WRITEHEADER, $hp);
        }
        
        $response = new CurlResponse;
        $response->setRequest( $this );
        $this->time = microtime(1);
        $this->time_start = date('Y-m-d H:i:s');
        $response->setResponse( curl_exec($ch), $ch );
        
        if ( $fp !== false ) {
            curl_close($ch);
            fclose($fp);
            fclose($hp);
        }

        if ( $this->logger != null ) {
            if ( $this->logger instanceof \ActiveRecord\Model ) {
                $this->setLogger($this->log_class,$this->extra_options);
            }
            else {
                $this->logger = new $this->log_class;
            }
            $this->logger->resource = $response->request_header;
            $this->logger->request = $response->request->getPostData();
            $this->logger->response = $response->raw_body;
            $this->logger->save();
            if ( $this->logger instanceof \ActiveRecord\Model ) {
                
            }
            else {
                $this->setLogger($this->log_class);
            }
        }

        return $response;
    }
    
    public function stopTimer()
    {
        $this->timer = microtime(1) - $this->time;
    }
    
    public function getTimer()
    {
        return $this->timer;
    }
    
    public function getReadableStartTime()
    {
        return $this->time_start;
    }
    
    public function getRenderedPOSTData()
    {
        return @$this->opts[CURLOPT_POSTFIELDS];
    }
    
    public function getUrl()
    {
        return $this->url;
    }
}
