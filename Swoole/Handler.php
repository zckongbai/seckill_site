<?php
namespace Swoole;

class Handler extends \Swoole\Http\HttpServer
{

    function __construct($config)
    {
    	parent::__construct($config);
    	$this->redis = new \Swoole\Cache\Redis($config['redis']);
    }

    /**
     * 处理逻辑
     */
    function processDynamic(\swoole_http_request $request, \swoole_http_response $response)
    {
        if ($this->document_root and is_file($this->document_root . $request->server['request_uri']))
        {
            return $this->doStatic($request, $response);
        }
    	$this->request = $request;
    	$this->response = $response;
    	// $this->log->put(var_export($request,true), 'INFO');
    	switch ($request->server['request_uri']) {
    		case '/goods/buy':
    			$get = $request->get;
    			$this->log->put(json_encode($get));

                // 先检查用户
                // if (!$this->limit_user())
                // {   
                //     $response->status = "404"; 
                //     $this->redirect($this->config['request']['404']);
                // }

                // 检查商品
 				$good_id = $this->redis->hget('seckill_goods_id', $get['id']);
 				if (!$good_id)
 				{
 					$response->status = "404"; 
 					$this->redirect($this->config['request']['404']);
 					return true ;
 				}

				$response->status = "302";
 				$good = $this->redis->hGetAll('goods:'.$get['id']);
                $goods_number_multiple = $this->config['seckill']['goods_number_multiple'] ? : 5;
 				$queue_size = $good['number'] * $goods_number_multiple; 

 				$queue_key = "site_queue_goods_id:" . $get['id'];
 				$queue = $this->redis->get($queue_key);
 				if(!$queue || $queue <= $queue_size) 
 				{
 					$this->log->put("allow");
 					$this->redis->incr($queue_key);
                    $url = $this->config['seckill']['allow'] . $request->server['request_uri'] . "?" . $request->server['query_string'] ;
                    $this->redirect($url);
 				}
 				else
 				{
 					$this->log->put("soldout");
 					$this->redirect($this->config['seckill']['soldout']);	
 				}

    			break;
    		
    		default:
    			# code...
                $response->status = "302"; 
                $this->redirect($this->config['request']['default_page']);
    			break;
    	}

        return true;
    }

    /**
     * 限制用户请求: 5s内成功一次
     */
    function limit_user()
    {
        if (!$this->request->get['uid'])
        {
            return false;
        }

        $limit_key = "seckill_site" . $this->request->get['uid'];
        $limit_res = $this->redis->get($limit_key);

        if (!$limit_res)
        {
            $time_limmit = $this->config['seckill']['time_limit'] ? : 5;
            $this->redis->setex($limit_key, $time_limmit);
            return true;
        }
        return false;
    }


}