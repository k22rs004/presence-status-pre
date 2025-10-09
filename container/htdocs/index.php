<?php
// index.php - PHP サンプルファイル

// ヘッダーで文字コードを指定
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>PHP サンプルページ</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      margin: 20px;
      padding: 20px;
    }
    h1 {
      color: #333;
    }
    p {
      font-size: 1.2em;
    }
    .info {
      margin-top: 40px;
      padding: 10px;
      background-color: #e9e9e9;
      border: 1px solid #ccc;
    }
  </style>
</head>
<body>
  <h1>PHP サンプルページ</h1>
  <p>Hello, World!</p>
  <p>現在の日時: <?php echo date("Y-m-d H:i:s"); ?></p>

  <div class="info">
    <h2>PHP 情報</h2>
    <?php
      // PHP の詳細情報を表示（設定や拡張モジュールの確認に便利）
      phpinfo();
    ?>
  </div>
</body>
</html>