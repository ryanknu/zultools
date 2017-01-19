<?php

namespace ZTools\Curl;

use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\AbstractTableGateway;

class CurlTable extends AbstractTableGateway
{
    protected $table = 'curl_logs';
    private $read_only = false;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet;
        $this->initialize();
    }
    
    public function disableWrites()
    {
        $this->read_only = true;
    }
    
    public function save($data)
    {
        if ( !$this->read_only ) {
            $this->insert($data);
        }
    }
    
    public function getById($id)
    {
        $rowset = $this->select(array('id' => $id));
        if( $rowset->count() ) {
            return $rowset->current();
        }
        else {
            throw new \Exception("Curl Logs Not Found for the ID:". $id);
        }
    }
}
