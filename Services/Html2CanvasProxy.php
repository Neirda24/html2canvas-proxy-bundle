<?php

namespace HTML2Canvas\ProxyBundle\Services;

use HTML2Canvas\ProxyBundle\Exception\ErrorStatic as Error;
use HTML2Canvas\ProxyBundle\Exception\HTML2CanvasProxyException;
use Symfony\Component\Filesystem\Filesystem;
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

    /**
     * @param string $error
     * @param array  $messageParams
     *
     * @throws HTML2CanvasProxyException
     */
    protected function raiseError($error, array $messageParams = [])
    {
        $errorMessages = Error::MESSAGES;

        list($errNum, $code) = explode('-', $error);

        $message = str_replace(array_keys($messageParams), array_values($messageParams), $errorMessages[$error]);

        throw new HTML2CanvasProxyException($message, $code);
    }

    /**
     * @throws HTML2CanvasProxyException
     *
     * @return bool
     */
    protected function validateRequest()
    {
        if (!($this->request instanceof Request)) {
            $this->initRequest();
        }

        if (!$this->request->server->has('HTTP_HOST') || strlen($this->request->server->get('HTTP_HOST')) === 0) {
            $this->raiseError(Error::REQUEST_NOT_SEND);
        } elseif (!$this->request->server->has('SERVER_PORT')) {
            $this->raiseError(Error::PORT_NOT_FOUND);
        } elseif ($this->maxExecTime < 10) {
            $this->raiseError(Error::SHORT_EXEC_TIME, [
                '#seconds#' => 15,
            ]);
        } elseif ($this->maxExecTime <= self::SOCKET_TIMEOUT) {
            $this->raiseError(Error::SOCKET_EXEC_TIME, [
                '#SOCKET_TIMEOUT#' => self::SOCKET_TIMEOUT
            ]);
        } elseif (!$this->request->query->has('url') || strlen($this->request->query->get('url')) === 0) {
            $this->raiseError(Error::MISSING_URL_GET_PARAMETER);
        } elseif (0 !== preg_match('#[^A-Za-z0-9_[.]\\[\\]]#', $this->paramCallback)) {
            $this->raiseError(Error::INVALID_CALLBACK_PARAMETER);
        }

        return true;
    }

    /**
     * @return string
     *
     * @throws HTML2CanvasProxyException
     */
    public function execute()
    {
        $this->initRequest();

        if ($this->validateRequest()) {
            $this->httpPort = (int)$this->request->server->get('SERVER_PORT');
            $url            = $this->request->query->get('url');

            $this->tmp = $this->createTmpFile($url, false);
            if ($this->tmp === false) {
                $err            = error_get_last();
                $this->response = ['error' => 'Can not create file' . (
                    $err !== null && isset($err['message']) && strlen($err['message']) > 0 ? (': ' . $err['message']) : ''
                    )];
                $err            = null;
            } else {
                $this->response = $this->downloadSource($url, $this->tmp['source'], 0);
                fclose($this->tmp['source']);
            }
        }

        if (is_array($this->response) && isset($this->response['mime']) && strlen($this->response['mime']) > 0) {
            clearstatcache();
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

                $this->removeOldFiles();

                if (true === $this->crossDomain) {
                    $mime = $this->response['mime'];
                    if ($this->response['encode'] !== null) {
                        $mime .= ';charset=' . json_encode($this->response['encode'], true);
                    }

                    $this->tmp = $this->response = null;

                    if (strpos($mime, 'image/svg') !== 0 && strpos($mime, 'image/') === 0) {
                        return $this->paramCallback . '("data:' . $mime . ';base64,' . base64_encode(file_get_contents($locationFile)) . '");';
                    } else {
                        return $this->paramCallback . '("data:' . $mime . ',' . $this->asciiToInline(file_get_contents($locationFile)) . '");';
                    }
                } else {
                    $this->tmp = $this->response = null;

                    $dir_name = dirname($_SERVER['SCRIPT_NAME']);
                    if ($dir_name === '\/' || $dir_name === '\\') {
                        $dir_name = '';
                    }

                    $json = json_encode(
                        ($this->httpPort === 443 ? 'https://' : 'http://') .
                        preg_replace('#:[0-9]+$#', '', $_SERVER['HTTP_HOST']) .
                        ($this->httpPort === 80 || $this->httpPort === 443 ? '' : (
                            ':' . $_SERVER['SERVER_PORT']
                        )) .
                        $dir_name . '/' .
                        $locationFile
                    );

                    return $this->paramCallback . '(' . $json . ');';
                }
            } else {
                $this->response = ['error' => 'Failed to rename the temporary file'];
            }
        }

        if (is_array($this->tmp) && isset($this->tmp['location']) && file_exists($this->tmp['location'])) {
            //remove temporary file if an error occurred
            unlink($this->tmp['location']);
        }


        //errors
        $this->setHeaders(true);//no-cache

        $this->removeOldFiles();

        return $this->paramCallback . '(' . json_encode('error: html2canvas-proxy-php: ' . $this->response['error']) . ');';

    }

    /**
     * Initialize request properties.
     *
     * @param bool $force
     */
    protected function initRequest($force = false)
    {
        if (!($this->request instanceof Request) || true === $force) {
            $this->request = $this->requestStack->getCurrentRequest();
            $this->request->headers->set('Content-Type', 'application/javascript');

            $initExec = time();
            if ($this->request->server->has('REQUEST_TIME')) {
                $requestTime = $this->request->server->get('REQUEST_TIME');
                if (strlen($requestTime) > 0) {
                    $initExec = (int)$requestTime;
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

        if (in_array('ssl', stream_get_transports())) {
            define('SOCKET_SSL_STREAM', '1');

            return true;
        }

        return 'Error';
    }

    /**
     * set headers in document
     *
     * @return void
     */
    protected function removeOldFiles()
    {
        $p = rtrim($this->imagesPath, '/') . '/';

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
        if (strpos($m, '//') === 0) {
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
     * @return boolean
     */
    protected function createFolder()
    {
        $fs = new Filesystem();

        $fs->mkdir($this->imagesPath);
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
            $tmpMine .= $this->request->server->get('REQUEST_TIME', (string)time());
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
                $fp,
                'GET ' . (
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
                    if (preg_match('#^location[:]#i', $data) !== 0) {
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
