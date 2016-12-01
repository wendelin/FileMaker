<?php
/**
 * FileMaker API for PHP
 *
 * @package airmoi\FileMaker
 *
 * @copyright Copyright (c) 2016 by 1-more-thing (http://1-more-thing.com) All rights reserved.
 * @license BSD
 */

namespace airmoi\FileMaker\Connector;
use airmoi\FileMaker\FileMakerException;

/**
 * Interface ConnectorInterface
 * @package airmoi\FileMaker\Connector
 */
interface ConnectorInterface
{
    /**
     * @param array $params
     *
     * @return string|FileMakerException the cUrl response
     * @throws FileMakerException
     */
    public function execute($params);

    /**
     * @param string $url
     *
     * @return string|FileMakerException Raw field data.
     */
    public function getContainerData($url);
}