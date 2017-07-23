<?php

$config = array(

		'redis'	=>	array(
				'redis_id'	=>	'master',
				'host'	=>	'127.0.0.1',
				'port'	=>	'6379',
				'password'	=>	'',
				'pconnect'	=>	true,
				'database'	=>	'',
				'timeout'	=>	0.5,
				'error_log'	=>	LOGPATH . '/redis_error.log',
			),

		'seckill'	=>	array(
				'allow'	=>	'http://127.0.0.1:9501',
				'soldout'	=>	'http://127.0.0.1:9500/goods/soldout.html'

			),

		'limit_user'	=>	array(
				'time_limit'	=>	5000,	//毫秒

			),
	);

return $config;