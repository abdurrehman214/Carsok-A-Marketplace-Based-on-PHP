<?php
require_once __DIR__.'/../connection.php';
$cols = DB::select("DESCRIBE users");
print_r($cols);
