<?php 

  $conn = new mysqli('mysql', 'root', 'zaiseki-pass', 'presence_status');//＜開発時の環境設定＞
  if ($conn->connect_errno) die($conn->connect_error);
  $conn->set_charset('utf8'); //文字コードをutf8に設定（文字化け対策）

/*
  $conn = new mysqli('mysql', 'parse', '/tHC9Op///WXVftZ', 'presence_status_2025');//＜運用時の環境設定＞
  if ($conn->connect_errno) die($conn->connect_error);
  $conn->set_charset('utf8'); //文字コードをutf8に設定（文字化け対策）
*/