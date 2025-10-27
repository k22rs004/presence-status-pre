<?php 

  $conn = new mysqli('mysql', 'root', 'zaiseki-pass', 'presence_status_tmp');//＜開発時の環境設定＞
  if ($conn->connect_errno) die($conn->connect_error);
  $conn->set_charset('utf8'); //文字コードをutf8に設定（文字化け対策）
/*
  try {
    $pdo = new PDO('mysql:host=mysql;dbname=presence_status_tmp', 'root', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
    exit("データベースに接続できませんでした。: " . $e->getMessage());
  }
    */

