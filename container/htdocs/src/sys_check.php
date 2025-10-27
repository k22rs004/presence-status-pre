<?php
require_once('db_inc.php'); //データベースが必要なので読み込ませる
$u = $_POST['unumber'];
$p = $_POST['pass'];
$sql = "SELECT * FROM tb_user WHERE student_number = '{$u}'  AND password='{$p}'";
$rs = $conn->query($sql);
$errorMessage = "";

if (!$rs) die('エラー: ' . $conn->error);
$row = $rs->fetch_assoc();
if ($row) { //Login succeeded
    $_SESSION['uid']   = $row['user_id'];
    $_SESSION['student_number']   = $row['student_number'];
    $_SESSION['uname'] = $row['name'];
    $_SESSION['urole'] = $row['account_type'];
    //header('Location:index.php');
    $url = "index.php";
    echo "<script>";
    echo "window.location.href = '{$url}';"; // 指定URLへ遷移
    echo "</script>";

} else {
    // ログイン失敗時の処理

    // ユーザー名とパスワードが一致するかどうかをチェック
    $sql = "SELECT * FROM tb_user WHERE student_number = '$u' AND password = '$p'"; // パスワードのチェックを追加
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

    //header("Location: ?do=sys_login&error=" . urlencode($errorMessage));
    $url = "?do=sys_login&error=" . urlencode($errorMessage);
    echo "<script>";
    echo "window.location.href = '{$url}';"; // 指定URLへ遷移
    echo "</script>";
}
