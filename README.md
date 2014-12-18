php-crate
=========

Simple class to interact with Crate.io data store

Usage:

Create object instance and add Crate.io servers IP addresses:

```
$crate = new PhpCrate();
$crate->setServers(array("192.168.10.51","192.168.10.52","192.168.10.53"));
```

To create a new table:

```
$sql = "CREATE TABLE test_table ( id long primary key, name string)";

$result = $crate->exec($sql);

echo "Created $result tables\n";

# Created 1 tables
```

To populate table with sample data:
```
$sql = "INSERT INTO test_table (id, name) VALUES (?,?)";

$data = array();
$data[] = array(1, "John");
$data[] = array(2, "Peter");
$data[] = array(3, "Thomas");

foreach ($data as $args) {
    $result = $crate->exec($sql, $args);
    
    echo "$result rows inserted\n";
}

# 1 rows inserted
# 1 rows inserted
# 1 rows inserted
```

To query data:
```
foreach ($crate->query("SELECT * FROM test_table") as $row) {
    print_r($row);
}

# Array
# (
#     [id] => 3
#     [name] => Thomas
# )
# Array
# (
#     [id] => 1
#     [name] => John
# )
# Array
# (
#     [id] => 2
#     [name] => Peter
# )
```
