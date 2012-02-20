<?php

/* HttpRequest:
 * A basic implementation of an Http Client
 * ==============================================================================
 * -- Version alpha 0.1 --
 * This code is being released under an MIT style license:
 *
 * Copyright (c) 2010 Jillian Ada Burrows
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 * ------------------------------------------------------------------------------
 * Original Author: Jillian Ada Burrows
 * Email:           jill@adaburrows.com
 * Website:         <http://www.adaburrows.com>
 * Facebook:        <http://www.facebook.com/jillian.burrows>
 * Twitter:         @jburrows
 * ------------------------------------------------------------------------------
 * Use at your own peril! J/K
 *
 */

class http_request {

    //basic request parameters
    protected $request_params;
    //string represntation of the request
    protected $request;
    //array representation of the response
    protected $response;
    // number of blocks to read.
    protected $count;
    // block size in bytes
    protected $bs;

    public function __construct() {
        //basic request parameters
        $this->request_params = array(
            'port' => 80,
            'scheme' => '',
            'host' => 'localhost',
            'path' => '/',
            'method' => 'GET'
        );
        //string represntation of the request
        $this->request = '';
        //array representation of the response
        $this->response = array();
        // set count to false --> read in all content
        $this->count = false;
        // set block size in bytes to 4096 to speed up reading in response
        $this->bs = 4096;
    }

    /* add_header();
     * -------------
     * Add basic auth to a request, should really only be used over TLS or SSL
     */
     public function add_header($field, $data) {
         if(empty($this->request_params['header_params'])) {
             $this->request_params['header_params'] = array();
         }
         $this->request_params['header_params'] = array_merge(
             $this->request_params['header_params'],
             array($field => $data)
         );
     }

    /* add_basic_auth();
     * -----------------
     * Add basic auth to a request, should really only be used over TLS or SSL
     */
     public function add_basic_auth($user, $pass) {
         $auth_b64 = base64_encode("$user:$pass");
         $this->request_params['auth'] = "Basic $auth_b64";
     }

    /* explode_query
     * -------------
     * Takes a query string and turns it into a hash
     */
    protected function explode_query($q) {
        $q = explode("&", $q);
        foreach ($q as $value) {
            $datum = explode("=", $value);
            $k = rawurldecode(array_shift($datum));
            $v = rawurldecode(array_shift($datum));
            $data[$k] = $v;
        }
        return $data;
    }

    /* build_query
     * -----------
     * Builds a query string from a query hash.
     */
    protected function build_query($data) {
        foreach ($data as $key => $value) {
            $k = rawurlencode($key);
            if(is_array($value)) {
              foreach($value as $vu) {
                $v = rawurlencode($vu);
                $q[] = "$k=$v";
              }
            } else {
              $v = rawurlencode($value);
              $q[] = "$k=$v";
            }
        }
        $query = implode("&", $q);
        return $query;
    }

    /* explode_headers
     * ---------------
     * Takes an array of string header lines and turns it into a hash format.
     */
    protected function explode_headers($h) {
        $data = array();
        foreach ($h as $header) {
            $datum = explode(": ", $header);
            $k = rawurldecode(array_shift($datum));
            $v = rawurldecode(array_shift($datum));
            $data[strtolower($k)] = $v;
        }
        return $data;
    }

    /* build_headers
     * -------------
     * Takes an header array and returns a string version.
     */
    protected function build_headers() {
        foreach ($this->request_params['header_params'] as $key => $value) {
            $header_line[] = "$key: $value";
        }
        $headers = implode("\r\n", $header_line);
        return $headers;
    }

    /* build_request
     * -------------
     * Takes the request array and turns it into a usable string.
     */
    protected function build_request() {
        $query = '';
        if(!empty ($this->request_params['query_params'])) {
            $query = http_build_query($this->request_params['query_params']);
        }
        // Set the verb, path, protocol, and version.
        // if this is a GET append query parameters
        // if not don't append
        if ($this->request_params['method'] == 'GET' || $this->request_params['method'] == 'DELETE') {
            $request = "{$this->request_params['method']} {$this->request_params['path']}?$query HTTP/1.1\r\n";
        } else {
            $request = "{$this->request_params['method']} {$this->request_params['path']} HTTP/1.1\r\n";
        }
        // Set the host parameter
        $request .= "Host: {$this->request_params['host']}\r\n";
        // If an auth type has been requested, add it here
        if (!empty($this->request_params['auth'])) {
            $request .= "Authorization: {$this->request_params['auth']}\r\n";
        }
        // If header parameters are set, include them here
        if (isset($this->request_params['header_params'])) {
            $request .= $this->build_headers();
        }
        // Set the connection type
        $request .= "Connection: Close\r\n";
        // If it's a post, process what we're sending
        if ($this->request_params['method'] == 'POST' || $this->request_params['method'] == 'PUT') {
            // If the content-type has not been set, set it
            if (empty($this->request_params['content-type']) || $this->request_params['content-type'] == null) {
                $content_type = 'application/x-www-form-urlencoded';
            } else {
                $content_type = $this->request_params['content-type'];
            }
            // If a body has been set, use it; otherwise try the query params
            if(!empty($this->request_params['body'])) {
                // If the body is an array, then it should probably be serialized
                // in url-encode fashion
                if(is_array($this->request_params['body'])) {
                    $this->request_params['body'] = http_build_query($this->request_params['body']);
                }
            } else {
                $this->request_params['body'] = $query;
            }
            // Set the content length
            $request .= 'Content-Length: ' . strlen($this->request_params['body']) . "\r\n";
            // Set the content type
            $request .= "Content-Type: {$content_type}\r\n";
        }
        // Add the space to indicate the body
        $request .= "\r\n";
        if (isset($this->request_params['body'])) {
            $request .= $this->request_params['body'];
        }
        // End the request
        $request .= "\r\n";
        $this->request = $request;
    }

    /* tx_request
     * ----------
     * Transceives the current request. Opens socket and submits request,
     * reads response, closes the connection, then return the response.
     * 
     * $bs specifies the block size to read: defaults to 4096 for faster reads.
     * $count specifies how many blocks to read in: by default all are read.
     */
    protected function tx_request() {
        $scheme = isset($this->request_params['scheme']) ? $this->request_params['scheme'] : '';
        $port = isset($this->request_params['port']) ? $this->request_params['port'] : 80;
        $fp = @fsockopen($scheme . $this->request_params['host'], $port);
        //TODO: add better error handling than this!
        if (!is_resource($fp)) {
            throw new Exception('connection failed');
        }
        $this->build_request();
        if (!fputs($fp, $this->request, strlen($this->request))) {
            fclose($fp);
            throw new Exception('request failed');
        }
        $response = '';
        if (!$this->count) {
            while (!feof($fp)) {
                $response .= fread($fp, $this->bs);
            }
        } else {
            for ($i = 0; $i < $this->count; $i++) {
                $response .= fread($fp, $this->bs);
            }
        }
        return($response);
    }

    /* unchunk
     * -------
     * Takes chunky data and composes it.
     */
    protected function unchunk($chunky_data) {
        $data = '';
        $chunks = explode("\r\n", $chunky_data);
        for ($i = 1; $i < count($chunks); $i = $i + 2) {
            $data .= $chunks[$i];
        }
        return $data;
    }

    /* parse_response
     * --------------
     * Takes the string representation of the response and parses it into
     * it's components for easy digestion.
     */
    protected function parse_response($response) {
        $parts = explode("\r\n\r\n", $response);
        $header = $parts[0];
        $message = $parts[1];

        //Expand the lines in the header response into an array
        $headers = explode("\r\n", $header);
        //Get status line, since it doesn't follow the same format as the headers
        $status_line = array_shift($headers);
        //Explode into parts
        $status_parts = explode(" ", $status_line);
        //Grab the protocol
        $protocol = array_shift($status_parts);
        //Explode into parts
        $protocol = explode('/', $protocol);
        //Grab the status code
        $this->response['status'] = array_shift($status_parts);
        //Grab the reason/explaination for the code
        $this->response['reason'] = array_shift($status_parts);
        //Grab the actual protocol
        $this->response['protocol'] = array_shift($protocol);
        //Grab the version of the protocol
        $this->response['protocol_version'] = array_shift($protocol);

        //Finish processing the headers
        $headers = $this->explode_headers($headers);
        $this->response['headers'] = $headers;

        if (isset($headers['transfer-encoding']) && $headers['transfer-encoding'] == 'chunked') {
            $message = $this->unchunk($message);
        }
        $this->response['body'] = $message;

        return $this->response['status'];
    }

    /* do_request
     * ----------
     * Executes the request and parses status
     */
    public function do_request() {
        $resp = $this->tx_request();
        $status = $this->parse_response($resp);
        return $status;
    }

    /* get()
     * -----
     * Requests an object.
     * Returns returns raw text response.
     */
    public function get($object, $params = array()) {
		$this->reset();
		$this->request_params['method'] = 'GET';
        $this->request_params['path'] = $object;
        $this->request_params['query_params'] = $params;
        $object = $this->do_request() ? $this->get_data() : null;
        return $object;
    }

    /* post()
     * ------
     * Posts the specified data to the object location.
     * Returns returns raw text response.
     */
    public function post($object, $data, $content_type = null) {
		$this->reset();
        $this->request_params['method'] = 'POST';
        $this->request_params['path'] = $object;
		if (is_array($data)) {
			$this->request_params['query_params'] = $data;
		} else {
			$this->request_params['body'] = $data;
		}
        $this->request_params['content-type'] = $content_type;
        $object = $this->do_request() ? $this->get_data() : null;
        return $object;
    }

    /* put()
     * ------
     * Puts the specified data to the object location.
     * Returns returns raw text response.
     */
    public function put($object, $data, $content_type = null) {
		$this->reset();
        $this->request_params['method'] = 'PUT';
        $this->request_params['path'] = $object;
		if (is_array($data)) {
			$this->request_params['query_params'] = $data;
		} else {
			$this->request_params['body'] = $data;
		}
        $this->request_params['content-type'] = $content_type;
        $object = $this->do_request() ? $this->get_data() : null;
        return $object;
    }

    /* delete()
     * --------
     * Deletes an object if you have permissions.
     * Returns returns raw text response.
     */
    public function delete($object, $params = array()) {
		$this->reset();
        $this->request_params['method'] = 'DELETE';
        $this->request_params['path'] = $object;
		$this->request_params['query_params'] = $params;
        $object = $this->do_request() ? $this->get_data() : null;
        return $object;
    }

	public function reset() {
		unset($this->request_params['method']);
		unset($this->request_params['path']);
		unset($this->request_params['body']);
		unset($this->request_params['content-type']);
		unset($this->request_params['query_params']);
	}

    /* get_data
     * --------
     * Fetches the data from the reponse
     */
    public function get_data() {
        return isset($this->response['body']) ? $this->response['body'] : NULL;
    }

    /* get_status
     * ----------
     * Fetches the reponse status
     */
    public function get_status() {
        return isset($this->response['status']) ? $this->response['status'] : NULL;
    }

    /* get_reason
     * ----------
     * Fetches the reason for the status
     */
    public function get_reason() {
        return isset($this->response['reason']) ? $this->response['reason'] : NULL;
    }

    /* get_headers
     * -----------
     * Fetches the headers from the reponse
     */
    public function get_headers() {
        return isset($this->response['headers']) ? $this->response['headers'] : NULL;
    }

    /* get_protocol
     * ------------
     * Fetches the protocol given by the server
     */
    public function get_protocol() {
        return isset($this->response['protocol']) ? $this->response['protocol'] : NULL;
    }

    /* get_protocol_version()
     * ----------------------
     * Fetches the data from the reponse
     */
    public function get_protocol_version() {
        return isset($this->response['protocol_version']) ? $this->response['protocol_version'] : NULL;
    }

}
