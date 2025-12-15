<?php

// ----------------------------------------
// 1. 初期設定とデータの取得 (変更なし)
// ----------------------------------------

// NOTE: db_inc.php のインクルードとセッション処理が事前に完了している前提
// global $conn; が関数内で使われるため、$conn はここで定義されている必要がある。

include('db_inc.php');

$target_user_id = $_GET['uid'] ?? $_SESSION['uid'];
//$target_user_id = 5;
// ターゲットユーザーの名前を取得
$sql_user_info = "SELECT name FROM tb_user WHERE user_id = " . $target_user_id;
$rs_user_info = $conn->query($sql_user_info);
if (!$rs_user_info) die('ユーザー情報取得エラー: ' . $conn->error);
$user_info = $rs_user_info->fetch_assoc();
$target_user_name = $user_info['name'] ?? 'ユーザー名不明';

// --- Undefined variable エラー解消のための定義 ---
$tz = new DateTimeZone('Asia/Tokyo');
$now = new DateTime('now', $tz);
$now_date_str = $now->format('Y-m-d');
// ------------------------------------------------


// ----------------------------------------
// 2. 在席確率計算関連関数 (ユーザー指定の関数ロジックをそのまま適用)
// ----------------------------------------

/**
 * 1時間間隔の時刻文字列を生成する (08:00:00 形式)
 */
function time_format($time)
{
    // 24時を跨ぐ時刻の比較はここでは行わないため、H:i:s形式を維持
    if ($time < 10) {
        $format_time = "0" . $time . ":00:00";
    } else {
        $format_time = $time . ":00:00";
    }
    return $format_time;
}

/**
 * 指定期間内、指定曜日の、指定時間帯における在席確率を計算する。
 * (ユーザー指定のロジックをそのまま使用)
 * * @param int $uid ユーザーID
 * @param int $week_number MySQLの曜日番号 (1=日, 7=土)
 * @param string $start_time 該当時間帯の開始時刻 (H:i:s)
 * @param string $end_time 該当時間帯の終了時刻 (H:i:s)
 * @return int 在席確率 (%)
 */
function present_probability($uid, $week_number, $start_time, $end_time)
{
    $tz = new DateTimeZone('Asia/Tokyo');
    $now = new DateTime('now', $tz);
    $now_date_str = $now->format('Y-m-d');

    global $conn;

    $sql_period = "SELECT* FROM tb_calculation_period WHERE cast('" . $now_date_str . "' as DATE) >= period_start AND cast('" . $now_date_str . "' as DATE) <= period_end";
    $rs_period = $conn->query($sql_period);
    $errorMessage1 = "";
    if (!$rs_period) die('エラー: ' . $conn->error);

    $row_period = $rs_period->fetch_assoc();

    if (!$row_period) return 0;

    $period_start = $row_period['period_start'];
    $period_end = $row_period['period_end'];

    $sql_present = "SELECT count(DISTINCT DATE(lease_start_date)) as count_present
    FROM tb_leases
    WHERE MACaddress IN(
    SELECT MACaddress
    FROM tb_device
    NATURAL JOIN tb_MACaddress
    WHERE user_id=" . $uid . "
    )
    AND DAYOFWEEK(lease_start_date) = " . $week_number . "
    AND (cast(lease_end_date as DATE) >= '" . $period_start . "' AND cast(lease_start_date as DATE) <= '" . $period_end . "')
    AND (cast(lease_end_date as TIME) >= '" . $start_time . "' AND cast(lease_start_date as TIME) < '" . $end_time . "')
    ";
    $rs_present = $conn->query($sql_present);
    $errorMessage2 = "";
    if (!$rs_present) die('エラー: ' . $conn->error);
    $row_present = $rs_present->fetch_assoc();

    $period_start_DateTime = new DateTime($period_start);
    $interval = $period_start_DateTime->diff($now);
    $days = $interval->days;

    $day_of_week_start = (int)$period_start_DateTime->format('w');
    $day_of_week_now = (int)$now->format('w');
    $week_count = 0;

    $days = $days - (7 - $day_of_week_start);
    if ($day_of_week_start <= $week_number - 1) {
        $week_count++;
    }

    $week_count += ((int)($days / 7));
    if ($days % 7 >= $week_number - 1) {
        $week_count++;
    }

    $st = new DateTime($start_time, $tz);
    if (($week_number - 1) == $day_of_week_now && $st > $now) {
        $week_count--;
    }

    if ($week_count <= 0) return 0;

    $probability = min((int)(($row_present['count_present'] * 100) / $week_count), 100);
    return $probability;
    //return $week_count;
}

// ----------------------------------------
// 3. 予定データの取得と整形 (変更なし)
// ----------------------------------------

$sql_schedule = "SELECT * FROM tb_schedule WHERE user_id = " . $target_user_id;
$rs_schedule = $conn->query($sql_schedule);
if (!$rs_schedule) die('エラー: ' . $conn->error);

$schedules = $rs_schedule->fetch_all(MYSQLI_ASSOC);

// 曜日をCSSクラスにマッピング
$day_map_css = [
    0 => 'sun',
    1 => 'mon',
    2 => 'tue',
    3 => 'wed',
    4 => 'thu',
    5 => 'fri',
    6 => 'sat'
];
$day_labels_jp = ['日', '月', '火', '水', '木', '金', '土'];
$days_of_week_jp = $day_labels_jp;

/**
 * 予定データを曜日ごとに分割・整形する (変更なし)
 */
function parseSchedules(array $schedules): array
{
    $parsed = [];
    $day_labels_jp = ['日', '月', '火', '水', '木', '金', '土'];

    foreach ($schedules as $schedule) {
        $bitmap = $schedule['schedule_day_of_week'];

        $start_time = strtotime($schedule['schedule_start']);
        $end_time = strtotime($schedule['schedule_end']);

        // 曜日ラベルを事前に作成
        $active_day_labels = [];
        for ($i = 0; $i < 7; $i++) {
            if ($bitmap & (1 << (6 - $i))) {
                $active_day_labels[] = $day_labels_jp[$i];
            }
        }
        $day_labels_string = implode('、', $active_day_labels);

        // 各曜日セルに配置するためのループ
        for ($i = 0; $i < 7; $i++) {
            // ビットが立っている曜日のみ処理
            if ($bitmap & (1 << (6 - $i))) {
                $day_key_index = $i; // 0:日, 1:月, ..., 6:土
                $day_css_class = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'][$day_key_index];

                $schedule_data = [
                    'id' => $schedule['schedule_id'],
                    'name' => htmlspecialchars($schedule['schedule_name']),
                    'place' => htmlspecialchars($schedule['schedule_place'] ?? '未登録'),
                    'start' => date('H:i', $start_time),
                    'end' => date('H:i', $end_time),
                    'start_h' => (int)date('H', $start_time),
                    'start_m' => (int)date('i', $start_time),
                    'end_h' => (int)date('H', $end_time),
                    'end_m' => (int)date('i', $end_time),
                    'day_labels' => $day_labels_string, // ポップアップ用にすべての曜日ラベルを格納
                    'day_index' => $day_key_index, // 描画位置特定用
                ];

                if (!isset($parsed[$day_css_class])) {
                    $parsed[$day_css_class] = [];
                }
                $parsed[$day_css_class][] = $schedule_data;
            }
        }
    }
    return $parsed;
}

$parsed_schedules = parseSchedules($schedules);
$schedules_json = json_encode($parsed_schedules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// 列幅計算
$day_count = count($day_labels_jp);
$day_column_width_percent = 100 / $day_count;

// 集計期間の表示用データ取得 (グローバル変数 $now_date_str を使用)
$sql_period_display = "SELECT * FROM tb_calculation_period WHERE cast('" . $now_date_str . "' as DATE) >= period_start AND cast('" . $now_date_str . "' as DATE) <= period_end";
$rs_period_display = $conn->query($sql_period_display);
$period_row = $rs_period_display->fetch_assoc();

$period_display_text = '集計期間未設定';
if ($period_row) {
    $period_name = $period_row['period_name'];
    $start_date = new DateTime($period_row['period_start']);
    $end_date = new DateTime($period_row['period_end']);
    $period_display_text = $period_name."　集計期間：".$start_date->format('Y年n月j日') . ' ~ ' . $end_date->format('Y年n月j日');
}
?>

<style>
    /* ---------------------------------------- */
    /* CSS スタイル (枠線と固定要素の背景を修正) */
    /* ---------------------------------------- */
    .main-content-wrapper {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
        background-color: white;
    }

    .user-info-bar {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        margin-bottom: 20px;
    }

    /* --- スクロール設定 --- */
    .table-container {
        /* 縦スクロールを有効にするための最大高さを設定 */
        max-height: 70vh;
        /* 画面の高さの70%に設定 (適宜調整してください) */
        overflow-y: auto;
        /* 縦スクロールを有効に */
        /* 横スクロールを有効にするための設定 */
        overflow-x: auto;
        /* テーブルをコンテナの幅以上に広げられるようにする */
        width: 100%;
    }

    /* --- テーブルスタイルの統合と調整 --- */
    .schedule-table {
        /* テーブルの幅を固定せず、コンテンツに合わせて広がるように設定（横スクロール用） */
        width: 100%;
        min-width: 800px;
        /* スマホで横スクロールさせるための最低幅 */
        border-collapse: collapse;
        /* table-layout: fixed; を削除し、横スクロールに対応 */
    }

    /* ヘッダーのセル (曜日ヘッダー) */
    .schedule-table th {
        border: 1px solid #ddd;
        padding: 5px 0;
        /* ヘッダーのパディングを少し減らす */
        text-align: center;
        font-weight: bold;
        background-color: #f8f8f8;
        /* 背景色を明示的に設定 */
        position: sticky;
        /* 横スクロールしても曜日ヘッダーを固定 */
        top: 0;
        z-index: 20;
    }

    /* 時間ラベル列の幅 (td.time-label と th.time-label の両方を対象) */
    .schedule-table .time-label {
        width: 70px;
        /* 修正1: 背景色を曜日ヘッダーと同じ色に統一 */
        background-color: #f8f8f8;

        /* 修正2: 垂直方向の中央揃えを実現 */
        vertical-align: middle;

        /* 修正3: 左右の余白 (パディング) を追加 */
        padding: 0 5px;

        text-align: center;
        font-weight: bold;
        border-right: 1px solid #ddd;
        /* 縦横スクロール時に時間ラベルを固定 */
        position: sticky;
        left: 0;
        z-index: 20;
    }

    /* 曜日ヘッダーの時間ラベル部分 (角のセル) */
    .schedule-table thead th.time-label {
        z-index: 30;
        /* 角のセルのz-indexを最も高く */
        /* 背景色を親要素と統一 */
        background-color: #f8f8f8;

        /* ヘッダーthのパディング(5px 0)を上書きし、均等なパディングに調整 */
        padding: 5px;
    }


    .day-header.sun {
        color: red;
    }

    .day-header.sat {
        color: blue;
    }

    /* 本体のセル */
    .schedule-table td {
        border: 1px solid #ddd;
        /* 1セル（1時間）の高さを縮小 */
        height: 40px;
        padding: 0;
        text-align: center;
        position: relative;
        vertical-align: top;
        background-color: white;
    }

    /* 確率テキスト (セルの右側30%に配置) */
    .probability-text {
        width: 30%;
        /* 高さを縮小に合わせて調整 */
        height: 100%;
        z-index: 1;
        position: absolute;
        top: 0;
        right: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
        /* フォントサイズを少し小さく */
        border-left: 1px dashed #ccc;
        /* 背景色はヒートマップに任せ、セルの背景(td)が白色で不透明であることを確認 */
    }

    /* 予定ブロック (セルの左側70%に配置) */
    .schedule-block {
        background-color: #28a745;
        color: white;
        border-radius: 2px;
        padding: 0 2px;
        /* パディングを調整 */
        cursor: pointer;
        box-sizing: border-box;
        position: absolute;
        width: calc(70% - 2px);
        left: 1px;
        overflow: hidden;
        text-align: left;
        font-size: 10px;
        /* フォントサイズを小さく */
        line-height: 1.0;
        /* 行の高さを詰める */
        z-index: 10;
        word-break: break-all;
        user-select: none;
    }

    .schedule-block strong,
    .schedule-block span {
        display: block;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
        padding: 0;
        line-height: 1.0;
    }

    /* ポップアップのスタイル (変更なし) */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        display: none;
    }

    .modal-content {
        background: white;
        padding: 30px 20px 20px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 450px;
        margin: auto;
        position: relative;
        border: 1px solid #ccc;
    }

    .modal-content h3 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .btn-close {
        position: absolute;
        /* ★修正: 右上隅へ */
        top: 10px;
        /* 上端から 10px */
        right: 10px;
        /* 右端から 10px */
        bottom: auto;
        /* bottomの設定を解除 */
        left: auto;
        /* leftの設定を解除 */
        width: 30px;
        height: 30px;
        background-color: transparent;
        border: none;
        cursor: pointer;
        outline: none;
        box-shadow: none;
        z-index: 10;
    }

    .btn-close::before,
    .btn-close::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 2px;
        height: 24px;
        background-color: #333;
        transform-origin: center;
    }

    .btn-close::before {
        transform: translate(-50%, -50%) rotate(45deg);
    }

    .btn-close::after {
        transform: translate(-50%, -50%) rotate(-45deg);
    }

    .btn-close:hover::before,
    .btn-close:hover::after {
        background-color: #555;
    }


    .btn-edit-delete {
        background-color: #007bff;
        color: white;
    }

    .collection-period{
        text-align: left;
        margin-bottom: 0px;
        margin-right: 5%;
        font-size: clamp(10px, 0.8vw, 18px);
    }
</style>

<div class="main-content-wrapper">
    <div class="user-info-bar">
        <?php
        echo '<form method="get" class="form login-form" style="display: flex; align-items: center; margin-bottom: 10px;">';
        echo '<input type="hidden" name="do" value="all_time_of_attendance2" />';
        echo '<select class="form-select" style="width:25%; font-weight:bold;" name="uid">';

        $sql_user = "SELECT * FROM tb_user ORDER BY student_number";
        $rs_user = $conn->query($sql_user);
        $errorMessage = "";

        if (!$rs_user) die('エラー: ' . $conn->error);
        while ($row = $rs_user->fetch_assoc()) {
            $choice_uid = $row['user_id'];
            $choice_name = $row['name'];
            $choice_student_number = $row['student_number'];
            if (strcmp($choice_student_number, "guest") == 0) {
                continue;
            }
            if ($target_user_id == $choice_uid) {
                echo '<option value="' . $choice_uid . '" selected>' . $choice_name . '</option>';
            } else {
                echo '<option value="' . $choice_uid . '">' . $choice_name . '</option>';
            }
        }
        echo '</select>';
        echo '<button class="btn btn-primary">選択</button>';
        echo "</form>";
        ?>

        <div class="collection-period">
            <?= htmlspecialchars($period_display_text) ?>
        </div>

        <div style="flex-grow: 1; text-align: right;">
            <?php
            if ($_SESSION['uid'] == $target_user_id) {
                //backは他の画面の戻るボタンでこの画面に戻るため0→在席時間帯画面、1→予定一覧画面
                echo '<a href="?do=all_schedule-add&back=0" class="btn btn-primary">予定追加</a>';
            }
            ?>
        </div>
    </div>

    <div class="table-container">
        <table class="schedule-table">
            <colgroup>
                <col class="time-label-col" style="width: 70px;">
                <?php for ($i = 0; $i < $day_count; $i++): ?>
                    <col style="width: <?= $day_column_width_percent ?>%;">
                <?php endfor; ?>
            </colgroup>
            <thead>
                <tr>
                    <th class="time-label"></th>
                    <?php foreach ($day_labels_jp as $day_key => $day_label): ?>
                        <?php
                        $class = '';
                        if ($day_label === '日') $class = 'sun';
                        if ($day_label === '土') $class = 'sat';
                        ?>
                        <th class="day-header <?= $class ?>"><?= $day_label ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // 0時から23時までの表示 (1時間ごと)
                $start_hour = 0;
                $end_hour = 24;

                for ($h = $start_hour; $h < $end_hour; $h++):
                    $current_time = time_format($h);
                    $next_time = time_format($h + 1);
                    // 時刻表示形式を "00:00" 形式に変更
                    $display_time = sprintf('%02d:00', $h);
                ?>
                    <tr data-hour="<?= $h ?>">
                        <td class="time-label">
                            <?= $display_time ?>
                        </td>

                        <?php for ($j = 1; $j <= 7; $j++): ?>
                            <?php
                            // $jはMySQLのDAYOFWEEK (1=日, 7=土)
                            $day_css_class = $day_map_css[$j - 1];

                            // 確率を計算
                            $prob = present_probability($target_user_id, $j, $current_time, $next_time);

                            // ヒートマップの色計算
                            $red_green = (int)(255 - ($prob * 255 / 100));
                            $color = ($prob >= 60) ? "white" : "black";
                            $bg_color_style = "background-color: rgb(" . $red_green . ", " . $red_green . ", 255);";
                            ?>
                            <td class="day-cell <?= $day_css_class ?>" data-hour="<?= $h ?>">
                                <div class="probability-text" style="color: <?= $color ?>; <?= $bg_color_style ?>">
                                    <?= $prob ?>%
                                </div>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay">
    <div class="modal-content">
        <div class="btn-close" aria-label="閉じる"></div>

        <h3>予定詳細</h3>
        <p><strong>予定名:</strong> <span id="modal-schedule-name"></span></p>
        <p><strong>曜日:</strong> <span id="modal-schedule-days"></span></p>
        <p><strong>開催時刻:</strong> <span id="modal-schedule-time"></span></p>
        <p><strong>場所:</strong> <span id="modal-schedule-place"></span></p>

        <div class="modal-actions">
            <?php
            // ログインユーザーと予定の所有者が同じ場合のみ「編集・削除」ボタンを表示
            if ($_SESSION['uid'] == $target_user_id) {
                echo '<a id="btn-edit-delete" href="#" class="btn btn-primary">編集・削除</a>';
            }
            ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(function() {
        const schedules = <?= $schedules_json ?>;

        // 1セル（1時間）の高さ (40pxに更新)
        const CELL_HEIGHT_PX = 40;
        // 表示開始時間 (0時に維持)
        const START_HOUR = 0;

        // スケジュール/確率の分割比率 (維持)
        const SCHEDULE_WIDTH = 70; // %
        const PROBABILITY_WIDTH = 30; // %

        /**
         * 衝突を検出・解決し、各予定の位置情報 (幅と左オフセット) を計算する関数 (ロジック変更なし)
         */
        function calculateSchedulePositions(daySchedules) {
            if (!daySchedules || daySchedules.length === 0) return [];
            daySchedules.sort((a, b) => (a.start_h * 60 + a.start_m) - (b.start_h * 60 + b.start_m));
            const positionedSchedules = [];
            const groups = [];

            daySchedules.forEach(schedule => {
                const start = schedule.start_h * 60 + schedule.start_m;
                const end = schedule.end_h * 60 + schedule.end_m;
                let conflictGroups = groups.filter(group => group.some(existing => {
                    const existingStart = existing.start_h * 60 + existing.start_m;
                    const existingEnd = existing.end_h * 60 + existing.end_m;
                    return (start < existingEnd) && (end > existingStart);
                }));

                if (conflictGroups.length === 0) {
                    groups.push([schedule]);
                } else {
                    conflictGroups[0].push(schedule);
                }
            });

            groups.forEach(group => {
                const columns = [];
                group.forEach(schedule => {
                    const start = schedule.start_h * 60 + schedule.start_m;
                    const end = schedule.end_h * 60 + schedule.end_m;
                    let columnIndex = -1;

                    for (let i = 0; i < columns.length; i++) {
                        let canPlace = true;
                        for (let existingSchedule of columns[i]) {
                            const existingStart = existingSchedule.start_h * 60 + existingSchedule.start_m;
                            const existingEnd = existingSchedule.end_h * 60 + existingSchedule.end_m;
                            if ((start < existingEnd) && (end > existingStart)) {
                                canPlace = false;
                                break;
                            }
                        }
                        if (canPlace) {
                            columnIndex = i;
                            break;
                        }
                    }

                    if (columnIndex === -1) {
                        columnIndex = columns.length;
                        columns.push([]);
                    }
                    columns[columnIndex].push(schedule);
                    schedule.columnIndex = columnIndex;
                });

                const maxColumns = columns.length;
                group.forEach(schedule => {
                    schedule.maxColumns = maxColumns;
                    positionedSchedules.push(schedule);
                });
            });

            return positionedSchedules;
        }


        /**
         * 予定ブロックを生成し、適切な位置とサイズに配置する (CELL_HEIGHT_PXの変更に対応)
         */
        function renderSchedules() {
            $('.schedule-block').remove();

            const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

            days.forEach(day => {
                const daySchedules = schedules[day];
                if (!daySchedules || daySchedules.length === 0) return;

                const positionedSchedules = calculateSchedulePositions(daySchedules);

                // 各日の最初のセルを取得 (0:00のセルを指す)
                const $dayColumnCell = $(`.day-cell.${day}[data-hour="${START_HOUR}"]`);

                if ($dayColumnCell.length === 0) return;


                positionedSchedules.forEach(schedule => {
                    // 0:00からの経過分数
                    const startTotalMinutes = (schedule.start_h * 60 + schedule.start_m) - (START_HOUR * 60);
                    const durationMinutes = (schedule.end_h * 60 + schedule.end_m) - (schedule.start_h * 60 + schedule.start_m);

                    // 1分あたりに必要なピクセル数 (40px/60分)
                    const pixelsPerMinute = CELL_HEIGHT_PX / 60;

                    // ピクセル単位での位置と高さを計算
                    const topOffset = startTotalMinutes * pixelsPerMinute;
                    const height = durationMinutes * pixelsPerMinute;

                    const maxColumnsInGroup = schedule.maxColumns || 1;
                    const column_index = schedule.columnIndex || 0;

                    // スケジュール表示エリア (70%幅) の中で幅と位置を計算
                    const column_width_percentage = SCHEDULE_WIDTH / maxColumnsInGroup;
                    const column_left_percentage = column_index * column_width_percentage;

                    // 衝突解決後の描画スタイル
                    const width = `calc(${column_width_percentage}% - 2px)`;
                    const left = `calc(${column_left_percentage}% + 1px)`;


                    const $block = $(`
                    <div class="schedule-block" 
                    data-id="${schedule.id}"
                    data-name="${schedule.name}"
                    data-place="${schedule.place}"
                    data-start="${schedule.start}"
                    data-end="${schedule.end}"
                    data-day-labels="${schedule.day_labels}" 
                    data-day-index="${schedule.day_index}" 
                    style="top: ${topOffset}px; height: ${height}px; width: ${width}; left: ${left};">
                    <strong>${schedule.name}</strong>
                    <span>${schedule.start} - ${schedule.end}</span>
                    </div>
                    `);

                    $dayColumnCell.append($block);
                });
            });
        }

        // ページロード時に予定を描画
        renderSchedules();

        // 予定ブロッククリック時のポップアップ表示
        $(document).on('click', '.schedule-block', function() {
            const $block = $(this);
            const scheduleId = $block.data('id');
            const name = $block.data('name');
            const placeString = $block.data('place');
            const start = $block.data('start');
            const end = $block.data('end');
            const dayLabels = $block.data('day-labels');

            // ★修正1: dayLabelsの後に「曜日」を追加する
            $('#modal-schedule-days').text(dayLabels).append('曜日');

            // ★修正2: placeが空または空白文字列の場合に「未登録」を表示する
            const displayPlace = (placeString && placeString.trim() !== '') ? placeString : '未登録';

            // 情報をモーダルにセット
            $('#modal-schedule-name').text(name);
            $('#modal-schedule-time').text(`${start} - ${end}`);
            $('#modal-schedule-place').text(displayPlace); // 修正された値をセット

            // 「編集・削除」ボタンのリンクを設定
            $('#btn-edit-delete').attr('href', `index.php?do=all_schedule-edit&schedule_id=${scheduleId}&back=0`);

            // モーダルを表示
            $('.modal-overlay').fadeIn(200);
        });

        // 閉じるボタンとオーバーレイクリックでポップアップを閉じる
        $('.btn-close, .modal-overlay').on('click', function(e) {
            if ($(e.target).hasClass('modal-overlay') || $(e.target).closest('.btn-close').length) {
                $('.modal-overlay').fadeOut(200);
            }
        });
        $('.modal-content').on('click', function(e) {
            e.stopPropagation();
        });
    });
</script>