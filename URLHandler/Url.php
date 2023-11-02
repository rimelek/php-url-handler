<?php

namespace Rimelek\URLHandler;

/**
 * URL kezelő osztály
 *
 * URL-ek feldolgozása, és kezelése mappák, paraméterek és fájl szerint.
 *
 * @author Takács Ákos <rimelek@rimelek.hu>
 */
class Url
{

    /**
     * Protokol típus
     * 
     * @var string $_protocol
     */
    protected $_protocol = '';

    /**
     * Domain név
     * 
     * @var string $_domain
     */
    protected $_domain = '';

    /**
     * Mappák (tömbként, sorszámozva)
     * 
     * @var array $_folders
     */
    protected $_folders = array();

    /**
     * Port (csak ha eltér az alapértelmezettől)
     * 
     * @var int $_port
     */
    protected $_port = null;

    /**
     * GET típusú paraméterezés (tömbként)
     * 
     * @var array $_get
     */
    protected $_get = array();

    /**
     * Lekért file neve
     * 
     * @var string $_file
     */
    protected $_file = '';

    /**
     * kettőskereszt utáni rész
     * 
     * @var string $_fragment
     */
    protected $_fragment = '';

    /**
     * Url osztály készítése.
     *
     * @param $url Esetlegesen, a kezelendő url (vagy null)
     */
    public function __construct($url = null)
    {
        if ($url != null) {
            $this->parse($url);
        }
    }

    /**
     * Url érvényesség ellenőrzése
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
                //porttal kezdődik. nincs domain
                return false;
            }
            //kamu séma+domain a validálónak relatív urlhez.
            $scheme = ($start == '/') ? 'http://domain.hu' : 'http://domain.hu/';
        }
        return (bool) filter_var($scheme . $url, FILTER_VALIDATE_URL);
    }

    /**
     * Url újra validálása módosítások után
     */
    protected function reValidate()
    {
        $url = $this->toHeaderString();

        if (!self::isValid(strstr($url, '#', true))) {
            throw new \Exception('A kapott url (' . $url . ') nem érvényes!');
        }
    }

    /**
     * Url felvitele kezelésre
     *
     * @param string $url A feldolgozandó url
     * 
     * @exception Exception 
     *          Akkor dobódik, ha az url érvénytelennek tűnik.
     * 
     *  
     */
    public function parse($url)
    {
        /*
         * Url feldarabolása, és részenként encodeolása. Egyben a / jeleket és
         * egyéb jeleket is encodeolná.
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
            throw new \Exception('A kapott url (' . $url . ') nem érvényes!');
        } else {
            if (isset($fe[1])) {
                $url = $url . '#' . $fe[1];
            }
            $pieces = array_merge(array(
                'scheme' => '', 'host' => '', 'port' => '', 'path' => '', 'query' => '', 'fragment' => ''
                ), parse_url($url));
            //Protokol, domain, port simán levehető
            $this->_protocol = $pieces['scheme'];
            $this->_domain = $pieces['host'];
            $this->_port = $pieces['port'];
            //Mappák és fájl szétszedése                        
            $pos = strrpos($pieces['path'], '/');
            $info['basename'] = substr($pieces['path'], $pos + 1);
            $info['dirname'] = substr($pieces['path'], 0, $pos + 1);
            $p = pathinfo($info['basename']);
            if (isset($p['extension'])) {
                $info['extension'] = $p['extension'];
            }
            //Ha nincs útvonal, üres a dirname. Ha csak 1 mappa vagy fájl van, akkor pont
            $path = $info['dirname'];
            if (!empty($info['extension'])) {
                //Ha van extension, akkor van fájl
                $this->_file = $info['basename'];
            } else {
                //Ha nincs extension, nincs fájl
                $this->_file = '';
                $path = $path . $info['basename'];
            }

            $this->_folders = explode('/', $path);
            $this->_fragment = $pieces['fragment'];
            //_GET string feldolgozása
            if (empty($pieces['query']) || $pieces['query'] == '?') {
                $this->_get = array(); //Nincs is _get
            } else {
                parse_str($pieces['query'], $this->_get);
            }
        }
    }

    /**
     * Stringgé alakítás opcionálisan megadható paraméter elválasztó karakterrel
     *
     * linkbe &amp;amp; jel, location-be &
     *
     * @param string $sep Paraméterek elválasztó karakterei
     * @return string
     */
    protected function toString($sep = '&amp;')
    {
        //Mappák urlkódolása
        $folders = $this->_folders;
        array_map('urlencode', $folders);
        //Query String készítése
        $qs = $this->_get ? '?' . http_build_query($this->_get, null, $sep) : '';
        $fragment = $this->_fragment ? '#' . $this->_fragment : '';
        $protocol = $this->_protocol ? $this->_protocol . '://' : '';

        $domain = '';
        if ($this->_domain) {
            $domain = $this->_domain .
                ($this->_port ? ':' . $this->_port : '');
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
     * Stringgé alakítás header átirányításhoz
     * (& jelek a paraméterek elválasztó karakterei)
     */
    public function toHeaderString()
    {
        return $this->toString('&');
    }

    /**
     * Url készítése az adatokból
     * @return string Az url
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Paraméter lekérdezése
     * @param string $name Paraméter neve
     * @return string Paraméter értéke
     */
    public function getParam($name)
    {
        return $this->_get[$name];
    }

    /**
     * GET paraméterek
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_get;
    }

    /**
     * Paraméter beállítása
     *
     * @param string $name Paraméter neve
     * @param string $value Paraméter értéke
     */
    public function setParam($name, $value)
    {
        $this->_get[$name] = $value;
    }

    /**
     * Paraméter létezik-e?
     * @param string $name Paraméter neve
     * @return bool Létezik-e?
     */
    public function issetParam($name)
    {
        return isset($this->_get[$name]);
    }

    /**
     * Paraméter törlése
     * @param $name Paraméter neve
     */
    public function unsetParam($name)
    {
        unset($this->_get[$name]);
    }

    /**
     * Útvonal lekérdezése
     * @see self::setPath()
     * @return string Útvonal
     */
    public function getPath()
    {
        return implode('/', $this->_folders);
    }

    /**
     * Útvonal váltás
     *
     * Ha relatív az új útvonal, akkor a protokolt, domaint és portot törli.
     *
     * @param string $path Új útvonal
     */
    public function setPath($path)
    {
        $folders = explode('/', $path);
        $this->_folders = $folders;
        $this->reValidate();
    }

    /**
     * Domain név lekérdezése
     * @return string a domain név
     */
    public function getDomain()
    {
        return $this->_domain;
    }

    /**
     * Domain név beállítása
     * @param string $domain Domain név
     */
    public function setDomain($domain = '')
    {
        if (!preg_match('#^[a-z0-9_.-]*$#i', $domain)) {
            throw new \Exception('A váltáshoz megadott domain cím hibás');
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
     * Port lekérdezése
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
    public function setPort($port = '')
    {
        if (!preg_match('#^[0-9]*$#', (string) $port)) {
            throw new \Exception('Az új port hibás');
        }
        $this->_port = $port;
        $this->reValidate();
    }

    /**
     * Protokol lekérdezése
     * @return string Protokol neve
     */
    public function getProtocol()
    {
        return $this->_protocol;
    }

    /**
     * Protokol beállítása
     * @param string $protocol Protokol névbű
     */
    public function setProtocol($protocol)
    {
        if (!preg_match('#^[a-z]+$#i', $protocol)) {
            throw new \Exception('A megadott protokol hibás formátumú');
        }
        $this->_protocol = $protocol;
        $this->reValidate();
    }

    /**
     * Fájl lekérdezése
     *
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * Fájl beállítása
     *
     * @param string $file
     */
    public function setFile($file)
    {
        $this->_file = urlencode($file);
    }

    /**
     * Fragment beállítása
     *
     * @param string $fragment
     */
    public function setFragment($fragment)
    {
        $this->_fragment = $fragment;
    }

    /**
     * Fragment lekérdezése
     *
     * @return string
     */
    public function getFragment()
    {
        return $this->_fragment;
    }

}
