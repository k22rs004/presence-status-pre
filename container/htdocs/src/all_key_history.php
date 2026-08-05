<?php
// タイムゾーンをJST (日本標準時: UTC+9) に設定
date_default_timezone_set('Asia/Tokyo');

// 外部のデータベース接続ファイルを読み込み ($conn が定義されている)
require_once 'db_inc.php';

$error = '';
$history_list = [];

// --- ページネーションの設定 ---
$limit = 10; // 1ページあたりの表示件数
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 1. 総件数を取得
$total_count = 0;
$count_sql = "SELECT COUNT(*) AS total FROM tb_key";
if ($count_result = $conn->query($count_sql)) {
    $count_row = $count_result->fetch_assoc();
    $total_count = (int)$count_row['total'];
    $count_result->free();
}

// 総ページ数を計算
$max_page = ceil($total_count / $limit);
if ($max_page > 0 && $page > $max_page) {
    $page = $max_page; // 存在しないページ数を指定された場合は最終ページへ
}

// 取得開始位置（OFFSET）を計算
$offset = ($page - 1) * $limit;

// 2. 現在のページのデータのみ取得（LIMIT, OFFSET を追加）
$sql = "SELECT 
            k.key_record,
            k.pickup_date,
            k.holder_flag,
            u_new.name AS new_user_name,
            u_new.student_number AS new_user_student_number,
            u_prev.name AS prev_user_name,
            u_prev.student_number AS prev_user_student_number
        FROM tb_key k
        LEFT JOIN tb_user u_new ON k.user_id = u_new.user_id
        LEFT JOIN tb_user u_prev ON k.previous_user = u_prev.user_id
        ORDER BY k.pickup_date DESC, k.key_record DESC
        LIMIT {$limit} OFFSET {$offset}";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $history_list[] = $row;
    }
    $result->free();
} else {
    $error = '履歴データの取得に失敗しました: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
}
?>

<!-- Bootstrap 5 CSS CDN -->
<style>
    /* 渡した側200px, 矢印領域40px(左寄せで右側に余白作成), 受け取った側220px */
    .transfer-container {
        display: inline-grid;
        grid-template-columns: 200px 40px 220px;
        align-items: center;
    }
    .user-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .arrow-text {
        text-align: left;
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="card shadow-sm">
                <div class="card-body p-4">

                    <!-- エラーメッセージ表示 -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($history_list)): ?>
                        <div class="text-center py-4 text-muted">
                            登録されている鍵の受け渡し履歴はありません。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 25%;">受け渡し日時</th>
                                        <th scope="col">渡した側 ➔ 受け取った側</th>
                                        <th scope="col" class="text-center" style="width: 15%;">現在使用中</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history_list as $row): ?>
                                        <tr class="<?= $row['holder_flag'] == 1 ? 'table-success' : '' ?>">
                                            <!-- 受け渡し日時 -->
                                            <td>
                                                <?= htmlspecialchars(date('Y/m/d H:i', strtotime($row['pickup_date'])), ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            
                                            <!-- 渡した側 ➔ 受け取った側 -->
                                            <td>
                                                <div class="transfer-container">
                                                    <!-- 渡した側 (200px) -->
                                                    <div class="user-text text-start">
                                                        <span class="fw-bold">
                                                            <?= htmlspecialchars($row['prev_user_name'] ?? '未記録', ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <small class="text-muted ms-1">
                                                            （<?= htmlspecialchars($row['prev_user_student_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?>）
                                                        </small>
                                                    </div>

                                                    <!-- 矢印 (40px幅・左寄せ) -->
                                                    <div class="arrow-text">
                                                        <span class="text-primary fw-bold">➔</span>
                                                    </div>

                                                    <!-- 受け取った側 (220px) -->
                                                    <div class="user-text text-start">
                                                        <span class="fw-bold">
                                                            <?= htmlspecialchars($row['new_user_name'] ?? '未記録', ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                        <small class="text-muted ms-1">
                                                            （<?= htmlspecialchars($row['new_user_student_number'] ?? '-', ENT_QUOTES, 'UTF-8') ?>）
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- 現在使用中フラグ（1なら○、それ以外は-） -->
                                            <td class="text-center">
                                                <?php if ($row['holder_flag'] == 1): ?>
                                                    <span class="badge bg-success fs-6">○</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ページネーションコントロール -->
                        <?php if ($max_page > 1): ?>
                            <nav aria-label="ページ移動" class="mt-4">
                                <ul class="pagination justify-content-center mb-0">
                                    <!-- 前へボタン -->
                                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?do=all_key_history&page=<?= $page - 1 ?>" aria-label="前へ">
                                            &laquo; 前へ
                                        </a>
                                    </li>

                                    <!-- ページ番号ボタン -->
                                    <?php for ($i = 1; $i <= $max_page; $i++): ?>
                                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                            <a class="page-link" href="?do=all_key_history&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- 次へボタン -->
                                    <li class="page-item <?= ($page >= $max_page) ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?do=all_key_history&page=<?= $page + 1 ?>" aria-label="次へ">
                                            次へ &raquo;
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>