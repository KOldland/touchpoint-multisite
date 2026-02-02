<?php
echo "Session save path: " . ini_get('session.save_path') . "<br>";
echo "Session save handler: " . ini_get('session.save_handler') . "<br>";
echo "Session auto start: " . ini_get('session.auto_start') . "<br>";
echo "Testing session creation...<br>";

session_start();
echo "Session ID: " . session_id() . "<br>";
$_SESSION['test'] = 'working';
echo "Session data set successfully<br>";
session_write_close();

echo "Session test completed.";
?>