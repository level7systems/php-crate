#!/usr/bin/php -q
<?php

require_once('PhpCrate.class.php');

$crate = new PhpCrate();
$crate->setServers(array("192.168.10.51","192.168.10.52","192.168.10.53"));

$sql = "CREATE TABLE test_table ( id long primary key, name string)";

$result = $crate->exec($sql);

echo "Created $result tables\n";

$sql = "INSERT INTO test_table (id, name) VALUES (?,?)";

$data = array();
$data[] = array(1, "John");
$data[] = array(2, "Peter");
$data[] = array(3, "Thomas");

foreach ($data as $args) {
    $result = $crate->exec($sql, $args);
    
    echo "$result rows inserted\n";
}

sleep(3); // wait for cluster to sync

foreach ($crate->query("SELECT * FROM test_table") as $row) {
    print_r($row);
}
