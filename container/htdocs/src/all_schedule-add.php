<?php
// ----------------------------------------------------------------------
// データベース接続、変数初期化、フォーム送信処理
// ----------------------------------------------------------------------
include('db_inc.php');

// 初期値の設定
$schedule_name = '';
$start_h = '00';
$start_m = '00';
$end_h = '00';
$end_m = '00';
$days_of_week = []; // 選択された曜日（0:日, 1:月, ..., 6:土）
$schedule_place = '';
$error_message = '';
// ユーザーIDはセッションから取得。セッションが開始されていることが前提です。
$user_id = $_SESSION['uid'] ?? 0; // デフォルト値を0として、セッションがない場合のエラーを避ける
$back = $_GET['back'] ?? 1;

$day_labels = ['日', '月', '火', '水', '木', '金', '土'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. POSTデータの取得
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
    // 整数型にして比較することで、時刻の大小を判断します。
    $start_time_val = (int)$start_h * 60 + (int)$start_m;
    $end_time_val = (int)$end_h * 60 + (int)$end_m;

    if ($start_time_val >= $end_time_val) {
        $error_message .= "・開催時刻が無効です。終了時刻は開始時刻よりも後に設定してください。<br>";
    }

    // エラーがなければ登録処理へ
    if (empty($error_message)) {
        // 曜日のビットマップ変換と時刻の形式変換
        $day_bitmap_str = '';
        for ($i = 0; $i < 7; $i++) {
            // $i は 0:日, 1:月, ...
            $day_bitmap_str .= in_array($i, $days_of_week) ? '1' : '0';
        }
        $day_bitmap_int = bindec($day_bitmap_str);

        // TIME型用に整形
        $schedule_start = sprintf('%02d:%02d:00', (int)$start_h, (int)$start_m);
        $schedule_end = sprintf('%02d:%02d:00', (int)$end_h, (int)$end_m);

        // SQL INSERT処理 (プリペアドステートメント)
        $sql = "INSERT INTO tb_schedule 
(schedule_name, schedule_place, schedule_day_of_week, schedule_start, schedule_end, user_id) 
VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssissi", $schedule_name, $schedule_place, $day_bitmap_int, $schedule_start, $schedule_end, $user_id);

            if ($stmt->execute()) {
                $stmt->close();
                // 登録成功後の遷移
                if ($back == 0) {
                    echo '<script>window.location.href = "index.php?do=all_time_of_attendance2&uid='.$user_id.'";</script>';
                } else {
                    echo '<script>window.location.href = "index.php?do=all_schedule";</script>';
                }
                exit;
            } else {
                $error_message .= "・データベース登録エラー: " . $stmt->error . "<br>";
                $stmt->close();
            }
        } else {
            $error_message .= "・SQL準備エラー: " . $conn->error . "<br>";
        }
    }
}
?>
<style>
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
</style>
<div class="schedule-form-container p-4">

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            入力エラーがあります:<br>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="row mb-3 align-items-center">
            <div class="col-md-3 text-md-start">
                <label for="schedule_name" class="col-form-label">
                    <span class="required-asterisk">※</span> 予定名:
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control" id="schedule_name" name="schedule_name" maxlength="32"
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
                <input type="number" class="form-control" name="start_h" value="<?php echo htmlspecialchars($start_h); ?>" min="0" max="23" style="width: 70px;" required>
                <span class="time-separator">:</span>
                <input type="number" class="form-control" name="start_m" value="<?php echo htmlspecialchars($start_m); ?>" min="0" max="59" style="width: 70px;" required>
                <span class="time-separator">～</span>
                <input type="number" class="form-control" name="end_h" value="<?php echo htmlspecialchars($end_h); ?>" min="0" max="23" style="width: 70px;" required>
                <span class="time-separator">:</span>
                <input type="number" class="form-control" name="end_m" value="<?php echo htmlspecialchars($end_m); ?>" min="0" max="59" style="width: 70px;" required>
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
                    <span class="required-asterisk">　</span> 開催地:
                </label>
            </div>
            <div class="col-md-9">
                <input type="text" class="form-control" id="schedule_place" name="schedule_place" maxlength="32"
                    value="<?php echo htmlspecialchars($schedule_place); ?>"
                    placeholder="予定の開催地を入力">
            </div>
        </div>

        <div class="text-center mt-4 pt-3 border-top d-flex justify-content-center gap-4">
            <?php
            
            if ($back == 0) {
                echo '<a href="?do=all_time_of_attendance2&uid='.$user_id.'" class="btn btn-secondary btn-custom-height">戻る</a>';
            } else {
                echo '<a href="?do=all_schedule" class="btn btn-secondary btn-custom-height">戻る</a>';
            }
            ?>
            <button type="submit" class="btn btn-primary btn-custom-height">登録</button>
        </div>
    </form>
</div>