<?php
require 'fun.php';

$baseurl = '16116.org';

$sslinfo=array('ssl_cert_file'=>'/home/ssl/server.crt',
				'ssl_key_file'=>'/home/ssl/domain.crt',
              );


$serv = new swoole_server("0.0.0.0", 9503, SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
$serv->addlistener('0.0.0.0', 9502, SWOOLE_SOCK_TCP); //test
$serv->addlistener('0.0.0.0', 81, SWOOLE_SOCK_TCP); //http
$serv->addlistener('0.0.0.0', 448, SWOOLE_SOCK_TCP | SWOOLE_SSL); //https



$serv->set(array(
    'worker_num' => 1, //工作进程数量
    //'daemonize' => true, //是否作为守护进程
    'daemonize' => true, //是否作为守护进程
    'ssl_cert_file' => $sslinfo['ssl_cert_file'],
    'ssl_key_file' => $sslinfo['ssl_key_file'],
));




$serv->reqproxylist = array();
$serv->reglist = array();
$serv->sockinfolist = array();


$serv->on('connect', function ($serv, $fd) {
    echo "Client:Connect.\n";
});
$serv->on('receive', function ($serv, $fd, $from_id, $data) {

    $fdinfo = $serv->connection_info($fd);
    if ($fdinfo['server_port'] == 9503 || $fdinfo['server_port'] == 9502) {

        if (!isset($serv->sockinfolist[$fd])) {
            $serv->sockinfolist[$fd] = array('type' => 1, //1 is contrl
                'fd' => $fd,
                'recvbuffer' => '',
            );
        }

        if (strlen($data) > 0) {
            $serv->sockinfolist[$fd]['recvbuffer'] = $serv->sockinfolist[$fd]['recvbuffer'] . $data;
        }

        $recvbut = $serv->sockinfolist[$fd]['recvbuffer'];

        if ($serv->sockinfolist[$fd]['type'] == 1) {
            //get len;
            $lenbuf = substr($recvbut, 0, 8);
            $len = tolen1($lenbuf);
            if (strlen($recvbut) >= (8 + $len)) {
                $json = substr($recvbut, 8, $len);
                $js = json_decode($json, true);
                $send = '';
                switch ($js['Type']) {
                    case 'Auth':
                        $send = Auth($js);
                        break;
                    case 'Ping':
                        $send = Pong($js);
                        //	$send1=ReqProxy($js);
                        break;
                    case 'ReqTunnel':
                        $back = ReqTunnel($fd, $js);
                        $serv->reqproxylist = $back['tunnelist'];
                        $send = $back['json'];
                        break;
                    //connect RegProxy
                    case 'RegProxy':
                        $tempsend = RegProxy($fdinfo, $serv->reglist, $fd, $js);
                        if (strlen($tempsend) > 0) {
                            $sendlen = lentobyte1(strlen($tempsend));
                            $serv->send($fd, $sendlen.$tempsend);

                            if (count($serv->reglist)) {
                                $xx = array_pop($serv->reglist);
                                //send local
                                if (strlen($xx['recvbut']) > 0) {
                                    $serv->send($fd, $xx['recvbut']);
                                }

                                $serv->sockinfolist[$fd] = array(
                                    'type' => 2, //1 is contrl
                                    'fd' => $fd,
                                    'tofd' => $xx['fd'],
                                    'recvbuffer' => $serv->sockinfolist[$fd]['recvbuffer'],
                                );

                                //
                                $serv->sockinfolist[$xx['fd']] = array('type' => 4,
                                    'fd' => $xx['fd'],
                                    'tofd' => $fd,
                                    'recvbuffer' => $serv->sockinfolist[$xx['fd']]['recvbuffer'],
                                );


                            }
                            //关闭连接
                            else {
                                $serv->close($fd);
                            }
                        }
                        break;
                }
                //send 
                if (strlen($send) > 0) {
                    $sendlen = lentobyte1(strlen($send));
                    $serv->send($fd, $sendlen.$send);
                }


                //edit buffer
                if (strlen($recvbut) == (8 + $len)) {
                    $serv->sockinfolist[$fd]['recvbuffer'] = '';
                } else {
                    $serv->sockinfolist[$fd]['recvbuffer'] = substr($recvbut, 8 + $len);
                }
            }
        }
        //已经进入代理模式，数据直接转发给远程的socket就行了。。
        else {
            $serv->send($serv->sockinfolist[$fd]['tofd'], $recvbut);
            $serv->sockinfolist[$fd]['recvbuffer'] = '';
        }
    }

    //81 http 448 https
    if ($fdinfo['server_port'] == 81 || $fdinfo['server_port'] == 448) {

        if (!isset($serv->sockinfolist[$fd])) {
            $serv->sockinfolist[$fd] = array('type' => 3, //1 is sock ,2 is proxy sock,3 is user sock,4 is connect proxy
                'fd' => $fd,
                'recvbuffer' => '',
            );
        }


        if (strlen($data) > 0) {
            $serv->sockinfolist[$fd]['recvbuffer'] = $serv->sockinfolist[$fd]['recvbuffer'] . $data;
        }
        $recvbut = $serv->sockinfolist[$fd]['recvbuffer'];


        if ($serv->sockinfolist[$fd]['type'] == 3) {
            $httpinfo = httphead($recvbut);
            $Subdomain = substr($httpinfo['Host'], 0, strpos($httpinfo['Host'], '.'));
            $cfd = $serv->reqproxylist[$Subdomain];

            if ($cfd > 0) {
				$Protocol=$fdinfo['server_port'] ==81?'http':'https';
                array_push($serv->reglist, array('Protocol' => $Protocol,
                    'Subdomain' => $Subdomain,
                    'recvbut' => $recvbut,
                    'fd' => $fd,
                ));
                $sendbuf = ReqProxy(null);
                $sendlen = lentobyte1(strlen($sendbuf));
                $serv->send($cfd, $sendlen . $sendbuf);
            } else {
                  $body='Tunnel '.$httpinfo['Host'].' not found.';
                  $request='HTTP/1.0  404 Not Found.'."\r\n".'Content-Length: '.strlen($body)."\r\n".$body;
                  $serv->send($fd,$request);
				  $serv->close($fd);
                 
            }
        }
        else {
            $serv->send($serv->sockinfolist[$fd]['tofd'], $recvbut);
            $serv->sockinfolist[$fd]['recvbuffer'] = '';
        }
    }
});
$serv->on('close', function ($serv, $fd) {
    if (isset($serv->sockinfolist[$fd]['type']) && $serv->sockinfolist[$fd]['type'] == 1) {

    }
    //if proxy sock close http  connect
    if (isset($serv->sockinfolist[$fd]['type']) && $serv->sockinfolist[$fd]['type'] == 2) {
        $serv->close($serv->sockinfolist[$fd]['tofd']);
    }
    echo "Client: Close.\n";
});
$serv->start();


?>