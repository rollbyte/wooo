<?php

namespace wooo\core;

class Request
{
    private $IsHttps = false;
  
    /**
     *
     * @var boolean
     */
    private $IsXmlHttpRequest = false;
  
    private $uri;
  
    private $path;
  
    private $query = null;
  
    private $body = null;
  
    private $files = null;
  
    private $pathParams = null;
    
    private $locals = [];
  
    private $rawPostData = null;
  
    private $tz;
    
    private $app;

    /**
     * @var \wooo\core\Locale
     */
    private $locale;
    
    /**
     * @var \wooo\core\Session
     */
    private $sess;
  
    private function acceptValue($value, $urldecode = false)
    {
        if (get_magic_quotes_gpc()) {
            if (is_array($value)) {
                array_walk_recursive(
                    $value,
                    function (&$item, $key, $urldecode) {
                        $item = stripslashes($urldecode ? rawurldecode($item) : $item);
                    },
                    $urldecode
                );
            } else {
                $value = stripslashes($urldecode ? rawurldecode($value) : $value);
            }
        }
        if (!is_null($value)) {
            return $value;
        }
        return null;
    }
  
    private function acceptParams($params, $urldecode, &$member)
    {
        foreach ($params as $key => $value) {
            $v = $this->acceptValue($value, $urldecode);
            if (is_array($member)) {
                $member[$key] = $v;
            } else if (is_object($member)) {
                $member->$key = $v;
            }
        }
    }
  
    public function __construct(App $app)
    {
        $this->app = $app;
        if (!isset($_SERVER['REQUEST_URI'])) {
            if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
                $this->uri = $_SERVER['HTTP_X_ORIGINAL_URL'];
            } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
                // IIS Isapi_Rewrite
                $this->uri = $_SERVER['HTTP_X_REWRITE_URL'];
            } else {
                // Use ORIG_PATH_INFO if there is no PATH_INFO
                if (!isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO'])) {
                    $_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
                }
        
                // Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
                if (isset($_SERVER['PATH_INFO'])) {
                    if ($_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME']) {
                        $this->uri = $_SERVER['PATH_INFO'];
                    } else {
                        $this->uri = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
                    }
                }
            }
        } else {
            $this->uri = $_SERVER['REQUEST_URI'];
        }
        $this->path = parse_url($this->uri, PHP_URL_PATH);
        if ($app->appRoot() && $app->appRoot() !== '/') {
            $this->path = str_ireplace($app->appRoot(), '', $this->path);
        }
        if (!$this->path || $this->path[0] != '/') {
            $this->path = '/' . $this->path;
        }
        $pathLength = strlen($this->path);
        if ($pathLength > 1 && $this->path[$pathLength - 1] === '/') {
            $this->path = substr($this->path, 0, $pathLength - 1);
        }
    
        $this->IsXmlHttpRequest = strtolower($this->getHeader('X_REQUESTED_WITH')) == 'xmlhttprequest';
        $this->IsHttps = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off');
    
        if ($tzh = $app->config()->get('tzHeader')) {
            $tz = $this->getHeader($tzh);
            if ($tz) {
                try {
                    $this->tz = new \DateTimeZone($tz);
                } catch (\Exception $e) {
                    $tz_name = timezone_name_from_abbr($tz);
                    if (!$tz_name) {
                        $tz_name = timezone_name_from_abbr('', $tz, 0);
                    }
                    if (!$tz_name) {
                        $tz_name = timezone_name_from_abbr('', $tz, 1);
                    }
                    if ($tz_name) {
                        $this->tz = new \DateTimeZone($tz_name);
                    }
                }
            }
        }
    
        if ($h = $this->getHeader('accept-language')) {
            $this->locale = new Locale(\Locale::acceptFromHttp($h));
        } else {
            $this->locale = new Locale(\Locale::getDefault());
        }
        
        $this->query = new \stdClass();
        $this->files = new \stdClass();
        $this->pathParams = new \stdClass();
    
        $this->acceptParams($_GET, true, $this->query);
        if (strtolower($this->getHeader('Content-Type')) == 'application/json') {
            $this->body = json_decode($this->getRawPostData());
        } else {
            $this->body = new \stdClass();
            $this->acceptParams($_POST, false, $this->body);
        }
    
        foreach ($_FILES as $key => $file) {
            if (is_array($file)/* && (($file['error'] == 0) || is_array($file['error']))*/) {
                if (is_array($file['name'])) {
                    $files = array();
                    foreach ($file['name'] as $i => $fname) {
                        if ($file['error'][$i] == UPLOAD_ERR_OK) {
                            $files[$i] = new UploadedFile(
                                array(
                                'name' => $fname,
                                'tmp_name' => $file['tmp_name'][$i],
                                'type' => $file['type'][$i],
                                'size' => $file['size'][$i],
                                'error' => UPLOAD_ERR_OK
                                )
                            );
                        } else {
                            $files[$i] = new UploadedFile(
                                array(
                                'error' => $file['error'][$i]
                                )
                            );
                        }
                    }
                    $this->files->$key = $files;
                } else {
                    $this->files->$key = new UploadedFile($file);
                }
            }
        }
        $this->sess = Session::instance($app->config());
    }
    
    public function forContext(App $app): Request
    {
        $result = new Request($app);
        $result->locals = $this->locals;
        $result->pathParams = $this->pathParams;
        return $result;
    }
    
    public function session(): Session
    {
        return $this->sess;
    }
  
    public function getRawPostData(): ?string
    {
        if (is_null($this->rawPostData)) {
            $this->rawPostData = file_get_contents('php://input');
        }
        return $this->rawPostData;
    }
  
    public function getHeader($nm): ?string
    {
        $nm = strtoupper(str_replace('-', '_', $nm));
        if (isset($_SERVER[$nm])) {
            return $_SERVER[$nm];
        } elseif (isset($_SERVER['HTTP_' . $nm])) {
            return $_SERVER['HTTP_' . $nm];
        }
        return null;
    }
  
    public function getCookie($nm): ?string
    {
        if (isset($_COOKIE[$nm])) {
            $v = $this->acceptValue($_COOKIE[$nm]);
            if ($v) {
                if ($key = $this->app->config()->get('cookieValidationKey', false)) {
                    $v = base64_decode($v);
                    $h = new Hash(Hash::SHA256);
                    $tmp = $h->apply('', $key);
                    $hashLength = mb_strlen($tmp, '8bit');
                    $cookieValue = mb_substr($v, $hashLength, null, '8bit');
                    $calcHash = $h->apply($cookieValue, $key);
                    $v = hash_equals($calcHash, mb_substr($v, 0, $hashLength, '8bit')) ? $cookieValue : null;
                }
                return $v;
            }
        }
        return null;
    }
    
    private function pathRegExp($pattern): string
    {
        $re = preg_replace_callback(
            '/\/|\:[\w_]+(\([^)]*\))?/i',
            function ($v) {
                if ($v[0] == '/') {
                    return '\/';
                } else if (count($v) > 1) {
                    return $v[1] . ($v[1][strlen($v[1]) - 1] == ')') ? '' : ')';
                }
                return '([\w%_-]+)';
            },
            $pattern
        );
        return '/^' . $re . '/i';
    }
  
    public function checkPath($pattern): bool
    {
        return preg_match($this->pathRegExp($pattern), $this->path) ? true : false;
    }
  
    public function parsePath($pattern): void
    {
        $re = $this->pathRegExp($pattern);
        $pvals = [];
        $matched = preg_match($re, $this->path, $pvals);
        if ($matched) {
            $pnames = [];
            preg_match_all('/:([\w_]+)/i', $pattern, $pnames, PREG_SET_ORDER);
            $n = count($pnames);
            for ($i = 0; $i < $n; $i++) {
                $pn = $pnames[$i][1];
                $this->pathParams->$pn = rawurldecode($pvals[$i + 1]);
            }
        }
    }
  
    public function getLocale(): Locale
    {
        return $this->locale;
    }
  
    public function getTimeZone(): ?\DateTimeZone
    {
        return $this->tz;
    }
  
    public function isAjax(): bool
    {
        return $this->IsXmlHttpRequest;
    }
  
    public function isSecured(): bool
    {
        return $this->IsHttps;
    }
  
    public function getHost(): ?string
    {
        return $_SERVER['HTTP_HOST'];
    }
  
    public function getMethod(): ?string
    {
        return $_SERVER['REQUEST_METHOD'];
    }
  
    public function getUri(): ?string
    {
        return $this->uri;
    }
  
    public function getPath(): ?string
    {
        return $this->path;
    }
  
    public function getParameters(): object
    {
        return $this->pathParams;
    }
  
    public function getQuery(): object
    {
        return $this->query;
    }
  
    public function getBody(): object
    {
        return $this->body;
    }
  
    public function getFiles(): object
    {
        return $this->files;
    }
    
    public function __set($nm, $v)
    {
        $this->locals[$nm] = $v;
    }
    
    public function __get($nm)
    {
        if (isset($this->locals[$nm])) {
            return $this->locals[$nm];
        }
        if (isset($this->getBody()->$nm)) {
            return $this->getBody()->$nm;
        }
        if (isset($this->getFiles()->$nm)) {
            return $this->getFiles()->$nm;
        }
        if (isset($this->getParameters()->$nm)) {
            return $this->getParameters()->$nm;
        }
        if (isset($this->getQuery()->$nm)) {
            return $this->getQuery()->$nm;
        }
    }
}
