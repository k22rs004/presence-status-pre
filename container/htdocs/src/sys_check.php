<?php
require_once('db_inc.php'); //データベースが必要なので読み込ませる
$u = $_POST['uid'];
$p = $_POST['pass'];
$sql = "SELECT * FROM tb_user WHERE user_id = '{$u}'  AND password='{$p}'";
$rs = $conn->query($sql);
$errorMessage = "";
if (!$rs) die('エラー: ' . $conn->error);
$row = $rs->fetch_assoc();
if ($row) { //Login succeeded
    $_SESSION['uid']   = $row['user_id'];
    $_SESSION['student_number']   = $row['student_number'];
    $_SESSION['uname'] = $row['user_name'];
    $_SESSION['urole'] = $row['role_id'];
    header('Location:index.php');
} else {
    // ログイン失敗時の処理

    // ユーザー名とパスワードが一致するかどうかをチェック
    $sql = "SELECT * FROM tb_user WHERE user_id = '$Su' AND password = '$p'"; // パスワードのチェックを追加
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // ログイン成功（本来はここに来ないはず）
    } else {
        // ログイン失敗
        // ユーザー名が存在するかチェック
        $sql = "SELECT * FROM tb_user WHERE user_id ='" . $u . "'";
        $result = $conn->query($sql);
        $errorMessage = 2;

    }

    if($u == '' | $u == null){
        $errorMessage = 1;
    }

    header("Location: ?do=sys_login&error=" . urlencode($errorMessage));
}
