<?php
namespace Swoole\Http;

class HttpServer
{
    const SOFTWARE = "seckill_site";
    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';
    const POST_MAXSIZE = 2000000; //POST最大2M

    /**
     * @var \swoole_http_request
     */
    public $request;

    /**
     * @var \swoole_http_response
     */
    public $response;

    public $http_protocol = 'HTTP/1.1';
    public $http_status = 200;
    public $head;
    public $cookie;
    public $body;

    public $charest = 'utf-8';
    public $expire_time = 86400;

    protected $config;

    static $gzip_extname = array('js' => true, 'css' => true, 'html' => true, 'txt' => true);

    static $HTTP_HEADERS = array(
        100 => "100 Continue",
        101 => "101 Switching Protocols",
        200 => "200 OK",
        201 => "201 Created",
        204 => "204 No Content",
        206 => "206 Partial Content",
        300 => "300 Multiple Choices",
        301 => "301 Moved Permanently",
        302 => "302 Found",
        303 => "303 See Other",
        304 => "304 Not Modified",
        307 => "307 Temporary Redirect",
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        408 => "408 Request Timeout",
        410 => "410 Gone",
        413 => "413 Request Entity Too Large",
        414 => "414 Request URI Too Long",
        415 => "415 Unsupported Media Type",
        416 => "416 Requested Range Not Satisfiable",
        417 => "417 Expectation Failed",
        500 => "500 Internal Server Error",
        501 => "501 Method Not Implemented",
        503 => "503 Service Unavailable",
        506 => "506 Variant Also Negotiates",
    );

    protected $types = array(
        'image/jpeg' => 'jpg',
        'image/bmp' => 'bmp',
        'image/x-icon' => 'ico',
        'image/gif' => 'gif',
        'image/png' => 'png',
        'application/octet-stream' => 'bin',
        'application/javascript' => 'js',
        'text/css' => 'css',
        'text/html' => 'html',
        'text/xml' => 'xml',
        'application/x-tar' => 'tar',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/pdf' => 'pdf',
        'application/x-shockwave-flash' => 'swf',
        'application/x-zip-compressed' => 'zip',
        'application/gzip' => 'gzip',
        'application/x-woff' => 'woff',
        'image/svg+xml' => 'svg',
    );

    function __construct($config='')
    {
        if (!empty($config['server']['charset']))
        {
            $this->charset = trim($config['server']['charset']);
        }
        $this->config = $config;
    }

    function onStart($serv, $worker_id = 0)
    {
        if (!defined('WEBROOT'))
        {
            define('WEBROOT', $this->config['server']['webroot']);
        }

        // Swoole\Error::$echo_html = true;
        $this->server = $serv;
        // $this->log(self::SOFTWARE . "[#{$worker_id}]. running. on {$this->server->host}:{$this->server->port}");
        // set_error_handler(array($this, 'onErrorHandle'), E_USER_ERROR);
        // register_shutdown_function(array($this, 'onErrorShutDown'));
    }
    /**
     * 捕获set_error_handle错误
     */
    function onErrorHandle($errno, $errstr, $errfile, $errline)
    {
        $error = array(
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        );
        $this->errorResponse($error);
    }

    /**
     * 发生了http错误
     * @param                 $code
     * @param Swoole\Response $response
     * @param string          $content
     */
    function httpError($code, $response, $content = '')
    {
        $response->header('Content-Type','text/html');
        $response->body = self::$HTTP_HEADERS[$code] .
            "<p>$content</p><hr><address>" . self::SOFTWARE . " at {$this->server->host}" .
            " Port {$this->server->port}</address>";
    }

    /**
     * 捕获register_shutdown_function错误
     */
    function onErrorShutDown()
    {
        $error = error_get_last();
        if (!isset($error['type'])) return;
        switch ($error['type'])
        {
            case E_ERROR :
            case E_PARSE :
            case E_USER_ERROR:
            case E_CORE_ERROR :
            case E_COMPILE_ERROR :
                break;
            default:
                return;
        }
        $this->errorResponse($error);
    }


    /**
     * 设置Logger
     * @param $log
     */
    function setLogger($log)
    {
        $this->log = $log;
    }

    /**
     * 设置document_root
     * @param $dir
     */
    function setDocumentRoot($dir)
    {
        $this->document_root = $dir;
    }

    function header($k, $v)
    {
        $k = ucwords($k);
        $this->response->header($k, $v);
    }

    function status($code)
    {
        $this->response->status($code);
    }

    function response($content)
    {
        $this->finish($content);
    }

    function redirect($url, $mode = 302)
    {
        $this->response->status($mode);
        $this->response->header('Location', $url);
    }

    function finish($content = '')
    {
        // throw new Swoole\Exception\Response($content);
    }

    function getRequestBody()
    {
        return $this->request->rawContent();
    }

    function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        $this->response->cookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 将swoole扩展产生的请求对象数据赋值给框架的Request对象
     * @param Swoole\Request $request
     */
    function assign(\Swoole\Request $request)
    {
        if (isset($this->request->get))
        {
            $request->get = $this->request->get;
        }
        if (isset($this->request->post))
        {
            $request->post = $this->request->post;
        }
        if (isset($this->request->files))
        {
            $request->files = $this->request->files;
        }
        if (isset($this->request->cookie))
        {
            $request->cookie = $this->request->cookie;
        }
        if (isset($this->request->server))
        {
            foreach($this->request->server as $key => $value)
            {
                $request->server[strtoupper($key)] = $value;
            }
            $request->remote_ip = $this->request->server['remote_addr'];
        }
        $request->header = $this->request->header;
        // $request->setGlobal();
    }

    /**
     * 设置Http状态
     * @param $code
     */
    function setHttpStatus($code)
    {
        $this->head[0] = $this->http_protocol.' '.self::$HTTP_HEADERS[$code];
        $this->http_status = $code;
    }

    /**
     * 设置Http头信息
     * @param $key
     * @param $value
     */
    function setHeader($key,$value)
    {
        $this->head[$key] = $value;
    }

    /**
     * 获取客户端IP
     * @return string
     */
    function getClientIP()
    {
        if (isset($this->server["HTTP_X_REAL_IP"]) and strcasecmp($this->server["HTTP_X_REAL_IP"], "unknown"))
        {
            return $this->server["HTTP_X_REAL_IP"];
        }
        if (isset($this->server["HTTP_CLIENT_IP"]) and strcasecmp($this->server["HTTP_CLIENT_IP"], "unknown"))
        {
            return $this->server["HTTP_CLIENT_IP"];
        }
        if (isset($this->server["HTTP_X_FORWARDED_FOR"]) and strcasecmp($this->server["HTTP_X_FORWARDED_FOR"], "unknown"))
        {
            return $this->server["HTTP_X_FORWARDED_FOR"];
        }
        if (isset($this->server["REMOTE_ADDR"]))
        {
            return $this->server["REMOTE_ADDR"];
        }
        return "";
    }

    /**
     * 从ini文件中加载配置
     * @param $ini_file
     */
    function loadSetting($ini_file)
    {
        if (!is_file($ini_file)) exit("Swoole AppServer配置文件错误($ini_file)\n");
        $config = parse_ini_file($ini_file, true);
        /*--------------Server------------------*/
        //开启http keepalive
        if (!empty($config['server']['keepalive']))
        {
            $this->keepalive = true;
        }
        //是否压缩
        if (!empty($config['server']['gzip_open']) and function_exists('gzdeflate'))
        {
            $this->gzip = true;
            //default level
            if (empty($config['server']['gzip_level']))
            {
                $config['server']['gzip_level'] = 1;
            }
            //level [1, 9]
            elseif ($config['server']['gzip_level'] > 9)
            {
                $config['server']['gzip_level'] = 9;
            }
        }
        //过期控制
        if (!empty($config['server']['expire_open']))
        {
            $this->expire = true;
            if (empty($config['server']['expire_time']))
            {
                $config['server']['expire_time'] = 1800;
            }
        }
        /*--------------Session------------------*/
        if (empty($config['session']['cookie_life'])) $config['session']['cookie_life'] = 86400; //保存SESSION_ID的cookie存活时间
        if (empty($config['session']['session_life'])) $config['session']['session_life'] = 1800; //Session在Cache中的存活时间
        if (empty($config['session']['cache_url'])) $config['session']['cache_url'] = 'file://localhost#sess'; //Session在Cache中的存活时间
        /*--------------Apps------------------*/
        if (empty($config['apps']['url_route'])) $config['apps']['url_route'] = 'url_route_default';
        if (empty($config['apps']['auto_reload'])) $config['apps']['auto_reload'] = 0;
        if (empty($config['apps']['charset'])) $config['apps']['charset'] = 'utf-8';

        if (!empty($config['access']['post_maxsize']))
        {
            $this->config['server']['post_maxsize'] = $config['access']['post_maxsize'];
        }
        if (empty($config['server']['post_maxsize']))
        {
            $config['server']['post_maxsize'] = self::POST_MAXSIZE;
        }
        /*--------------Access------------------*/
        $this->deny_dir = array_flip(explode(',', $config['access']['deny_dir']));
        $this->static_dir = array_flip(explode(',', $config['access']['static_dir']));
        $this->static_ext = array_flip(explode(',', $config['access']['static_ext']));
        $this->dynamic_ext = array_flip(explode(',', $config['access']['dynamic_ext']));
        /*--------------document_root------------*/
        if (empty($this->document_root) and !empty($config['server']['document_root']))
        {
            $this->document_root = $config['server']['document_root'];
        }
        /*-----merge----*/
        if (!is_array($this->config))
        {
            $this->config = array();
        }
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 过滤请求，阻止静止访问的目录，处理静态文件
     * @param Swoole\Request $request
     * @param Swoole\Response $response
     * @return bool
     */
    function doStaticRequest($request, $response)
    {
        $path = explode('/', trim($request->server['path_info'], '/'));
        //扩展名
        $request->ext_name = $ext_name = $this->getFileExt($request->server['path_info']);
        /* 检测是否拒绝访问 */
        if (isset($this->deny_dir[$path[0]]))
        {
            $this->httpError(403, $response, "服务器拒绝了您的访问(" . $request->server['path_info'] . ")");
            return true;
        }
        /* 是否静态目录 */
        elseif (isset($this->static_dir[$path[0]]) or isset($this->static_ext[$ext_name]))
        {
            return $this->processStatic($request, $response);
        }
        return false;
    }

    /**
     * 处理静态请求
     * @param Swoole\Request $request
     * @param Swoole\Response $response
     * @return bool
     */
    function processStatic(\swoole_http_request $request, \swoole_http_response $response)
    {
        $path = $this->document_root  . $request->server['path_info'];
        if (is_file($path))
        {
            $read_file = true;
            if ($this->expire)
            {
                $expire = intval($this->config['server']['expire_time']);
                $fstat = stat($path);
                //过期控制信息
                if (isset($request->header['If-Modified-Since']))
                {
                    $lastModifiedSince = strtotime($request->header['If-Modified-Since']);
                    if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince)
                    {
                        //不需要读文件了
                        $read_file = false;
                        $response->status = 304;
                        $response->body = $this->http_protocol.' '.self::$HTTP_HEADERS[304];
                    }
                }
                else
                {
                    $response->header('Cache-Control', "max-age={$expire}");
                    $response->header('Pragma', "max-age={$expire}");
                    $response->header('Last-Modified', date(self::DATE_FORMAT_HTTP, $fstat['mtime']));
                    $response->header('Expires', "max-age={$expire}");
                }
            }
            $extname = $this->getFileExt($request->server['path_info']);
            if (empty($this->types[$extname]))
            {
                $mime_type = 'text/html; charset='.$this->charest;
            }
            else
            {
                $mime_type = $this->types[$extname];
            }
            if($read_file)
            {
                $response->header('Content-Type', $mime_type);
                $response->body = file_get_contents($path);
                // $response->end (file_get_contents($path));
            }
            return true;
        }
        else
        {
            return false;
        }
    }

    function doStatic(\swoole_http_request $req, \swoole_http_response $resp)
    {
        $file = $this->document_root . $req->server['request_uri'];
        $read_file = true;
        $fstat = stat($file);

        //过期控制信息
        if (isset($req->header['if-modified-since']))
        {
            $lastModifiedSince = strtotime($req->header['if-modified-since']);
            if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince)
            {
                //不需要读文件了
                $read_file = false;
                $resp->status(304);
            }
        }
        else
        {
            $resp->header('Cache-Control', "max-age={$this->expire_time}");
            $resp->header('Pragma', "max-age={$this->expire_time}");
            $resp->header('Last-Modified', date(self::DATE_FORMAT_HTTP, $fstat['mtime']));
            $resp->header('Expires',  "max-age={$this->expire_time}");
        }

        if ($read_file)
        {
            $extname = $this->getFileExt($file);
            if (empty($this->types[$extname]))
            {
                $mime_type = 'text/html; charset='.$this->charest;
            }
            else
            {
                $mime_type = $this->types[$extname];
            }
            $resp->header('Content-Type', $mime_type);
            $resp->sendfile($file);
        }
        else
        {
            $resp->status = 404;
            // $resp->body = self::$HTTP_HEADERS['404'];
            $resp->end(self::$HTTP_HEADERS['404']);
        }
        return true;
    }

    /**
     * 根据文件名获取扩展名
     * @param $file
     * @return string
     */
    public function getFileExt($file)
    {
        $s = strrchr($file, '.');
        if ($s === false)
        {
            return false;
        }
        return strtolower(trim(substr($s, 1)));
    }

    /**
     * 处理请求
     * @param $request
     * @return Swoole\Response
     */
    function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        //请求路径
        if ($request->server['path_info'][strlen($request->server['path_info']) - 1] == '/')
        {
            $request->server['path_info'] .= $this->config['request']['default_page'];
        }

        if ($this->doStaticRequest($request, $response))
        {
             //pass
        }
        /* 动态脚本 */
        elseif (isset($this->dynamic_ext[$request->ext_name]) or empty($request->ext_name))
        {
            $this->processDynamic($request, $response);
        }
        else
        {
            $this->httpError(404, $response, "Http Not Found(" . $request->server['path_info'] . ")");
        }
        $response->end(isset($response->body) ? $response->body : '');
    }

    // function onRequest(\swoole_http_request $request, \swoole_http_response $response){}
    // function processDynamic($request, $response){}

}