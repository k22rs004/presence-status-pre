<?php
session_start(); // 通常のセッション開始

// ヘッダーのインクルード
include('src/pg_header.php');

// uroleが存在しているかを確認してから処理
if (isset($_SESSION['urole']) && $_SESSION['urole'] == 0) {
    $action = 'all_home';
} else if (isset($_SESSION['urole']) && $_SESSION['urole'] == 1) {
    $action = 'all_home';
} else {
    $action = 'sys_login';
   // $action = 'all_home';
}

// GETパラメータで指定があれば上書き
if (isset($_GET['do'])) {
    $action = $_GET['do'];
}

// 指定されたファイルを読み込む
include('src/' . $action . '.php');

// フッターのインクルード
include('src/pg_footer.php'); 
?>
