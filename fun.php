<?php

function Auth($js) {
    $Payload = array(
        'Version' => '2',
        'MmVersion' => '1.7',
        'ClientId' => MD5(time()),
        'Error' => '',
    );
    $json = array(
        'Type' => 'AuthResp',
        'Payload' => $Payload
    );
    return json_encode($json);
}

function Pong($js) {
    $Payload = (object) array();
    $json = array(
        'Type' => 'Pong',
        'Payload' => $Payload,
    );
    return json_encode($json);
}

function ReqProxy($js) {
    $Payload = (object) array();
    $json = array(
        'Type' => 'ReqProxy',
        'Payload' => $Payload,
    );
    return json_encode($json);
}

function ReqTunnel($fd, $js) {
    /*
      json:{"Type":"NewTunnel","Payload":{"ReqId":"47fa0e022","Url":"https://jobtest.t
      unnel.mobi","Protocol":"https","Error":""}}
     */

    global $baseurl;

    if ($js['Payload']['Protocol'] == 'http' || $js['Payload']['Protocol'] == 'https') {
        if (strlen($js['Payload']['Subdomain']) > 0) {
            $url_ = $js['Payload']['Subdomain'];
        } else {
            $url_ = substr(MD5(time() . rand(0, 1000)), 0, 8);
        }
        $url = $url_ . '.' . $baseurl;
        $tunnelist[$url_] = $fd;
    }




    $Payload = array('ReqId' => $js['Payload']['ReqId'],
        'Protocol' => $js['Payload']['Protocol'],
        'Error' => '',
        'Url' => $url,
    );
    $json = array(
        'Type' => 'NewTunnel',
        'Payload' => $Payload,
    );
    return array('json' => json_encode($json),
        'tunnelist' => $tunnelist,
    );
}

function httphead($request) {
    $http = explode("\n", $request);
    $REQUEST_METHOD = substr($http[0], 0, strpos($http[0], ' '));
    $back = array();
    foreach ($http as $k => $z) {
        if ($k > 0) {
            $key = trim(substr($z, 0, strpos($z, ':')));
            $value = trim(substr($z, strpos($z, ':') + 1));
            $back[$key] = $value;
        }
    }
    $back['REQUEST_METHOD'] = $REQUEST_METHOD;
    return $back;
}

function RegProxy($fdinfo, $reglist, $fd, $js) {

    /*
      {"Type":"StartProxy","Payload":{"Url":"http://jobtest1.tunnel.mobi","Cl
      ientAddr":"183.48.73.230:33604"}}
     */
    global $baseurl;
    $xx = $reglist[0];

    $Payload = array('Url' => $xx['Protocol'] . '://' . $xx['Subdomain'] . '.' . $baseurl,
        'ClientAddr' => $fdinfo['remote_ip'] . ':' . $fdinfo['remote_port'],
    );
    $json = array(
        'Type' => 'StartProxy',
        'Payload' => $Payload,
    );
    return json_encode($json);
}

/*
  网络字节序
 */

function tolen($v) {
    list ($hi, $lo) = array_values(unpack("N*N*", $v));
    if ($hi < 0)
        $hi += (1 << 32);
    if ($ho < 0)
        $lo += (1 << 32);
    return ($hi << 32) + $lo;
}

/* 机器字节序 */

function tolen1($v) {
    list ($hi, $lo) = array_values(unpack("L*L*", $v));
    if ($hi < 0)
        $hi += (1 << 32);
    if ($ho < 0)
        $lo += (1 << 32);
    return ($hi << 32) + $lo;
}

/* 网络字节序 */

function lentobyte($len) {
    $xx = pack("N", $len);
    $xx1 = pack("C4", 0, 0, 0, 0);
    return $xx1 . $xx;
}

/* 机器字节序 */

function lentobyte1($len) {
    $xx = pack("L", $len);
    $xx1 = pack("C4", 0, 0, 0, 0);
    return $xx . $xx1;
}

?>