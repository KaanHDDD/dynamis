<?php
/* HttpRequest:
 * A basic implementation of an Http Client
 *==============================================================================
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
 *------------------------------------------------------------------------------
 * Original Author: Jillian Ada Burrows
 * Email:           jill@adaburrows.com
 * Website:         <http://www.adaburrows.com>
 * Facebook:        <http://www.facebook.com/jillian.burrows>
 * Twitter:         @jburrows
 *------------------------------------------------------------------------------
 * Use at your own peril! J/K
 * 
 */

class http_request {
	//basic request parameters
	var $request_params;
	//string represntation of the request
	var $request;
	//array representation of the response
	var $response;
  // number of blocks to read.
  var $count;
  // block size in bytes
  var $bs;

	function http_request() {$this->__construct();}

	function __construct() {
		//basic request parameters
		$this->request_params = array(
			'port'		=> 80,
			'scheme'	=> '',
			'host'		=> 'localhost',
			'path'		=> '/',
			'method'	=> 'GET'
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

	/* explode_query
	 * -------------
	 * Takes a query string and turns it into a hash
	 */
	function explode_query($q) {
		$q = explode("&", $q);
		foreach($q as $value) {
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
	function build_query() {
		foreach($this->request_params['query_params'] as $key => $value) {
			$k = rawurlencode($key);
			$v = rawurlencode($value);
			$q[] = "$k=$v";
		}
		$query = implode("&", $q);
		return $query;
	}

	/* explode_headers
	 * ---------------
	 * Takes an array of string header lines and turns it into a hash format.
	 */
	function explode_headers($h) {
		$data = array();
		foreach($h as $header) {
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
	function build_headers() {
		foreach($this->request_params['header_params'] as $key => $value) {
			$header_line[] = "$key: $value";
		}
		$headers = implode("\r\n", $header_line);
		return $headers;
	}

	/* build_request
	 * -------------
	 * Takes the request array and turns it into a usable string.
	 */
	function build_request() {
		$query = (isset($this->request_params['query_params'])) ? ($this->build_query()) : '';
    if($this->request_params['method'] == 'GET') {
  		$request  = "{$this->request_params['method']} {$this->request_params['path']}?$query HTTP/1.1\r\n";
    } else {
  		$request  = "{$this->request_params['method']} {$this->request_params['path']} HTTP/1.1\r\n";
    }
		$request .= "Host: {$this->request_params['host']}\r\n";
		$request .= "Connection: Close\r\n";
		if ($this->request_params['method'] == 'POST') {
			$request .= 'Content-Length: '.strlen($this->request_params['body'])."\r\n";
			$request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}
		if (isset($this->request_params['header_params'])) {
			$request .= $this->build_headers();
		}
		$request .= "\r\n";
		if (isset($this->request_params['body'])) {
			$request .= $query."\r\n".$this->request_params['body'];
		}
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
	function tx_request() {
		$scheme = isset($this->request_params['scheme']) ? $this->request_params['scheme'] : '';
		$port = isset($this->request_params['port']) ? $this->request_params['port'] : 80;
		$fp = @fsockopen($scheme.$this->request_params['host'], $port);
		//TODO: add better error handling than this!
		if (!is_resource($fp)){
			throw new Exception('connection failed');
		}
		$this->build_request();
		if (!fputs($fp, $this->request, strlen($this->request))) {
			fclose($fp);
			throw new Exception('request failed');
		}
		$response = '';
    if (!$this->count) {
  		while (!feof($fp)){
  			$response .= fread($fp, $this->bs);
  		}
    } else {
      for ($i = 0; $i < $this->count; $i++) {
        $response .= fread($fp, $bs);
      }
    }
		return($response);
	}

	/* unchunk
	 * -------
	 * Takes chunky data and composes it.
	 */
	function unchunk($chunky_data) {
    $data = '';
    $chunks = explode("\r\n", $chunky_data);
    for($i = 1; $i < count($chunks); $i = $i + 2) {
       $data .= $chunks[$i];
    }
    return $data;
  }

	/* parse_response
	 * --------------
	 * Takes the string representation of the response and parses it into
	 * it's components for easy digestion.
	 */
	function parse_response($response) {
		$parts = explode("\r\n\r\n", $response);
    $header = $parts[0];
    $headers = explode("\r\n", $header);
    $headers = $this->explode_headers($headers);
    $message = $parts[1];

		$status = array_shift($headers);
		$status = explode(" ", $status);
		$protocol = array_shift($status);
		$protocol = explode('/', $protocol);
		$this->response['headers'] = $headers;

		$this->response['status'] = array_shift($status);
		$this->response['reason'] = array_shift($status);
		$this->response['protocol'] = array_shift($protocol);
		$this->response['protocol_version'] = array_shift($protocol);

    if(isset($headers['transfer-encoding']) && $headers['transfer-encoding'] == 'chunked') {
      $message = $this->unchunk($message);
    }
		$this->response['body'] = $message;

		return $this->response['status'];
	}

	/* do_request
	 * ----------
	 * Executes the request and parses status
	 */
	function do_request() {
		$resp = $this->tx_request();
		$status = $this->parse_response($resp);
		//TODO: parse response and generate error message
		$r_stat = true;
		//Reset method to 'GET' for next call
		$this->request_params['method'] = 'GET';
		return $r_stat;
	}

	/* get_data
	 * --------
	 * Fetches the data from the reponse
	 */
	function get_data() {
		//TODO: make this return null when not set
		return $this->response['body'];
	}

}
