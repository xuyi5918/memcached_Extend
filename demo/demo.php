<?php
include './Memcached.php';
$redisList = array(
    'master' => array('23.83.231.148',1314)
);
$Client=new memcachedClient($redisList);
var_dump($Client->select('master')->exec->get('kkk'));