<?php
/**
 * Copyright 2013 Eric D. Hough (http://ehough.com)
 *
 * This file is part of shortstop (https://github.com/ehough/shortstop)
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

/**
 * Decodes HTTP messages using chain-of-responsibility.
 */
abstract class ehough_shortstop_impl_AbstractDecoderChain implements ehough_shortstop_spi_HttpResponseDecoder
{
    /**
     * Raw response chain key.
     */
    const CHAIN_KEY_RAW_RESPONSE = 'rawResponse';

    /**
     * Decoded response chain key.
     */
    const CHAIN_KEY_DECODED_RESPONSE = 'decodedResponse';

    /** @var \ehough_chaingang_api_Chain */
    private $_chain;

    /** @var \ehough_epilog_api_ILogger */
    private $_logger;

    public function __construct(ehough_chaingang_api_Chain $chain)
    {
        $this->_chain  = $chain;
        $this->_logger = ehough_epilog_api_LoggerFactory::getLogger('Abstract Decoder Chain');
    }

    /**
     * Decodes an HTTP response.
     *
     * @param ehough_shortstop_api_HttpResponse $response The HTTP response.
     *
     * @return void
     */
    public final function decode(ehough_shortstop_api_HttpResponse $response)
    {
        $context = new ehough_chaingang_impl_StandardContext();
        $context->put(self::CHAIN_KEY_RAW_RESPONSE, $response);

        $status = $this->_chain->execute($context);

        if ($status === false) {

            return;
        }

        $entity  = $response->getEntity();
        $decoded = $context->get(self::CHAIN_KEY_DECODED_RESPONSE);


        $entity->setContent($decoded);
        $entity->setContentLength(strlen($decoded));

        $contentType = $response->getHeaderValue(ehough_shortstop_api_HttpMessage::HTTP_HEADER_CONTENT_TYPE);

        if ($contentType !== null) {

            $entity->setContentType($contentType);
        }

        $response->setEntity($entity);
    }

    /**
     * Determines if this response needs to be decoded.
     *
     * @param ehough_shortstop_api_HttpResponse $response The HTTP response.
     *
     * @return boolean True if this response should be decoded. False otherwise.
     */
    public final function needsToBeDecoded(ehough_shortstop_api_HttpResponse $response)
    {
        $entity = $response->getEntity();

        if ($entity === null) {

            if ($this->_logger->isDebugEnabled()) {

                $this->_logger->debug('Response contains no entity');
            }

            return false;
        }

        $content = $entity->getContent();

        if ($content == '' || $content == null) {

            if ($this->_logger->isDebugEnabled()) {

                $this->_logger->debug('Response entity contains no content');
            }

            return false;
        }

        $expectedHeaderName = $this->getHeaderName();
        $actualHeaderValue  = $response->getHeaderValue($expectedHeaderName);

        if ($actualHeaderValue === null) {

            if ($this->_logger->isDebugEnabled()) {

                $this->_logger->debug(sprintf('Response does not contain %s header. No need to decode.', $expectedHeaderName));
            }

            return false;
        }

        if ($this->_logger->isDebugEnabled()) {

            $this->_logger->debug(sprintf('Response contains %s header. Will attempt decode.', $expectedHeaderName));
        }

        return true;
    }

    protected abstract function getHeaderName();
}