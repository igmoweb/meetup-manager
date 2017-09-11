<?php
/**
 * @package    Meetup
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 */
class Meetup_Manager_API extends Meetup
{

	/**
	 * The response object from the request
	 * @var mixed
	 */
	protected $_response_headers = null;


	/**
	 * Stub for fetching events
	 *
	 * @param array $parameters The parameters passed for this request
	 * @return mixed A json object containing response data
	 * @throws Exception if anything goes wrong
	 */
	public function getEvents(array $parameters = array())
	{
		return $this->get('/:urlname/events', $parameters);
	}

	/**
	 * Stub for fetching single event
	 *
	 * @param array $parameters The parameters passed for this request
	 * @return mixed A json object containing response data
	 * @throws Exception if anything goes wrong
	 */
	public function getEvent(array $parameters = array())
	{
		return $this->get('/:urlname/events/:id', $parameters);
	}

	/**
	 * Stub for fetching a member
	 *
	 * @param array $parameters The parameters passed for this request
	 * @return mixed A json object containing response data
	 * @throws Exception if anything goes wrong
	 */
	public function getMember(array $parameters = array())
	{
		return $this->get('/2/member/:id', $parameters);
	}

	/**
	 * Stub for fetching list of attendants
	 *
	 * @param array $parameters The parameters passed for this request
	 * @return mixed A json object containing response data
	 * @throws Exception if anything goes wrong
	 */
	public function getEventAttendants(array $parameters = array())
	{
		return $this->get('/:urlname/events/:id/attendance', $parameters);
	}
	/**
	 * Main routine that all requests go through which handles the CURL call to the server and
	 * prepares the request accordingly.
	 *
	 * @param array $parameters The parameters passed for this request
	 * @throws Exception if anything goes wrong
	 * @note The parameter 'sign' is automatically included with value 'true' if using an api key
	 */
	protected function api($url, $parameters, $action=self::GET)
	{
		//merge parameters
		$params = array_merge($parameters, $this->_parameters);

		//make sure 'sign' is included when using api key only
		if(in_array('key', $params) && $url!=self::ACCESS && $url!=self::AUTHORIZE)
		{
			//api request (any) - include sign parameters
			$params = array_merge( array('sign', 'true'), $params );
		}

		switch ( $action ) {
			case 2: { $method = 'POST'; break; }
			case 3: { $method = 'PUT'; break; }
			case 4: { $method = 'DELETE'; break; }
			default: { $method = 'GET'; break; }
		}

		$request_params = array(
			'method' => $method,
			'sslverify' => false,
			'timeout' => 120,
			'user-agent' => $_SERVER['HTTP_USER_AGENT']
		);

		$headers = array();

		$request_url = $url;

		//either GET/POST/PUT/DELETE against api
		if($action==self::GET || $action==self::DELETE)
		{
			//GET + DELETE

			//include headers as specified by manual
			if( $url == self::ACCESS )
			{
				array_push($headers, 'Content-Type: application/x-www-form-urlencoded');
			}
			else if( strpos($url, self::BASE) === 0 && in_array('access_token', $params) )
			{
				array_merge($params, array('token_type'=>'bearer'));
			}

			$request_url .= (!empty($params) ? ('?' . http_build_query($params)) : '');
		}
		else
		{
			//POST + PUT
			$request_params['body'] = $params;
		}

		$request_params['headers'] = $headers;

		//fetch content
		$result  = wp_remote_request( $request_url, $request_params );

		if ( is_wp_error( $result ) ) {
			throw new Exception("Failed retrieving  '" . $url . "' because of connection issue [" . $result->get_error_code() . "] ' " . $result->get_error_message() . "'.");
		}
		$response_body = json_decode( wp_remote_retrieve_body( $result ) );
		$response_headers = wp_remote_retrieve_headers( $result );
		$response_code = wp_remote_retrieve_response_code( $result );

		//retrieve json and store it internally
		$this->_response = $response_body;
		$this->_response_headers = $response_headers;

		if (!is_null($this->_response) && ($response_code < 200 || $response_code >= 300))
		{
			//tell them what went wrong or just relay the status
			if( isset($this->_response->error) && isset($this->_response->error_description) )
			{
				//what we see against Oath
				$error = $this->_response->error . ' - ' . $this->_response->error_description;
			}
			else if( isset($this->_response->details) && isset($this->_response->problem) && isset($this->_response->code) )
			{
				//what we see against regular access
				$error = $this->_response->code . ' - ' . $this->_response->problem . ' - ' . $this->_response->details;
			}
			else
			{
				$error = 'Status ' . $response_code;
			}

			throw new Exception("Failed retrieving  '" . $url . "' because of ' " . $error . "'.");
		}
		else if (is_null($this->_response))
		{
			//did we have any parsing issues for the response?
			switch (json_last_error())
			{
				case JSON_ERROR_NONE:
					$error = 'No errors';
					break;
				case JSON_ERROR_DEPTH:
					$error = 'Maximum stack depth exceeded';
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$error = ' Underflow or the modes mismatch';
					break;
				case JSON_ERROR_CTRL_CHAR:
					$error = 'Unexpected control character found';
					break;
				case JSON_ERROR_SYNTAX:
					$error = 'Syntax error, malformed JSON';
					break;
				case JSON_ERROR_UTF8:
					$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
					break;
				default:
					$error = 'Unknown error';
					break;
			}

			throw new Exception("Cannot read response by  '" . $url . "' because of: '" . $error . "'.");
		}

		return $this->_response;
	}
}