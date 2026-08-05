<?php
require_once 'connection.php';
Auth::logout();
redirect(BASE_URL . '/index.php');