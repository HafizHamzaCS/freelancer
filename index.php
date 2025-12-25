<?php

require_once 'config.php';
require_once 'functions.php';

// Redirect to dashboard (which will redirect to login if not authenticated)
redirect('dashboard.php');
?>
