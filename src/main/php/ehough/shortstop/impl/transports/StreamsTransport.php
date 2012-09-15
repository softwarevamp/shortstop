<?php
/**
 * Copyright 2012 Eric D. Hough (http://ehough.com)
 *
 * This file is part of shortstop (https://github.com/ehough/shortstop)
 *
 * shortstop is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * shortstop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with shortstop.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Lifted from http://core.trac.wordpress.org/browser/tags/3.0.4/wp-includes/class-http.php
 *
 * HTTP request method uses Streams to retrieve the url.
 *
 * Requires PHP 5.0+ and uses fopen with stream context. Requires that 'allow_url_fopen' PHP setting
 * to be enabled.
 *
 * Second preferred method for getting the URL, for PHP 5.
 */
class ehough_shortstop_impl_transports_StreamsTransport extends ehough_shortstop_impl_transports_AbstractHttpTransport
{
    private static $_stream_http_transport        = 'http';
    private static $_stream_metadata_timedout     = 'timed_out';
    private static $_stream_metadata_data_wrapper = 'wrapper_data';
    private static $_stream_metadata_data_headers = 'headers';

    private static $_http_context_option_method       = 'method';
    private static $_http_context_option_header       = 'header';
    private static $_http_context_option_useragent    = 'user_agent';
    private static $_http_context_option_ignoreerrors = 'ignore_errors';
    private static $_http_context_option_timeout      = 'timeout';
    private static $_http_context_option_content      = 'content';
    private static $_http_context_option_protocol     = 'protocol_version';

    private static $_fopen_mode_readonly = 'r';

    private $_streamContext;

    private $_streamResultMeta;

    private $_httpMessageParser;

    private $_statusLine;

    /** @var ehough_epilog_api_ILogger */
    private $_logger;

    public function __construct(ehough_shortstop_spi_HttpMessageParser $messageParser)
    {
        parent::__construct($messageParser);

        $this->_httpMessageParser = $messageParser;
        $this->_logger            = ehough_epilog_api_LoggerFactory::getLogger('Streams Transport');
    }

    /**
     * Perform optional setup to handle a new HTTP request.
     *
     * @param ehough_shortstop_api_HttpRequest $request The HTTP request to handle.
     *
     * @return void
     */
    protected function prepareToHandleNewRequest(ehough_shortstop_api_HttpRequest $request)
    {
        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug('Creating stream context...');
        }

        $streamParams = array(self::$_stream_http_transport => array(

            self::$_http_context_option_header       => $this->_httpMessageParser->getHeaderArrayAsString($request),
            self::$_http_context_option_method       => $request->getMethod(),
            self::$_http_context_option_useragent    => $request->getHeaderValue(ehough_shortstop_api_HttpRequest::HTTP_HEADER_USER_AGENT),
            self::$_http_context_option_ignoreerrors => true,
            self::$_http_context_option_content      => $request->getEntity() === null ? null : $request->getEntity()->getContent(),
            self::$_http_context_option_protocol     => '1.0' //use HTTP 1.0 unless you want to make this SLOW
        ));

        $this->_streamContext = stream_context_create($streamParams);
    }

    /**
     * Perform handling of the given request.
     *
     * @param ehough_shortstop_api_HttpRequest $request The HTTP request.
     *
     * @throws ehough_shortstop_api_exception_RuntimeException If something goes wrong.
     *
     * @return string The raw response for this request. May be empty or null.
     */
    protected function handleRequest(ehough_shortstop_api_HttpRequest $request)
    {
        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug('Calling fopen()...');
        }

        $handle = @fopen($request->getUrl()->toString(), self::$_fopen_mode_readonly, false, $this->_streamContext);

        if (! $handle) {

            throw new ehough_shortstop_api_exception_RuntimeException(sprintf('Could not open handle for fopen() to %s', $request));
        }

        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug(sprintf('Successfully used fopen() to get a handle to %s', $request->getUrl()));
        }

        /* set the timeout to 5 seconds */
        stream_set_timeout($handle, 5);

        /* read stream contents */
        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug('Reading stream contents...');
        }
        $strResponse = stream_get_contents($handle);

        /* read stream metadata */
        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug('Reading stream metadata...');
        }
        $this->_streamResultMeta = stream_get_meta_data($handle);

        /* close the stream... */
        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug('Closing stream...');
        }

        @fclose($handle);

        if ($this->_streamResultMeta[self::$_stream_metadata_timedout]) {

            throw new ehough_shortstop_api_exception_RuntimeException(sprintf('Timed out while waiting for %s', $request));
        }

        return $this->_buildRawResponse($strResponse);
    }

    /**
     * Get the name of this transport.
     *
     * @return string The name of this transport.
     */
    protected function getTransportName()
    {
        return 'Streams';
    }

    /**
     * Get the response code.
     *
     * @throws ehough_shortstop_api_exception_RuntimeException If something goes wrong.
     *
     * @return int the HTTP response code.
     */
    protected function getResponseCode()
    {
        $pieces = explode(' ', $this->_statusLine);

        if (count($pieces) < 2) {

            throw new ehough_shortstop_api_exception_RuntimeException('Invalid status line: ' . $this->_statusLine);
        }

        $code = $pieces[1];

        return intval($code);
    }

    /**
     * Perform optional tear down after handling a request.
     *
     * @return void
     */
    protected function tearDown()
    {
        unset($this->_streamContext);
        unset($this->_streamResultMeta);
        unset($this->_httpMessageParser);
    }

    /**
     * Determines whether or not this transport is available on the system.
     *
     * @return bool True if this transport is available on the system. False otherwise.
     */
    function isAvailable()
    {
        if (! function_exists('fopen')) {

            if ($this->_logger->isDebugEnabled()) {

                $this->_logger->debug('fopen() is not available.');
            }

            return false;
        }

        if (function_exists('ini_get') && ini_get('allow_url_fopen') != true) {

            if ($this->_logger->isDebugEnabled()) {

                $this->_logger->debug('allow_url_fopen is set to false.');
            }

            return false;
        }

        return true;
    }

    /**
     * Determines if this transport can handle the given request.
     *
     * @param ehough_shortstop_api_HttpRequest $request The request to handle.
     *
     * @return bool True if this transport can handle the given request. False otherwise.
     */
    function canHandle(ehough_shortstop_api_HttpRequest $request)
    {
        $scheme = $request->getUrl()->getScheme();

        return preg_match_all('/https?/', $scheme, $matches) === 1;
    }

    private function _buildRawResponse($body)
    {
        $headerArray = $this->_streamResultMeta[self::$_stream_metadata_data_wrapper];

        if (! is_array($headerArray)) {

            throw new Exception('HTTP response is missing header array');
        }

        $this->_statusLine = $headerArray[0];

        $headerString = '';
        for ($x = 1; $x < count($headerArray); $x++) {

            $headerString .= $headerArray[$x] . "\r\n";
        }

        return $headerString . "\r\n" . $body;
    }

    /**
     * @return ehough_epilog_api_ILogger
     */
    protected function getLogger()
    {
        return $this->_logger;
    }
}