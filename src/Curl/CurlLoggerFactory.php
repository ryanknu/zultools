<?php

namespace ZTools\Curl;
use Zend\Db\Adapter\Adapter;
use Interop\Container\ContainerInterface;

class CurlLoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $adapter = @$config['curl_logs_adapter'] ?: Adapter::class;
        $disbale = !@$config['enable_curl_logs'];
        $dbAdapter = $container->get($adapter);
        
        $ct = new CurlTable($dbAdapter);
        if ( $disbale ) {
            $ct->disableWrites();
        }
        
        return new CurlLogger($ct);
    }
}
