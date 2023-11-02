<?php

namespace Rimelek\URLHandler;

use Exception;

/**
 * URL handler class using parse_url and parse_str
 *
 * This class handles whole URLs, including path components and query string.
 * It helps you validate the URL or get specific parts of it.
 *
 * @author Ákos Takács <rimelek@rimelek.hu>
 */
class Url
{

    /**
     * Protocol type
     * 
     * @var string $_protocol
     */
    protected $_protocol = '';

    /**
     * Domain name
     * 
     * @var string $_domain
     */
    protected $_domain = '';

    /**
     * The path in the URL split into an array by "/"
     * 
     * @var array $_folders
     */
    protected $_folders = array();

    /**
     * Port number only if it is not the default (80 for http and 443 fo https)
     * 
     * @var int $_port
     */
    protected $_port = null;

    /**
     * "GET" parameters in the query string
     * 
     * @var array $_get
     */
    protected $_get = array();

    /**
     * The filename at the end of the URL
     * 
     * @var string $_file
     */
    protected $_file = '';

    /**
     * The fragment (or anchor) in the URL after the hashmark character
     * 
     * @var string $_fragment
     */
    protected $_fragment = '';

    /**
     *
     * @param string $url 
     */
    public function __construct($url = null)
    {
        if ($url != null) {
            $this->parse($url);
        }
    }

    /**
     * Url validation
     *
     * @param string $url
     * @return boolean
     */
    public static function isValid($url)
    {
        $scheme = "";
        if (!preg_match('#^(ftp|http|https|gopher|mailto|news|nntp|telnet|wais|file|prospero)\://#i', $url)) {
            $start = substr($url, 0, 1);
            if ($start == ':') {
                // starts with a port, without domain
                return false;
            }
            // Fake scheme and domain for relative URLs (paths).
            $scheme = ($start == '/') ? 'http://domain.hu' : 'http://domain.hu/';
        }
        return (bool) filter_var($scheme . $url, FILTER_VALIDATE_URL);
    }

    /**
     * Revalidate URL after modifications
     */
    protected function reValidate()
    {
        $url = $this->toHeaderString();

        if (!self::isValid(strstr($url, '#', true))) {
            throw new Exception('The given URL (' . $url . ') is not valid!');
        }
    }

    /**
     * Parse URL
     *
     * @param string $url
     * 
     * @throws Exception When the URL is invalid
     */
    public function parse($url)
    {
        /*
         * Splitting the URL and encoding each component
         *
         */
        $url = html_entity_decode($url);
        $fe = explode('#', $url, 2);
        if (!empty($fe[0])) {
            $qe = explode('?', $fe[0], 2);
            $qs = "";
            if (!empty($qe[1])) {
                $get = [];
                parse_str($qe[1], $get);
                $qs = '?' . http_build_query($get);
            }
            $pe = "";
            if (!empty($qe[0])) {
                $protocol = "";
                $pe = explode('/', $qe[0]);
                if ($pe and substr($pe[0], -1) === ':') {
                    $protocol = array_shift($pe) . '/';
                }

                foreach ($pe as $k => &$v) {
                    if ($v and $k < 2) {
                        continue;
                    }
                    $v = urlencode($v);
                }
                $pe = $protocol . implode('/', $pe);
            }
            $url = $pe . $qs;
        } else {
            $url = "";
        }

        if (!self::isValid($url)) {
            throw new Exception('Invalid URL: ' . $url);
        } else {
            if (isset($fe[1])) {
                $url = $url . '#' . $fe[1];
            }
            $pieces = array_merge(array(
                'scheme' => '', 'host' => '', 'port' => '', 'path' => '', 'query' => '', 'fragment' => ''
                ), parse_url($url));
            // Protocol, domain, port can be determined easily
            $this->_protocol = $pieces['scheme'];
            $this->_domain = $pieces['host'];
            $this->_port = $pieces['port'];
            // Directories and filename
            $pos = strrpos($pieces['path'], '/');
            $info['basename'] = substr($pieces['path'], $pos + 1);
            $info['dirname'] = substr($pieces['path'], 0, $pos + 1);
            $p = pathinfo($info['basename']);
            if (isset($p['extension'])) {
                $info['extension'] = $p['extension'];
            }

            $path = $info['dirname'];
            if (!empty($info['extension'])) {
                $this->_file = $info['basename'];
            } else {
                $this->_file = '';
                $path = $path . $info['basename'];
            }

            $this->_folders = explode('/', $path);
            $this->_fragment = $pieces['fragment'];

            if (empty($pieces['query']) || $pieces['query'] == '?') {
                $this->_get = array();
            } else {
                parse_str($pieces['query'], $this->_get);
            }
        }
    }

    /**
     * Convert the URL instance to string.
     * 
     * The query string separator can be passed as a parameter.
     *
     * Use &amp;amp; in an HTML code and & in a HTTP header.
     *
     * @param string $sep Parameter separator character or characters
     * @return string
     */
    protected function toString($sep = '&amp;')
    {
        // Encoding directories
        $folders = $this->_folders;
        array_map('urlencode', $folders);

        // Encoding query string
        $qs = $this->_get ? '?' . http_build_query($this->_get, null, $sep) : '';
        $fragment = $this->_fragment ? '#' . $this->_fragment : '';
        $protocol = $this->_protocol ? $this->_protocol . '://' : '';

        $domain = '';
        if ($this->_domain) {
            $domain = $this->_domain . ($this->_port ? ':' . $this->_port : '');
        }

        $filepath = implode('/', $folders) . urlencode($this->_file);

        if ($filepath and $domain and $filepath[0] != '/') {
            $filepath = '/' . $filepath;
        }

        return
            $protocol .
            $domain .
            $filepath .
            $qs . $fragment;
    }

    /**
     * Convert the URL instance to a HTTP header compatible string
     * (Separator character is &)
     */
    public function toHeaderString()
    {
        return $this->toString('&');
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Get query string parameter
     * 
     * @param string $name Parameter name
     * @return string Parameter value
     */
    public function getParam($name)
    {
        return $this->_get[$name];
    }

    /**
     * Get all query string parameters as an array
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_get;
    }

    /**
     * Set query string parameter
     *
     * @param string $name Parameter name
     * @param string $value Parameter value
     */
    public function setParam($name, $value)
    {
        $this->_get[$name] = $value;
    }

    /**
     * Check if the parameter exists and also set
     * 
     * @param string $name Parameter name
     * @return bool
     */
    public function issetParam($name)
    {
        return isset($this->_get[$name]);
    }

    /**
     * Paraméter törlése
     * @param string $name Parameter name
     */
    public function unsetParam($name)
    {
        unset($this->_get[$name]);
    }

    /**
     * Get the path from the URL
     * 
     * @see self::setPath()
     * @return string
     */
    public function getPath()
    {
        return implode('/', $this->_folders);
    }

    /**
     * Set the path in the URL
     *
     * In case of the path is relative, the protocol, domain and port will be removed.
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $folders = explode('/', $path);
        $this->_folders = $folders;
        $this->reValidate();
    }

    /**
     * Get domain name
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->_domain;
    }

    /**
     * Set the domain
     * 
     * @param string $domain
     * @throws Exception
     */
    public function setDomain($domain = '')
    {
        if (!preg_match('#^[a-z0-9_.-]*$#i', $domain)) {
            throw new Exception('Invalid domain name');
        }
        if (!empty($domain) and empty($this->_protocol)) {
            $this->_protocol = 'http';
        } else if (empty($domain)) {
            $this->_protocol = '';
        }
        $this->_domain = $domain;
        $this->reValidate();
    }

    /**
     * Get port number
     *
     * @return string
     */
    public function getPort()
    {
        return $this->_port;
    }

    /**
     * Port beállítása
     *
     * @param string $port
     */
    public function setPort($port = null)
    {
        if (!preg_match('#^[0-9]*$#', (string) $port)) {
            throw new Exception('Port number is invalid');
        }
        $this->_port = $port;
        $this->reValidate();
    }

    /**
     * Get protocol
     * 
     * @return string
     */
    public function getProtocol()
    {
        return $this->_protocol;
    }

    /**
     * Set potocol
     *
     * @param string $protocol
     */
    public function setProtocol($protocol)
    {
        if (!preg_match('#^[a-z]+$#i', $protocol)) {
            throw new Exception('Invalid protocol');
        }
        $this->_protocol = $protocol;
        $this->reValidate();
    }

    /**
     * Get file name
     *
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * Set file name
     *
     * @param string $file
     */
    public function setFile($file)
    {
        $this->_file = urlencode($file);
    }

    /**
     * Set fragment (anchor)
     *
     * @param string $fragment
     */
    public function setFragment($fragment)
    {
        $this->_fragment = $fragment;
    }

    /**
     * Get fragment (anchor)
     *
     * @return string
     */
    public function getFragment()
    {
        return $this->_fragment;
    }

}
