<?php

namespace PaZa\Curl;

class Curl {
    protected $url              = '';
    protected $postParams       = NULL;
    protected $requestHeaders   = NULL;
    protected $responseHeaders  = NULL;
    protected $body             = NULL;
    protected $requestDone      = false;

    public function __construct($url) {
        $this->url = $url;
    }

    public function setPostParams($postParams) {
        $this->postParams = $postParams;
    }

    public function setRequestHeaders($requestHeaders) {
        $this->requestHeaders = $requestHeaders;

        #curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        #    #'Cookie: ASP.NET_SessionId=01ifdrxoarhhbm5jitqxmqip'
        #));
    }

    /**
     * gets the page content of a page using a curl http post request
     *
     * @return string
     */
    public function getContent() {
        $this->doRequest();

        return $this->responseBody;
    }

    public function getResponseHeaders() {
        $this->doRequest();

        return $this->responseHeaders;
    }
    
    public function getResponseHeader($key) {
        if(isset($this->responseHeaders[$key])) {
            return $this->responseHeaders[$key];
        }
        return NULL;
    }

    public function doRequest() {

        if(true === $this->requestDone) {
            return;
        }

        $this->requestDone = true;

        // open connection
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if(!empty($this->requestHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->requestHeaders);
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);

        // set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $this->url);

        if(!is_null($this->postParams)) {
            curl_setopt($ch, CURLOPT_POST, count($this->postParams));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->postParams));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // execute post
        $response               = curl_exec($ch);
        $header_size            = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->setResponseHeaders(substr($response, 0, $header_size));
        $this->responseBody     = substr($response, $header_size);

        // close connection
        curl_close($ch);

        return $this->responseBody;
    }

    protected function setResponseHeaders($headerString) {
        $responseHeaders    = array();
        $responseHeadersRaw = explode("\n", trim($headerString));

        foreach($responseHeadersRaw as $header) {
            $headerSplit = explode(':', $header);

            if(2 === count($headerSplit)) {
                $responseHeaders[trim($headerSplit[0])] = trim($headerSplit[1]);
            }
        }

        $this->responseHeaders = $responseHeaders;
    }

    public function toString() {
        return serialize(array(
            'url' => $this->url,
            'params' => $this->postParams,
            'post' => !is_null($this->postParams)
        ));
    }
}