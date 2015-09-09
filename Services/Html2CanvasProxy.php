<?php

namespace HTML2Canvas\ProxyBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class Html2CanvasProxy
{
    /**
     * Configure alternative function log, eg. console.log, alert, custom_function
     *
     * @const JS_LOG
     */
    const JS_LOG = 'console.log';

    /**
     * Limit access-control and cache, define 0/false/null/-1 to not use "http header cache"
     *
     * @const CCACHE
     */
    const CCACHE = 60 * 5 * 1000;

    /**
     * Timeout from load Socket
     *
     * @const SOCKET_TIMEOUT
     */
    const SOCKET_TIMEOUT = 30;

    /**
     * Configure loop limit for redirect (location header)
     *
     * @const REDIRECT_MAX_LOOP
     */
    const REDIRECT_MAX_LOOP = 10;

    /**
     * @const TMP_FILE_PREFIX
     */
    const TMP_FILE_PREFIX = 'h2c_';

    /**
     * Relative folder where the images are saved
     *
     * @var string|null
     */
    protected $imagesPath = null;

    /**
     * Enable use of "data URI scheme"
     *
     * @var bool
     */
    protected $crossDomain = false;

    /**
     * @var string
     */
    protected $eol;

    /**
     * @var string
     */
    protected $wol;

    /**
     * @var string
     */
    protected $gmDateCache;

    /**
     * Reduces 5 seconds to ensure the execution of the DEBUG
     *
     * @var int
     */
    protected $maxExecTime;

    /**
     * @var int
     */
    protected $initExec;

    /**
     * @var int
     */
    protected $httpPort = 0;

    /**
     * @var string
     */
    protected $paramCallback = self::JS_LOG;

    /**
     * @var string|null
     */
    protected $tmp;

    /**
     * @var array
     */
    protected $response = [];

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var Request
     */
    protected $request = null;

    /**
     * @param RequestStack $requestStack
     * @param string       $imagesPath
     * @param string       $crossDomain
     */
    public function __construct(RequestStack $requestStack, $imagesPath, $crossDomain)
    {
        $this->imagesPath   = $imagesPath;
        $this->crossDomain  = $crossDomain;
        $this->eol          = chr(10);
        $this->wol          = chr(13);
        $this->gmDateCache  = gmdate('D, d M Y H:i:s');
        $maxExec            = (int)ini_get('max_execution_time');
        $this->maxExecTime  = $maxExec < 1 ? 0 : ($maxExec - 5);
        $this->requestStack = $requestStack;
    }

    public function execute()
    {
        $this->initRequest();

        if (isset($_SERVER['HTTP_HOST']) === false || strlen($_SERVER['HTTP_HOST']) === 0) {
            $this->response = ['error' => 'The client did not send the Host header'];
        } elseif (isset($_SERVER['SERVER_PORT']) === false) {
            $this->response = ['error' => 'The Server-proxy did not send the PORT (configure PHP)'];
        } elseif ($this->maxExecTime < 10) {
            $this->response = ['error' => 'Execution time is less 15 seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more'];
        } elseif ($this->maxExecTime <= self::SOCKET_TIMEOUT) {
            $this->response = ['error' => 'The execution time is not configured enough to self::SOCKET_TIMEOUT in SOCKET, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended that the "max_execution_time =;" be a minimum of 5 seconds longer or reduce the self::SOCKET_TIMEOUT in "define(\'self::SOCKET_TIMEOUT\', ' . self::SOCKET_TIMEOUT . ');"'];
        } elseif (isset($_GET['url']) === false || strlen($_GET['url']) === 0) {
            $this->response = ['error' => 'No such parameter "url"'];
        } elseif ($this->isHttpUrl($_GET['url']) === false) {
            $this->response = ['error' => 'Only http scheme and https scheme are allowed'];
        } elseif (preg_match('#[^A-Za-z0-9_[.]\\[\\]]#', $this->paramCallback) !== 0) {
            $this->response      = ['error' => 'Parameter "callback" contains invalid characters'];
            $this->paramCallback = self::JS_LOG;
        } elseif ($this->createFolder() === false) {
            $err            = $this->get_error();
            $this->response = ['error' => 'Can not create directory' . (
                $err !== null && isset($err['message']) && strlen($err['message']) > 0 ? (': ' . $err['message']) : ''
                )];
            $err            = null;
        } else {
            $this->httpPort = (int)$_SERVER['SERVER_PORT'];

            $this->tmp = $this->createTmpFile($_GET['url'], false);
            if ($this->tmp === false) {
                $err            = $this->get_error();
                $this->response = ['error' => 'Can not create file' . (
                    $err !== null && isset($err['message']) && strlen($err['message']) > 0 ? (': ' . $err['message']) : ''
                    )];
                $err            = null;
            } else {
                $this->response = $this->downloadSource($_GET['url'], $this->tmp['source'], 0);
                fclose($this->tmp['source']);
            }
        }

        if (is_array($this->response) && isset($this->response['mime']) && strlen($this->response['mime']) > 0) {
            clearstatcache();
            if (false === file_exists($this->tmp['location'])) {
                $this->response = ['error' => 'Request was downloaded, but file can not be found, try again'];
            } elseif (filesize($this->tmp['location']) < 1) {
                $this->response = ['error' => 'Request was downloaded, but there was some problem and now the file is empty, try again'];
            } else {
                $extension = str_replace(['image/', 'text/', 'application/'], '', $this->response['mime']);
                $extension = str_replace(['windows-bmp', 'ms-bmp'], 'bmp', $extension);
                $extension = str_replace(['svg+xml', 'svg-xml'], 'svg', $extension);
                $extension = str_replace('xhtml+xml', 'xhtml', $extension);
                $extension = str_replace('jpeg', 'jpg', $extension);

                $locationFile = preg_replace('#[.][0-9_]+$#', '.' . $extension, $this->tmp['location']);
                if (file_exists($locationFile)) {
                    unlink($locationFile);
                }

                if (rename($this->tmp['location'], $locationFile)) {
                    //set cache
                    $this->setHeaders(false);

                    $this->remove_old_files();

                    if (true === $this->crossDomain) {
                        $mime = $this->JsonEncodeString($this->response['mime'], true);
                        $mime = $this->response['mime'];
                        if ($this->response['encode'] !== null) {
                            $mime .= ';charset=' . $this->JsonEncodeString($this->response['encode'], true);
                        }

                        $this->tmp = $this->response = null;

                        if (strpos($mime, 'image/svg') !== 0 && strpos($mime, 'image/') === 0) {
                            echo $this->paramCallback, '("data:', $mime, ';base64,',
                            base64_encode(
                                file_get_contents($locationFile)
                            ),
                            '");';
                        } else {
                            echo $this->paramCallback, '("data:', $mime, ',',
                            $this->asciiToInline(
                                file_get_contents($locationFile)
                            ),
                            '");';
                        }
                    } else {
                        $this->tmp = $this->response = null;

                        $dir_name = dirname($_SERVER['SCRIPT_NAME']);
                        if ($dir_name === '\/' || $dir_name === '\\') {
                            $dir_name = '';
                        }

                        echo $this->paramCallback, '(',
                        $this->JsonEncodeString(
                            ($this->httpPort === 443 ? 'https://' : 'http://') .
                            preg_replace('#:[0-9]+$#', '', $_SERVER['HTTP_HOST']) .
                            ($this->httpPort === 80 || $this->httpPort === 443 ? '' : (
                                ':' . $_SERVER['SERVER_PORT']
                            )) .
                            $dir_name . '/' .
                            $locationFile
                        ),
                        ');';
                    }
                    exit;
                } else {
                    $this->response = ['error' => 'Failed to rename the temporary file'];
                }
            }
        }

        if (is_array($this->tmp) && isset($this->tmp['location']) && file_exists($this->tmp['location'])) {
            //remove temporary file if an error occurred
            unlink($this->tmp['location']);
        }


        //errors
        $this->setHeaders(true);//no-cache

        $this->remove_old_files();

        echo $this->paramCallback, '(',
        $this->JsonEncodeString(
            'error: html2canvas-proxy-php: ' . $this->response['error']
        ),
        ');';

    }

    /**
     * Initialize request properties.
     */
    protected function initRequest()
    {
        $this->request = $this->requestStack->getCurrentRequest();
        $this->request->headers->set('Content-Type', 'application/javascript');

        $initExec = time();
        if ($this->request->server->has('REQUEST_TIME')) {
            $requestTime = $this->request->server->get('REQUEST_TIME');
            if (strlen($requestTime) > 0) {
                $initExec = (int) $requestTime;
            }
        }

        $this->initExec = $initExec;

        if ($this->request->query->has('callback')) {
            $paramCallback = $this->request->get('callback');
            if (strlen($paramCallback) > 0) {
                $this->paramCallback = $paramCallback;
            }
        }
    }

    /**
     * For show ASCII documents with "data uri scheme"
     *
     * @param string $str to encode
     *
     * @return string      always return string
     */
    protected function asciiToInline($str)
    {
        $trans             = [];
        $trans[$this->eol] = '%0A';
        $trans[$this->wol] = '%0D';
        $trans[' ']        = '%20';
        $trans['"']        = '%22';
        $trans['#']        = '%23';
        $trans['&']        = '%26';
        $trans['\/']       = '%2F';
        $trans['\\']       = '%5C';
        $trans[':']        = '%3A';
        $trans['?']        = '%3F';
        $trans[chr(0)]     = '%00';
        $trans[chr(8)]     = '';
        $trans[chr(9)]     = '%09';

        return strtr($str, $trans);
    }

    /**
     * Detect SSL stream transport
     *
     * @return boolean|string        If returns string has an problem, returns true if ok
     */
    protected function supportSSL()
    {
        if (defined('SOCKET_SSL_STREAM')) {
            return true;
        }

        /* PHP 5 */
        if (in_array('ssl', stream_get_transports())) {
            defined('SOCKET_SSL_STREAM', '1');

            return true;
        }

        return 'Error';
    }

    /**
     * set headers in document
     *
     * @return void return always void
     */
    protected function remove_old_files()
    {
        $p = $this->imagesPath . '/';

        if (
            ($this->maxExecTime === 0 || (time() - $this->initExec) < $this->maxExecTime) && //prevents this function locks the process that was completed
            (file_exists($p) || is_dir($p))
        ) {
            $h = opendir($p);
            if (false !== $h) {
                while (false !== ($f = readdir($h))) {
                    if (
                        is_file($p . $f) && is_dir($p . $f) === false &&
                        strpos($f, self::TMP_FILE_PREFIX) !== false &&
                        ($this->initExec - filectime($p . $f)) > (self::CCACHE * 2)
                    ) {
                        unlink($p . $f);
                    }
                }
            }
        }
    }

    /**
     * this function does not exist by default in php4.3, get detailed error in php5
     *
     * @return array   if has errors
     */
    protected function get_error()
    {
        if (function_exists('error_get_last') === false) {
            return error_get_last();
        }

        return null;
    }

    /**
     * enconde string in "json" (only strings), json_encode (native in php) don't support for php4
     *
     * @param string $s to encode
     * @param bool   $onlyEncode
     *
     * @return string always return string
     */
    protected function JsonEncodeString($s, $onlyEncode = false)
    {
        $vetor     = [];
        $vetor[0]  = '\\0';
        $vetor[8]  = '\\b';
        $vetor[9]  = '\\t';
        $vetor[10] = '\\n';
        $vetor[12] = '\\f';
        $vetor[13] = '\\r';
        $vetor[34] = '\\"';
        $vetor[47] = '\\/';
        $vetor[92] = '\\\\';

        $this->tmp = '';
        $enc       = '';
        $j         = strlen($s);

        for ($i = 0; $i < $j; ++$i) {
            $this->tmp = substr($s, $i, 1);
            $c         = ord($this->tmp);
            if ($c > 126) {
                $d         = '000' . dechex($c);
                $this->tmp = '\\u' . substr($d, strlen($d) - 4);
            } else {
                if (isset($vetor[$c])) {
                    $this->tmp = $vetor[$c];
                } elseif (($c > 31) === false) {
                    $d         = '000' . dechex($c);
                    $this->tmp = '\\u' . substr($d, strlen($d) - 4);
                }
            }

            $enc .= $this->tmp;
        }

        if ($onlyEncode === true) {
            return $enc;
        } else {
            return '"' . $enc . '"';
        }
    }

    /**
     * Set headers in document
     *
     * @param boolean $nocache If false set cache (if self::CCACHE > 0), If true set no-cache in document
     */
    protected function setHeaders($nocache)
    {
        if (false === $nocache && is_int(self::CCACHE) && self::CCACHE > 0) {
            //save to browser cache
            $this->request->headers->set('Last-Modified', $this->gmDateCache . ' GMT');
            $this->request->headers->addCacheControlDirective('max-age', (self::CCACHE - 1));
            $this->request->headers->set('Expires', gmdate('D, d M Y H:i:s', $this->initExec + self::CCACHE - 1));
            $this->request->headers->set('Access-Control-Max-Age', self::CCACHE);
        } else {
            //no-cache
            $this->request->headers->removeCacheControlDirective('max-age');
            $this->request->headers->set('Expires', $this->gmDateCache . ' GMT');
        }

        //set access-control
        $this->request->headers->set('Access-Control-Allow-Origin', '*');
        $this->request->headers->set('Access-Control-Request-Method', '*');
        $this->request->headers->set('Access-Control-Allow-Methods', 'OPTIONS, GET');
        $this->request->headers->set('Access-Control-Allow-Headers', '*');
    }

    /**
     * Converte relative-url to absolute-url
     *
     * @param string $u set base url
     * @param string $m set relative url
     *
     * @return string         return always string, if have an error, return blank string (scheme invalid)
     */
    protected function relativeToAbsolute($u, $m)
    {
        if (strpos($m, '//') === 0) {//http link //site.com/test
            return 'http:' . $m;
        }

        if (preg_match('#^[a-zA-Z0-9]+[:]#', $m) !== 0) {
            $pu = parse_url($m);

            if (preg_match('/^(http|https)$/i', $pu['scheme']) === 0) {
                return '';
            }

            $m = '';
            if (isset($pu['path'])) {
                $m .= $pu['path'];
            }

            if (isset($pu['query'])) {
                $m .= '?' . $pu['query'];
            }

            if (isset($pu['fragment'])) {
                $m .= '#' . $pu['fragment'];
            }

            return $this->relativeToAbsolute($pu['scheme'] . '://' . $pu['host'], $m);
        }

        if (preg_match('/^[?#]/', $m) !== 0) {
            return $u . $m;
        }

        $pu         = parse_url($u);
        $pu['path'] = isset($pu['path']) ? preg_replace('#/[^/]*$#', '', $pu['path']) : '';

        $pm         = parse_url('http://1/' . $m);
        $pm['path'] = isset($pm['path']) ? $pm['path'] : '';

        $isPath = $pm['path'] !== '' && strpos(strrev($pm['path']), '/') === 0 ? true : false;

        if (strpos($m, '/') === 0) {
            $pu['path'] = '';
        }

        $b = $pu['path'] . '/' . $pm['path'];
        $b = str_replace('\\', '/', $b);//Confuso ???

        $ab = explode('/', $b);
        $j  = count($ab);

        $ab = array_filter($ab, 'strlen');
        $nw = [];

        for ($i = 0; $i < $j; ++$i) {
            if (isset($ab[$i]) === false || $ab[$i] === '.') {
                continue;
            }
            if ($ab[$i] === '..') {
                array_pop($nw);
            } else {
                $nw[] = $ab[$i];
            }
        }

        $m = $pu['scheme'] . '://' . $pu['host'] . '/' . implode('/', $nw) . ($isPath === true ? '/' : '');

        if (isset($pm['query'])) {
            $m .= '?' . $pm['query'];
        }

        if (isset($pm['fragment'])) {
            $m .= '#' . $pm['fragment'];
        }

        $nw = null;
        $ab = null;
        $pm = null;
        $pu = null;

        return $m;
    }

    /**
     * validate url
     *
     * @param string $u set base url
     *
     * @return boolean   return always boolean
     */
    protected function isHttpUrl($u)
    {
        return preg_match('#^http(|s)[:][/][/][a-z0-9]#i', $u) !== 0;
    }

    /**
     * create folder for images download
     *
     * @return boolean      return always boolean
     */
    protected function createFolder()
    {
        if (file_exists($this->imagesPath) === false || is_dir($this->imagesPath) === false) {
            return mkdir($this->imagesPath, 0755);
        }

        return true;
    }

    /**
     * create temp file which will receive the download
     *
     * @param string  $basename set url
     * @param boolean $isEncode If true uses the "first" temporary name
     *
     * @return boolean|array        If you can not create file return false, If create file return array
     */
    protected function createTmpFile($basename, $isEncode)
    {
        $folder = preg_replace('#[/]$#', '', $this->imagesPath) . '/';
        if ($isEncode === false) {
            $basename = self::TMP_FILE_PREFIX . sha1($basename);
        }

        //$basename .= $basename;
        $tmpMine = '.' . mt_rand(0, 1000) . '_';
        if ($isEncode === true) {
            $tmpMine .= isset($_SERVER['REQUEST_TIME']) && strlen($_SERVER['REQUEST_TIME']) > 0 ? $_SERVER['REQUEST_TIME'] : (string)time();
        } else {
            $tmpMine .= (string)$this->initExec;
        }

        if (file_exists($folder . $basename . $tmpMine)) {
            return $this->createTmpFile($basename, true);
        }

        $source = fopen($folder . $basename . $tmpMine, 'w');
        if ($source !== false) {
            return [
                'location' => $folder . $basename . $tmpMine,
                'source'   => $source
            ];
        }

        return false;
    }

    /**
     * download http request recursive (If found HTTP 3xx)
     *
     * @param string   $url      to download
     * @param resource $toSource to download
     *
     * @return array                    retuns array
     */
    protected function downloadSource($url, $toSource, $caller)
    {
        $errno  = 0;
        $errstr = '';

        ++$caller;

        if ($caller > self::REDIRECT_MAX_LOOP) {
            return ['error' => 'Limit of ' . self::REDIRECT_MAX_LOOP . ' redirects was exceeded, maybe there is a problem: ' . $url];
        }

        $uri    = parse_url($url);
        $secure = strcasecmp($uri['scheme'], 'https') === 0;

        if ($secure) {
            $this->response = $this->supportSSL();
            if ($this->response !== true) {
                return ['error' => $this->response];
            }
        }

        $port = isset($uri['port']) && strlen($uri['port']) > 0 ? (int)$uri['port'] : ($secure === true ? 443 : 80);
        $host = ($secure ? 'ssl://' : '') . $uri['host'];

        $fp = fsockopen($host, $port, $errno, $errstr, self::SOCKET_TIMEOUT);
        if ($fp === false) {
            return ['error' => 'SOCKET: ' . $errstr . '(' . ((string)$errno) . ')'];
        } else {
            fwrite(
                $fp, 'GET ' . (
                isset($uri['path']) && strlen($uri['path']) > 0 ? $uri['path'] : '/'
                ) . (
                isset($uri['query']) && strlen($uri['query']) > 0 ? ('?' . $uri['query']) : ''
                ) . ' HTTP/1.0' . $this->wol . $this->eol
            );

            if (isset($uri['user'])) {
                $auth = base64_encode($uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
                fwrite($fp, 'Authorization: Basic ' . $auth . $this->wol . $this->eol);
            }

            if (isset($_SERVER['HTTP_ACCEPT']) && strlen($_SERVER['HTTP_ACCEPT']) > 0) {
                fwrite($fp, 'Accept: ' . $_SERVER['HTTP_ACCEPT'] . $this->wol . $this->eol);
            }

            if (isset($_SERVER['HTTP_USER_AGENT']) && strlen($_SERVER['HTTP_USER_AGENT']) > 0) {
                fwrite($fp, 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . $this->wol . $this->eol);
            }

            if (isset($_SERVER['HTTP_REFERER']) && strlen($_SERVER['HTTP_REFERER']) > 0) {
                fwrite($fp, 'Referer: ' . $_SERVER['HTTP_REFERER'] . $this->wol . $this->eol);
            }

            fwrite($fp, 'Host: ' . $uri['host'] . $this->wol . $this->eol);
            fwrite($fp, 'Connection: close' . $this->wol . $this->eol . $this->wol . $this->eol);

            $isRedirect = true;
            $isBody     = false;
            $isHttp     = false;
            $encode     = null;
            $mime       = null;
            $data       = '';

            while (false === feof($fp)) {
                $data = fgets($fp);

                if ($data === false) {
                    continue;
                }

                if ($isHttp === false) {
                    if (preg_match('#^HTTP[/]1[.]#i', $data) === 0) {
                        fclose($fp);//Close connection
                        $data = '';

                        return ['error' => 'This request did not return a HTTP response valid'];
                    }

                    $tmp = preg_replace('#(HTTP/1[.]\\d |[^0-9])#i', '',
                        preg_replace('#^(HTTP/1[.]\\d \\d{3}) [\\w\\W]+$#i', '$1', $data)
                    );

                    if ($tmp === '304') {
                        fclose($fp);//Close connection
                        $data = '';

                        return ['error' => 'Request returned HTTP_304, this status code is incorrect because the html2canvas not send Etag'];
                    } else {
                        $isRedirect = preg_match('#^(301|302|303|307|308)$#', $tmp) !== 0;

                        if ($isRedirect === false && $tmp !== '200') {
                            fclose($fp);
                            $data = '';

                            return ['error' => 'Request returned HTTP_' . $tmp];
                        }

                        $isHttp = true;

                        continue;
                    }
                }

                if ($isBody === false) {
                    if (preg_match('#^location[:]#i', $data) !== 0) {//200 force 302
                        fclose($fp);//Close connection

                        $data = trim(preg_replace('#^location[:]#i', '', $data));

                        if ($data === '') {
                            return ['error' => '"Location:" header is blank'];
                        }

                        $nextUri = $data;
                        $data    = $this->relativeToAbsolute($url, $data);

                        if ($data === '') {
                            return ['error' => 'Invalid scheme in url (' . $nextUri . ')'];
                        }

                        if ($this->isHttpUrl($data) === false) {
                            return ['error' => '"Location:" header redirected for a non-http url (' . $data . ')'];
                        }

                        return $this->downloadSource($data, $toSource, $caller);
                    } elseif (preg_match('#^content[-]length[:]( 0|0)$#i', $data) !== 0) {
                        fclose($fp);
                        $data = '';

                        return ['error' => 'source is blank (Content-length: 0)'];
                    } elseif (preg_match('#^content[-]type[:]#i', $data) !== 0) {
                        $data = strtolower($data);

                        if (preg_match('#[;](\s|)+charset[=]#', $data) !== 0) {
                            $tmp2   = preg_split('#[;](\s|)+charset[=]#', $data);
                            $encode = isset($tmp2[1]) ? trim($tmp2[1]) : null;
                        }

                        $mime = trim(
                            preg_replace('/[;]([\\s\\S]|)+$/', '',
                                str_replace('content-type:', '',
                                    str_replace('/x-', '/', $data)
                                )
                            )
                        );

                        if (in_array($mime, [
                                'image/bmp', 'image/windows-bmp', 'image/ms-bmp',
                                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                                'text/html', 'application/xhtml', 'application/xhtml+xml',
                                'image/svg+xml', //SVG image
                                'image/svg-xml' //Old servers (bug)
                            ]) === false
                        ) {
                            fclose($fp);
                            $data = '';

                            return ['error' => $mime . ' mimetype is invalid'];
                        }
                    } elseif ($isBody === false && trim($data) === '') {
                        $isBody = true;
                        continue;
                    }
                } elseif ($isRedirect === true) {
                    fclose($fp);
                    $data = '';

                    return ['error' => 'The response should be a redirect "' . $url . '", but did not inform which header "Localtion:"'];
                } elseif ($mime === null) {
                    fclose($fp);
                    $data = '';

                    return ['error' => 'Not set the mimetype from "' . $url . '"'];
                } else {
                    fwrite($toSource, $data);
                    continue;
                }
            }

            fclose($fp);

            $data = '';

            if ($isBody === false) {
                return ['error' => 'Content body is empty'];
            } elseif ($mime === null) {
                return ['error' => 'Not set the mimetype from "' . $url . '"'];
            }

            return [
                'mime'   => $mime,
                'encode' => $encode
            ];
        }
    }
}
