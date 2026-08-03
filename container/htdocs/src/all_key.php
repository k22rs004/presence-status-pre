<?php
// タイムゾーンをJST (日本標準時: UTC+9) に設定
date_default_timezone_set('Asia/Tokyo');

// 外部のデータベース接続ファイルを読み込み ($conn が定義されている)
require_once 'db_inc.php';

$message = '';
$error = '';

// POST送信時（鍵受け渡し登録処理）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $previous_user_id = filter_input(INPUT_POST, 'previous_user', FILTER_VALIDATE_INT);
    $new_user_id      = filter_input(INPUT_POST, 'new_user', FILTER_VALIDATE_INT);

    if (!$previous_user_id || !$new_user_id) {
        $error = '前所有者と新所有者を正しく選択してください。';
    } elseif ($previous_user_id === $new_user_id) {
        $error = '前所有者と新所有者が同じです。別のユーザーを選択してください。';
    } else {
        // 現在のJST日時を取得 (YYYY-MM-DD HH:MM:SS)
        $now_jst = date('Y-m-d H:i:s');

        // トランザクション開始
        $conn->begin_transaction();

        try {
            // 1. 鍵を渡したユーザー（previous_user_id）の holder_flag = 1 のレコードのみ 0 に更新
            $sqlUpdateFlag = "UPDATE tb_key SET holder_flag = 0 WHERE user_id = ? AND holder_flag = 1";
            $stmtUpdate = $conn->prepare($sqlUpdateFlag);
            if (!$stmtUpdate) {
                throw new Exception($conn->error);
            }
            $stmtUpdate->bind_param('i', $previous_user_id);
            if (!$stmtUpdate->execute()) {
                throw new Exception($stmtUpdate->error);
            }
            $stmtUpdate->close();

            // 2. 新しい鍵所有者レコードを追加する（holder_flag = 1）
            // pickup_date に JST日時（$now_jst）を直接指定
            $sqlInsert = "INSERT INTO tb_key (user_id, previous_user, pickup_date, holder_flag) 
                          VALUES (?, ?, ?, 1)";
            $stmtInsert = $conn->prepare($sqlInsert);
            if (!$stmtInsert) {
                throw new Exception($conn->error);
            }

            // bind_param の型指定: i (int), i (int), s (string)
            $stmtInsert->bind_param('iis', $new_user_id, $previous_user_id, $now_jst);
            if (!$stmtInsert->execute()) {
                throw new Exception($stmtInsert->error);
            }
            $stmtInsert->close();

            // すべて成功した場合はコミット
            $conn->commit();
            $message = '鍵の受け渡し履歴を正常に登録しました！';

        } catch (Exception $e) {
            // エラーが発生した場合はロールバック
            $conn->rollback();
            $error = '登録処理に失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// 画面描画用：1. 前の鍵の所有者（現在いずれかの鍵を所持している holder_flag = 1 のユーザー）
$previous_users = [];
$sqlPrevious = "SELECT DISTINCT u.user_id, u.name, u.student_number 
                FROM tb_key k 
                JOIN tb_user u ON k.user_id = u.user_id 
                WHERE k.holder_flag = 1 AND u.student_number != 'guest'
                ORDER BY u.student_number ASC";
if ($resultPrevious = $conn->query($sqlPrevious)) {
    while ($row = $resultPrevious->fetch_assoc()) {
        $previous_users[] = $row;
    }
    $resultPrevious->free();
}

// 画面描画用：2. 新しい鍵の所有者一覧（guestおよび「現在鍵を所持しているユーザー」を除外）
$new_users = [];
$sqlUsers = "SELECT user_id, name, student_number 
             FROM tb_user 
             WHERE student_number != 'guest' 
               AND user_id NOT IN (
                   SELECT DISTINCT user_id FROM tb_key WHERE holder_flag = 1
               )
             ORDER BY student_number ASC";
if ($resultUsers = $conn->query($sqlUsers)) {
    while ($row = $resultUsers->fetch_assoc()) {
        $new_users[] = $row;
    }
    $resultUsers->free();
} else {
    $error = 'ユーザーデータの取得に失敗しました。';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>鍵受け渡し登録システム</title>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 mx-auto">
            
            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <!-- メッセージ表示領域 -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" id="keyTransferForm">
                        
                        <!-- 前の所有者 (holder_flag=1 を持っているユーザーのみ表示) -->
                        <div class="mb-4">
                            <label for="previous_user" class="form-label fw-bold">前の鍵の所有者</label>
                            <select name="previous_user" id="previous_user" class="form-select form-select-lg" required>
                                <?php if (empty($previous_users)): ?>
                                    <option value="" selected disabled>現在鍵を所有しているユーザーがいません</option>
                                <?php else: ?>
                                    <option value="" selected disabled>選択してください</option>
                                    <?php foreach ($previous_users as $p_user): ?>
                                        <option value="<?= $p_user['user_id'] ?>">
                                            <?= htmlspecialchars($p_user['name'], ENT_QUOTES, 'UTF-8') ?>（<?= htmlspecialchars($p_user['student_number'], ENT_QUOTES, 'UTF-8') ?>）
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- 矢印表示 -->
                        <div class="text-center my-3 text-muted">
                            <span class="fs-3">⬇</span>
                        </div>

                        <!-- 新しい所有者 (現在鍵を所持していないユーザー一覧) -->
                        <div class="mb-4">
                            <label for="new_user" class="form-label fw-bold">新しい鍵の所有者</label>
                            <select name="new_user" id="new_user" class="form-select form-select-lg" required>
                                <option value="" selected disabled>選択してください</option>
                                <?php foreach ($new_users as $user): ?>
                                    <option value="<?= $user['user_id'] ?>">
                                        <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>（<?= htmlspecialchars($user['student_number'], ENT_QUOTES, 'UTF-8') ?>）
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- 登録ボタン -->
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" <?= empty($previous_users) ? 'disabled' : '' ?>>登録</button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap 5 JavaScript Bundle CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- フロントエンド用 JavaScript（重複チェック） -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('keyTransferForm');
    const prevSelect = document.getElementById('previous_user');
    const newSelect = document.getElementById('new_user');

    form.addEventListener('submit', function(e) {
        const prevValue = prevSelect.value;
        const newValue = newSelect.value;

        if (prevValue && newValue && prevValue === newValue) {
            e.preventDefault();
            alert('前の所有者と新しい所有者に同じ人物が選択されています。');
        }
    });
});
</script>

</body>
</html>