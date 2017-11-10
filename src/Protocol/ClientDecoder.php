<?php

namespace Clue\React\Soap\Protocol;

use \SoapClient;

class ClientDecoder extends SoapClient
{
    private $response = null;

    public function decode($response, $function, $args)
    {
        // temporarily save response internally for further processing
        $this->response = $response;

        // pretend we just invoked a SOAP function.
        // internally, use the injected response to parse its results
        $ret = $this->__soapCall($function, $args);
        $this->response = null;

        return $ret;
    }

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        // the actual result doesn't actually matter, just return the given result
        // this will be processed internally and will return the parsed result
        return $this->response;
    }
}
