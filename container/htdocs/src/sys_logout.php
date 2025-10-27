<?php
unset($_SESSION);
session_destroy();
//header('Location:?do=sys_login');
echo "<script>";
echo 'window.location.href = "?do=sys_login";';
echo "</script>";
