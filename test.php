<?php 
include 'sql.php';

$sql = new sql('sql.env');

$q = $sql->query("SELECT * FROM texts");

var_dump($sql->fetch($q, true));



?>