<?php

/* Fb
 * An easy to use, basic implementation of Facebook's Graph API for PHP
 * ==============================================================================
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
 * Github:          <http://github.com/jburrows>
 * Facebook:        <http://www.facebook.com/jillian.burrows>
 * Twitter:         @jburrows
 * ------------------------------------------------------------------------------
 */

// make sure we can access the http_request class
app::getLib('OAuth2');

class facebook_graph extends OAuth2 {
    protected $old_domain;
    protected $signed_request;

    /* initialize
     * ----------
     * Initializes the variables required to communicate with facebook.
     */
    public function initialize() {
        $this->user_auth = 'https://www.facebook.com/dialog/oauth';
        $this->domain = 'graph.facebook.com';
        $this->old_domain = 'api.facebook.com';
        $this->token_endpoint = '/oauth/access_token';
        $this->permission_delim = ',';
        $this->request_params['host'] = $this->domain;
        $this->signed_request = null;

        if(!empty($_REQUEST['signed_request'])){
            $this->parse_signed_request($_REQUEST['signed_request']);
        }
    }
    /* parse_signed_request
     * --------------------
     * Parses a signed_request parameter and returns the decoded array
     *
     * Thank you Facebook! I ripped this off your docs and modified it
     *   + now it's extensible if more signing algorithms become available.
     */
    private function parse_signed_request($signed_request) {
        list($encoded_sig, $payload) = explode('.', $signed_request, 2);

        // decode the data
        $sig = $this->_base64_url_decode($encoded_sig);
        $data = json_decode($this->_base64_url_decode($payload), true);
        $algorithm = strtoupper($data['algorithm']);
        switch($algorithm) {
            case 'HMAC-SHA256':
                // check sig
                $expected_sig = hash_hmac('sha256', $payload, $this->app_secret, $raw = true);
                if ($sig == $expected_sig) {
                    $this->signed_request = $data;
                } else {
                    error_log('Bad Signed JSON signature!');
                }
                break;
            default:
                error_log('Unknown algorithm. Expected HMAC-SHA256');
        }
    }

    /* _base64_url_decode
     * ------------------
     * Performs a base64 URL decoding,
     * not to be confused with just plain base 64 (en/de)coding
     */
    protected function _base64_url_decode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /* _has_id
     * -------
     * Determines if the resulting object has an id
     */
    private function _has_id($object) {
        if ($object != null) {
            $object = json_decode($object, true);
            $object = $object['id'];
        }
        return $object;
    }

    /* _has_data
     * ---------
     * Determines if the resulting object has data
     */
    private function _has_data($object) {
        if ($object != null) {
            $object = json_decode($object, true);
        }
        return isset($object['data']) ? $object['data'] : null;
    }

    /* _has_meta
     * ---------
     * Determines if the resulting object has meta data.
     */
    private function _has_meta($object) {
        if ($object != null) {
            $object = json_decode($object, true);
        }
        return isset($object['metadata']) ? $object['metadata'] : null;
    }


    /* _update_token()
     * ----------------
     * Executes the oauth token endpoint and sets the proper properties
     */
    protected function _update_token() {
        $this->request_params['method'] = 'GET';
        $this->request_params['path'] = "{$this->token_endpoint}";
        $data = $this->do_request() ? $this->get_data() : null;
        if(json_decode($data) == null) {
            $values = array();
            parse_str($data, $values);
            $access_token = isset($values['access_token']) ? $values['access_token'] : null;
            $expiration = isset($values['expires']) ? $values['expires'] : null;
            $this->access_token = $access_token;
            $this->expiration = $expiration;
        } else {
            throw new Exception("Error in retreiving token: {$data}");
        }
    }

    /* fql
     * ---
     * Runs an fql query
     */
    public function fql($fql) {
        $result = $this->get('/fql', array('q' => $fql));
        return $result;
    }

    /* multi_fql
     * ---
     * Runs a multi.fql query
     */
    public function multi_fql($multi_fql_array) {
        $result = null;
        return $result;
    }

    /* mutual_friends
     * --------------
     * Returns the mutual friends of the $target user and either
     *  + the current user if, $source is not specified
     *  + the $source user
     */
    public function mutual_friends($target, $source = null) {
        $result = null;
        return $result;
    }
    /* get_connection_types
     * --------------------
     * Requests an object's connection types to the FB social graph. Returns
     * a hash of connection types as keys and links to the respective api
     * calls.
     * <http://developers.facebook.com/docs/api>
     */
    public function get_relationships($object) {
        $data = null;
        $this->get($object, array(
            'metadata' => 1
        ));
        $data = $this->do_request() ? $this->get_data() : null;
        $meta = $this->_has_meta($data);
        if (($meta != null) && isset($meta['connections'])) {
            $data = $meta['connections'];
        }
        return $data;
    }

    /* get_connections
     * ---------------
     * Requests an object's connections to other objects on the FB graph.
     * $relation can be: any of: (friends, home, feed (Wall), likes, 
     * movies, books, notes, photos, videos, events, groups).
     * However, call get_connection_types to get a real list of connection
     * an object supports.
     * <http://developers.facebook.com/docs/api>
     */
    public function get_connections($object, $relation) {
        $object = $this->get("$object/$relation");
        return $this->_has_data($object);
    }

	/* get_accounts
	 * ------------
	 * Requests all of your accounts, pretty much your pages.
	 *
	 * <http://developers.facebook.com/docs/api>
	 */
	public function get_accounts() {
		$object = $this->get("me/accounts");
		return $this->_has_data($object);
	}

    /* post_feed
     * ---------
     * Posts a message to $profile_id's feed and returns the message id.
     * This is basically a way to post to a user's wall, But can be used to
     * post to pages or events.
     *
     * Message parameters: message, picture, link, name, description
     *
     * <http://developers.facebook.com/docs/api>
     */
    public function post_feed($profile_id, $params) {
        $body = $this->_add_auth($params);
        $object = $this->post("$profile_id/feed", $body);
        return $this->_has_id($object);
    }

    /* post_like
     * ---------
     * Likes a post.
     *
     * Message parameters: none
     *
     * <http://developers.facebook.com/docs/api>
     */
    public function post_like($post_id, $params) {
        $body = $this->_add_auth($params);
        $object = $this->post("$post_id/likes", $body);
        return $this->_has_id($object);
    }

    /* post_comment
     * ------------
     * Posts a comment to $post_id and returns the comment id.
     *
     * Message parameters: message
     *
     * <http://developers.facebook.com/docs/api>
     */
    public function post_comment($post_id, $params) {
        $body = $this->_add_auth($params);
        $object = $this->post("$post_id/comments", $body);
        return $this->_has_id($object);
    }

    /* post_note
     * ---------
     * Posts a note to $profile_id's feed and returns the note id.
     *
     * Message parameters: subject, message (an HTML string)
     *
     * <http://developers.facebook.com/docs/api>
     */
    public function post_note($profile_id, $params) {
        $body = $this->_add_auth($params);
        $object = $this->post("$profile_id/notes", $body);
        return $this->_has_id($object);
    }

    /* post_link
     * ---------
     * Posts a link to $profile_id and returns the link id.
     *
     * Message parameters: link, message
     *
     * <http://developers.facebook.com/docs/api>
     */
    public function post_link($profile_id, $params) {
        $body = $this->_add_auth($params);
        $object = $this->post("$profile_id/links", $body);
        return $this->_has_id($object);
    }

}
