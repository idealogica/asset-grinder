<?php
namespace Idealogica\AssetGrinder;

use Psr\Http\Message\UriInterface;

/**
 * Returns Uri instance origin.
 *
 * @param UriInterface $uri
 *
 * @return string
 */
function getUriOrigin(UriInterface $uri): string
{
    return $uri->getScheme() . '://' . $uri->getHost() . ($uri->getPort() ? ':' . $uri->getPort() : '');
}

/**
 * @param string $str
 * @param string $prefix
 *
 * @return string
 */
function removePrefix(string $str, string $prefix): string
{
    $prefixLen = strlen($prefix);
    if (substr($str, 0, $prefixLen) === $prefix) {
        return substr($str, $prefixLen);
    }
    return $str;
}
