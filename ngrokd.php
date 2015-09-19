<?php

require 'fun.php';
require 'ngrok.php';
$baseurl = '16116.org';

$sslinfo = array('ssl_cert_file' => '/home/ssl/server.crt',
    'ssl_key_file' => '/home/ssl/domain.key',
);

$serv = new swoole_server("0.0.0.0", 9503, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$serv->addlistener('0.0.0.0', 9502, SWOOLE_SOCK_TCP); //test
$serv->addlistener('0.0.0.0', 81, SWOOLE_SOCK_TCP); //http
$serv->addlistener('0.0.0.0', 448, SWOOLE_SOCK_TCP | SWOOLE_SSL); //https
//var_dump($serv->connections);

$serv->set(array(
    'worker_num' => 1, //工作进程数量
    //'daemonize' => true, //是否作为守护进程
    'daemonize' => false, //是否作为守护进程
    'ssl_cert_file' => $sslinfo['ssl_cert_file'],
    'ssl_key_file' => $sslinfo['ssl_key_file'],
));

$serv->reqproxylist = array();
$serv->reglist = array();
$serv->sockinfolist = array();

$server->on('connect', 'NGROK::connect');
$server->on('receive', 'NGROK::receive');
$server->on('close', 'NGROK::close');
$serv->start();
?>