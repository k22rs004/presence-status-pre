<?php
// ----------------------------------------------------------------------
// データベース接続、変数初期化、フォーム送信処理
// ----------------------------------------------------------------------
// ユーザー環境に合わせてファイル名を修正してください。
include('db_inc.php'); 

// 予定IDがGETパラメータで渡されているかチェック
$schedule_id = $_GET['schedule_id'] ?? 0;
if (!is_numeric($schedule_id) || $schedule_id <= 0) {
    die("エラー: 予定IDが指定されていません。");
}

// 初期値の設定
$schedule_name = '';
$start_h = '00';
$start_m = '00';
$end_h = '00';
$end_m = '00';
$days_of_week = []; // 選択された曜日（0:日, 1:月, ..., 6:土）
$schedule_place = '';
$error_message = '';
$is_authenticated = false; // ユーザー認証フラグ

// ユーザーIDはセッションから取得。セッションが開始されていることが前提です。
$user_id = $_SESSION['uid'] ?? 0;
$back = $_GET['back'] ?? 1; // 1: all_schedule (予定一覧), 0: all_在席時間帯？

$day_labels = ['日', '月', '火', '水', '木', '金', '土'];

// ----------------------------------------------------------------------
// 既存データの読み込みとユーザー認証
// ----------------------------------------------------------------------
if ($user_id > 0) {
    $sql_select = "SELECT * FROM tb_schedule WHERE schedule_id = ?";
    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $schedule_id);
        $stmt_select->execute();
        $result = $stmt_select->get_result();

        if ($schedule_data = $result->fetch_assoc()) {
            // ユーザーIDの確認
            if ($schedule_data['user_id'] == $user_id) {
                $is_authenticated = true;

                // フォームに初期値をセット
                $schedule_name = $schedule_data['schedule_name'];
                $schedule_place = $schedule_data['schedule_place'];

                // 時刻の分解
                list($start_h, $start_m) = explode(':', $schedule_data['schedule_start']);
                list($end_h, $end_m) = explode(':', $schedule_data['schedule_end']);

                // 曜日のビットマップから配列へ変換
                $day_bitmap_int = (int)$schedule_data['schedule_day_of_week'];
                $days_of_week = [];
                for ($i = 0; $i < 7; $i++) {
                    // $i=0:日, 1:月...。ビットマップは0:日(右端), 6:土(左端)の順
                    // 曜日インデックス $i に対応するビットは (6 - $i)
                    if ($day_bitmap_int & (1 << (6 - $i))) {
                        $days_of_week[] = $i;
                    }
                }
            } else {
                // データベースから取得したユーザーIDと入力のユーザーIDが異なる場合
                die("エラー: この予定を編集する権限がありません。");
            }
        } else {
            die("エラー: 指定された予定が見つかりません。");
        }
        $stmt_select->close();
    } else {
        die("エラー: SQL準備エラー (SELECT)");
    }
}

// ----------------------------------------------------------------------
// フォーム送信処理 (編集または削除)
// ----------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 戻るURLを決定
    $redirect_url = ($back == 0) ? "index.php?do=all_在席時間帯？" : "index.php?do=all_schedule";

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // 削除処理
        $sql_delete = "DELETE FROM tb_schedule WHERE schedule_id = ? AND user_id = ?";
        if ($stmt_delete = $conn->prepare($sql_delete)) {
            $stmt_delete->bind_param("ii", $schedule_id, $user_id);

            if ($stmt_delete->execute()) {
                $stmt_delete->close();
                echo '<script>alert("予定を削除しました。"); window.location.href = "' . $redirect_url . '";</script>';
                exit;
            } else {
                $error_message .= "・データベース削除エラー: " . $stmt_delete->error . "<br>";
                $stmt_delete->close();
            }
        } else {
            $error_message .= "・SQL準備エラー (DELETE): " . $conn->error . "<br>";
        }

    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        // 編集処理
        
        // 1. POSTデータの取得（更新後の値）
        $schedule_name = trim($_POST['schedule_name'] ?? '');
        $start_h = $_POST['start_h'] ?? '';
        $start_m = $_POST['start_m'] ?? '';
        $end_h = $_POST['end_h'] ?? '';
        $end_m = $_POST['end_m'] ?? '';
        $days_of_week = $_POST['day_of_week'] ?? [];
        $schedule_place = trim($_POST['schedule_place'] ?? '');

        // 2. 必須項目のバリデーション
        if (empty($schedule_name)) {
            $error_message .= "・予定名は必須項目です。<br>";
        }
        if (empty($start_h) || empty($start_m) || empty($end_h) || empty($end_m)) {
            $error_message .= "・開催時刻は必須項目です。<br>";
        }
        if (empty($days_of_week)) {
            $error_message .= "・曜日は必須項目です。<br>";
        }

        // 開催時刻の前後関係チェック
        $start_time_val = (int)$start_h * 60 + (int)$start_m;
        $end_time_val = (int)$end_h * 60 + (int)$end_m;

        if ($start_time_val >= $end_time_val) {
            $error_message .= "・開催時刻が無効です。終了時刻は開始時刻よりも後に設定してください。<br>";
        }

        // エラーがなければ更新処理へ
        if (empty($error_message)) {
            // 曜日のビットマップ変換と時刻の形式変換
            $day_bitmap_str = '';
            for ($i = 0; $i < 7; $i++) {
                $day_bitmap_str .= in_array($i, $days_of_week) ? '1' : '0';
            }
            $day_bitmap_int = bindec($day_bitmap_str);

            // TIME型用に整形
            $schedule_start = sprintf('%02d:%02d:00', (int)$start_h, (int)$start_m);
            $schedule_end = sprintf('%02d:%02d:00', (int)$end_h, (int)$end_m);

            // SQL UPDATE処理 (プリペアドステートメント)
            $sql_update = "UPDATE tb_schedule SET 
                schedule_name = ?, 
                schedule_place = ?, 
                schedule_day_of_week = ?, 
                schedule_start = ?, 
                schedule_end = ? 
                WHERE schedule_id = ? AND user_id = ?";

            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("ssissii", 
                    $schedule_name, 
                    $schedule_place, 
                    $day_bitmap_int, 
                    $schedule_start, 
                    $schedule_end, 
                    $schedule_id, 
                    $user_id
                );

                if ($stmt_update->execute()) {
                    $stmt_update->close();
                    echo '<script>alert("予定を編集しました。"); window.location.href = "' . $redirect_url . '";</script>';
                    exit;
                } else {
                    $error_message .= "・データベース更新エラー: " . $stmt_update->error . "<br>";
                    $stmt_update->close();
                }
            } else {
                $error_message .= "・SQL準備エラー (UPDATE): " . $conn->error . "<br>";
            }
        }
    }
}
// ----------------------------------------------------------------------
// ユーザー認証エラー時の表示
// ----------------------------------------------------------------------
if (!$is_authenticated && $schedule_id > 0) {
    die("エラー: 認証されていないユーザーID、または存在しない予定IDです。");
}
?>
<style>
    /* CSSは元のものを維持し、ポップアップ関連を追加 */
    .schedule-form-container {
        max-width: 600px;
        margin: 30px auto;
    }

    .required-asterisk {
        color: #dc3545;
        margin-right: 5px;
    }

    .time-separator {
        margin: 0 5px;
    }

    /* ボタンの縦幅を調整するカスタムスタイル */
    .btn-custom-height {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
        font-size: 1.1rem;
    }

    /* ポップアップ関連のスタイル */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: none; /* 初期は非表示 */
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal-content {
        background: white;
        padding: 30px 40px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        text-align: center;
        max-width: 400px;
        width: 90%;
    }
    
    .modal-content h4 {
        margin-top: 0;
        font-size: 1.3rem;
        font-weight: bold;
    }

    .modal-content p {
        margin-bottom: 20px;
        color: #dc3545; /* 警告色 */
        font-weight: bold;
    }

    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 20px;
    }
</style>

<div class="schedule-form-container p-4">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            入力エラーがあります:<br>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="?do=all_schedule-edit&schedule_id=<?php echo $schedule_id; ?>&back=<?php echo $back; ?>" id="editForm">
        <input type="hidden" name="action" value="edit">
        
        <div class="row mb-3 align-items-center">
            <div class="col-md-3 text-md-start">
                <label for="schedule_name" class="col-form-label">
                    <span class="required-asterisk">※</span> 予定名:
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control" id="schedule_name" name="schedule_name"
                    value="<?php echo htmlspecialchars($schedule_name); ?>" required
                    placeholder="予定名を入力">
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-md-3 text-md-start">
                <label class="col-form-label">
                    <span class="required-asterisk">※</span> 開催時刻:
                </label>
            </div>
            <div class="col-md-9 d-flex align-items-center">
                <input type="number" class="form-control" name="start_h" value="<?php echo htmlspecialchars(sprintf('%02d', (int)$start_h)); ?>" min="0" max="23" style="width: 70px;" required>
                <span class="time-separator">:</span>
                <input type="number" class="form-control" name="start_m" value="<?php echo htmlspecialchars(sprintf('%02d', (int)$start_m)); ?>" min="0" max="59" step="15" style="width: 70px;" required>
                <span class="time-separator">～</span>
                <input type="number" class="form-control" name="end_h" value="<?php echo htmlspecialchars(sprintf('%02d', (int)$end_h)); ?>" min="0" max="23" style="width: 70px;" required>
                <span class="time-separator">:</span>
                <input type="number" class="form-control" name="end_m" value="<?php echo htmlspecialchars(sprintf('%02d', (int)$end_m)); ?>" min="0" max="59" step="15" style="width: 70px;" required>
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-md-3 text-md-start">
                <label class="col-form-label">
                    <span class="required-asterisk">※</span> 曜日:
                </label>
            </div>
            <div class="col-md-9 d-flex flex-wrap align-items-center">
                <?php for ($i = 0; $i < 7; $i++): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="day_of_week[]" id="day_<?php echo $i; ?>" value="<?php echo $i; ?>"
                            <?php echo in_array($i, $days_of_week) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="day_<?php echo $i; ?>"><?php echo $day_labels[$i]; ?></label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="row mb-3 align-items-center">
            <div class="col-md-3 text-md-start">
                <label for="schedule_place" class="col-form-label">
                    <span class="required-asterisk">　</span> 場所:
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control" id="schedule_place" name="schedule_place"
                    value="<?php echo htmlspecialchars($schedule_place); ?>"
                    placeholder="予定の開催地を入力">
            </div>
        </div>

        <div class="text-center mt-4 pt-3 border-top d-flex justify-content-center gap-4">
            <?php
            // 戻るボタンの遷移先を決定
            $back_url = ($back == 0) ? "index.php?do=all_在席時間帯？" : "index.php?do=all_schedule";
            ?>
            <a href="<?php echo $back_url; ?>" class="btn btn-secondary btn-custom-height" style="background-color: #6c757d; border-color: #6c757d;">戻る</a>
            
            <button type="button" class="btn btn-danger btn-custom-height" id="deleteButton">削除</button>
            
            <button type="submit" class="btn btn-primary btn-custom-height" style="background-color: #007bff; border-color: #007bff;">編集</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
        <form method="POST" action="?do=all_schedule-edit&schedule_id=<?php echo $schedule_id; ?>&back=<?php echo $back; ?>" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <h4>予定名: <span id="modalScheduleName"><?php echo htmlspecialchars($schedule_name); ?></span></h4>
            <p>本当に削除しますか？</p>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary btn-custom-height" onclick="document.getElementById('deleteModal').style.display='none';" style="background-color: #6c757d; border-color: #6c757d;">戻る</button>
                <button type="submit" class="btn btn-danger btn-custom-height" style="background-color: #dc3545; border-color: #dc3545;">削除</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('deleteButton').addEventListener('click', function() {
        // ポップアップを表示する
        document.getElementById('deleteModal').style.display = 'flex';
        // 予定名をポップアップに反映（PHPで初期化済みだが念のため）
        document.getElementById('modalScheduleName').textContent = document.getElementById('schedule_name').value;
    });

    // モーダルオーバーレイをクリックで閉じる
    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target.id === 'deleteModal') {
            document.getElementById('deleteModal').style.display = 'none';
        }
    });
</script>