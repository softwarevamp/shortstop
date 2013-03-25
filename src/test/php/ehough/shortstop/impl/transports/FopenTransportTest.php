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

class ehough_shortstop_impl_transports_FopenTransportTest extends ehough_shortstop_impl_transports_AbstractHttpTransportTest
{

    protected function _getSutInstance(ehough_shortstop_spi_HttpMessageParser $mp)
    {
        return new ehough_shortstop_impl_transports_FopenTransport($mp);
    }

    protected function _isAvailable()
    {
        return function_exists('fopen') && function_exists('ini_get') && ini_get('allow_url_fopen') == true;
    }
}