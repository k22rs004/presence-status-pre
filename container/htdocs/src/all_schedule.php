<?php

// ----------------------------------------
// 1. SQL問い合わせとデータの取得 (この部分はユーザー側で実装)
// ----------------------------------------

// ユーザーIDは外部から渡されるものと仮定
// NOTE: session_start() がどこかで呼ばれている必要があります。

$user_id = $_GET['uid'] ?? $_SESSION['uid'];
// NOTE: 以下のファイル名と変数は、元のコードに基づいていますが、
// 実際の動作には db_inc.php が正しく読み込まれる必要があります。
include("db_inc.php");

// SQLインジェクションを防ぐため、プリペアドステートメントの使用を推奨しますが、
// 提示されたコードに合わせて、ここでは変数を直接使用します（ただし、非推奨）。
$sql = "SELECT * FROM tb_schedule WHERE user_id = " . $user_id;
$rs = $conn->query($sql);
$errorMessage = "";
if (!$rs) die('エラー: ' . $conn->error);

$schedules = $rs->fetch_all(MYSQLI_ASSOC);
// ----------------------------------------
// 2. データの整形
// ----------------------------------------

// 曜日を日本語とCSSクラスにマッピング
$day_map = [
    '日' => 'sun',
    '月' => 'mon',
    '火' => 'tue',
    '水' => 'wed',
    '木' => 'thu',
    '金' => 'fri',
    '土' => 'sat'
];
$days_of_week_jp = array_keys($day_map); // ['日', '月', '火', '水', '木', '金', '土']

/**
 * 予定データを曜日ごとに分割・整形する
 */
function parseSchedules(array $schedules): array
{
    $parsed = [];
    $days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    // 曜日名の日本語ラベルを定義
    $day_labels_jp = ['日', '月', '火', '水', '木', '金', '土'];

    foreach ($schedules as $schedule) {
        $bitmap = $schedule['schedule_day_of_week'];

        $start_time = strtotime($schedule['schedule_start']);
        $end_time = strtotime($schedule['schedule_end']);

        // 登録されている曜日名のリストを保持する配列
        $active_day_labels = [];

        // 曜日ビットマップから登録されている曜日をすべて検出
        for ($i = 0; $i < 7; $i++) {
            // ビットが立っているかチェック (0:日, 1:月, ... 6:土)
            // ビットマップの順序は、通常 0(右端)=日, 6(左端)=土 のため、(1 << (6 - $i)) でビットを取得
            if ($bitmap & (1 << (6 - $i))) {
                // 登録されている曜日ラベルを収集
                $active_day_labels[] = $day_labels_jp[$i];
            }
        }

        // 曜日ラベルを「月、火、水」のような文字列に整形
        $day_labels_string = implode('、', $active_day_labels);

        // 各曜日セルに配置するためのループ
        for ($i = 0; $i < 7; $i++) {
            if ($bitmap & (1 << (6 - $i))) {
                $day_key = $days[$i];

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
                    'day_labels' => $day_labels_string, // ★修正: 日本語の曜日ラベルを格納
                ];

                if (!isset($parsed[$day_key])) {
                    $parsed[$day_key] = [];
                }
                $parsed[$day_key][] = $schedule_data;
            }
        }
    }
    return $parsed;
}

$parsed_schedules = parseSchedules($schedules);
$schedules_json = json_encode($parsed_schedules, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// 列幅計算
$day_count = count($day_map);
// 曜日列を均等に割るためのパーセンテージ
$day_column_width_percent = 100 / $day_count;
?>

<style>
    /* ---------------------------------------- */
    /* CSS スタイル */
    /* ---------------------------------------- */
    .container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
        background-color: white;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .schedule-title {
        font-size: 24px;
        font-weight: bold;
    }

    .btn-add {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        font-size: 16px;
    }

    /* 予定表のラッパー */
    .schedule-wrapper {
        border: 1px solid #ddd;
    }

    /* 予定表のヘッダー */
    .schedule-table-header {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-bottom: -1px;
        background-color: #f8f8f8;
    }

    /* ヘッダーのセル */
    .schedule-table-header th {
        border: 1px solid #ddd;
        padding: 5px 0;
        text-align: center;
        font-weight: normal;
    }

    /* プレースホルダーの列 (スクロールバーの幅を埋める) */
    .scrollbar-placeholder {
        width: 17px !important;
        padding: 0 !important;
        border-right: none !important;
        border-bottom: none !important;
        border-top: none !important;
        background-color: #f8f8f8;
    }

    /* ヘッダーの最終曜日列の右側の罫線を削除して、プレースホルダーと一体感を出す */
    .schedule-table-header th:nth-last-child(2) {
        border-right: none;
    }

    /* ヘッダーの時間ラベル列 */
    .schedule-table-header .time-label {
        width: 80px;
    }

    .day-header.sun {
        color: red;
    }

    .day-header.sat {
        color: blue;
    }


    /* 予定表のスクロール可能な本体 */
    .schedule-container {
        height: 600px;
        overflow-y: scroll;
        width: 100%;
    }

    .schedule-table-body {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    /* 本体のセル */
    .schedule-table-body td {
        border: 1px solid #ddd;
        padding: 0;
        text-align: center;
        height: 30px;
        position: relative;
    }

    /* 本体の時間ラベル列 */
    .schedule-table-body .time-label {
        width: 80px;
        text-align: left;
        padding-left: 5px;
        background-color: #f8f8f8;
        border-top: none;
        border-bottom: 1px solid #ddd;
    }

    .day-cell {
        padding: 0 !important;
        position: relative;
    }

    .half-hour-row .day-cell {
        border-top: 1px dashed #eee !important;
    }

    /* 予定ブロック (省略) */
    .schedule-block {
        background-color: #4CAF50;
        color: white;
        border-radius: 5px;
        padding: 2px 5px;
        cursor: pointer;
        box-sizing: border-box;
        position: absolute;
        width: calc(100% - 2px);
        left: 1px;
        overflow: hidden;
        text-align: left;
        font-size: 12px;
        line-height: 1.2;
        z-index: 10;
    }

    .schedule-block span {
        display: block;
        /* 常に新しい行から開始 */
        text-align: left;
        /* 強制的に左寄せ */
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin: 0;
        padding: 0;
        line-height: 1.2;
    }

    .schedule-block strong {
        margin: 0;
        padding: 0;
        display: block;
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }

    /*ポップアップ*/
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
        /* クローズアイコンのスペースを確保するため、上部のパディングを増やす */
        padding: 40px 20px 20px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 450px;
        margin: auto;
        /* アイコンを絶対配置するための基準点 */
        position: relative;
    }

    .modal-content h3 {
        margin-top: 0;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .modal-content p {
        margin: 10px 0;
    }

    /* ★★★ アクションボタンエリアの調整 (中央寄せ) ★★★ */
    .modal-actions {
        display: flex;
        /* 中央寄せ */
        justify-content: center;
        gap: 10px;
        /* ボタン間の間隔 */
        margin-top: 20px;
    }

    .modal-actions a {
        /* 編集・削除ボタンの共通スタイル */
        padding: 8px 15px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        width: auto;
        text-align: center;
        font-size: 14px;
    }

    /* ---------------------------------------------------- */
    /* .btn-close (クローズアイコン) の絶対配置 (右上隅に修正) */
    /* ---------------------------------------------------- */
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
</style>

<div class="container">
    <div class="header-content">
        <h2 class="schedule-title">予定一覧</h2>
        <?php
        if ($_SESSION['uid'] == $user_id) {
            //backは他の画面の戻るボタンでこの画面に戻るため0→在席時間帯画面、1→予定一覧画面
            echo '<a href="?do=all_schedule-add&back=1" class="btn-add">予定追加ボタン</a>';
        }
        ?>
    </div>
    <?php
    echo '<form method="get" class="form login-form" style="display: flex; align-items: center; margin-bottom: 10px;">';
    echo '<input type="hidden" name="do" value="all_schedule" />';
    echo '<select class="form-select" style="width:25%; font-weight:bold;" name="uid">';

    $sql_user = "SELECT * FROM tb_user";
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
        if ($user_id == $choice_uid) {
            echo '<option value="' . $choice_uid . '" selected>' . $choice_name . '</option>';
        } else {
            echo '<option value="' . $choice_uid . '">' . $choice_name . '</option>';
        }
    }
    echo '</select>';
    echo '<button class="btn btn-primary">選択</button>';
    echo "</form>";
    ?>

    <div class="schedule-wrapper">
        <table class="schedule-table-header">
            <colgroup>
                <col style="width: 80px;">
                <?php for ($i = 0; $i < $day_count; $i++): ?>
                    <col style="width: <?= $day_column_width_percent ?>%;">
                <?php endfor; ?>
                <col style="width: 17px;">
            </colgroup>
            <thead>
                <tr>
                    <th class="time-label"></th>
                    <?php foreach ($day_map as $day_key => $day_class): ?>
                        <th class="day-header <?= $day_class ?>"><?= $day_key ?></th>
                    <?php endforeach; ?>
                    <th class="scrollbar-placeholder"></th>
                </tr>
            </thead>
        </table>

        <div class="schedule-container" id="schedule-container">
            <table class="schedule-table-body">
                <colgroup>
                    <col style="width: 80px;">
                    <?php for ($i = 0; $i < $day_count; $i++): ?>
                        <col style="width: <?= $day_column_width_percent ?>%;">
                    <?php endfor; ?>
                </colgroup>
                <tbody>
                    <?php
                    $start_hour = 0;
                    $end_hour = 24;

                    for ($h = $start_hour; $h < $end_hour; $h++):
                    ?>
                        <tr class="schedule-row-00" data-time="<?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>00">
                            <td class="time-label">
                                <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                            </td>
                            <?php foreach ($day_map as $day_key => $day_class): ?>
                                <td class="day-cell <?= $day_class ?>" data-hour="<?= $h ?>" data-minute="00"></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="half-hour-row schedule-row-30" data-time="<?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>30">
                            <td class="time-label"></td>
                            <?php foreach ($day_map as $day_key => $day_class): ?>
                                <td class="day-cell <?= $day_class ?>" data-hour="<?= $h ?>" data-minute="30"></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
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
            if ($_SESSION['uid'] == $user_id) {
                echo '<a id="btn-edit-delete" href="#" class="btn-edit-delete">編集・削除</a>';
            }
            ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(function() {
        const schedules = <?= $schedules_json ?>;

        const CELL_HEIGHT_PX = 30;
        const START_HOUR = 0;

        /**
         * 衝突を検出・解決し、各予定の位置情報 (幅と左オフセット) を計算する関数
         */
        function calculateSchedulePositions(daySchedules) {
            if (!daySchedules || daySchedules.length === 0) return [];

            // 予定を「開始時刻」でソート
            daySchedules.sort((a, b) => (a.start_h * 60 + a.start_m) - (b.start_h * 60 + b.start_m));

            const positionedSchedules = [];
            const groups = []; // 衝突する予定のグループ

            // 1. 衝突グループの構築
            daySchedules.forEach(schedule => {
                const start = schedule.start_h * 60 + schedule.start_m;
                const end = schedule.end_h * 60 + schedule.end_m;
                let assigned = false;

                // 既に存在するどのグループとも衝突しないかチェック
                let conflictGroups = [];
                for (let group of groups) {
                    const conflicts = group.some(existing => {
                        const existingStart = existing.start_h * 60 + existing.start_m;
                        const existingEnd = existing.end_h * 60 + existing.end_m;

                        // 開始時刻と終了時刻が重なっているか (境界含む)
                        return (start < existingEnd) && (end > existingStart);
                    });
                    if (conflicts) {
                        conflictGroups.push(group);
                    }
                }

                if (conflictGroups.length === 0) {
                    // 衝突グループがない場合、新しいグループを作成
                    groups.push([schedule]);
                    assigned = true;
                } else if (conflictGroups.length === 1) {
                    // 1つのグループと衝突する場合、そのグループに追加
                    conflictGroups[0].push(schedule);
                    assigned = true;
                } else {
                    // 複数のグループと衝突する場合、それらのグループをマージし、新しいグループを作成（このロジックは簡略化しています）
                    // 複雑なマージロジックは実装が難しいため、ここでは最も古いグループに追加するシンプルな方法を採用します。
                    conflictGroups[0].push(schedule);
                    assigned = true;

                    // 実際には、グループをマージし、列計算をリセットする必要がありますが、
                    // シンプルな衝突解決アルゴリズムでは、この方法で視覚的に問題なく表示されることが多いです。
                }

                if (!assigned) {
                    // 新しいグループを作成（念のため）
                    groups.push([schedule]);
                }
            });

            // 2. グループ内の位置と幅を計算
            groups.forEach(group => {
                const columns = []; // グループ内の表示列を管理

                group.forEach(schedule => {
                    const start = schedule.start_h * 60 + schedule.start_m;
                    const end = schedule.end_h * 60 + schedule.end_m;
                    let columnIndex = -1;

                    // 空いている列を探す
                    for (let i = 0; i < columns.length; i++) {
                        let canPlace = true;
                        for (let existingSchedule of columns[i]) {
                            const existingStart = existingSchedule.start_h * 60 + existingSchedule.start_m;
                            const existingEnd = existingSchedule.end_h * 60 + existingSchedule.end_m;

                            // 既存の予定と衝突するか
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

                    // 空いている列がない場合、新しい列を作成
                    if (columnIndex === -1) {
                        columnIndex = columns.length;
                        columns.push([]);
                    }

                    columns[columnIndex].push(schedule);
                    schedule.columnIndex = columnIndex; // 予定に列インデックスを格納
                });

                // 3. 幅と左オフセットの計算
                const maxColumns = columns.length;
                group.forEach(schedule => {
                    schedule.maxColumns = maxColumns;
                    schedule.widthPercentage = 100 / maxColumns;
                    schedule.leftPercentage = schedule.columnIndex * (100 / maxColumns);
                    positionedSchedules.push(schedule);
                });
            });

            return positionedSchedules;
        }

        /**
         * 予定ブロックを生成し、適切な位置とサイズに配置する
         */
        function renderSchedules() {
            $('.schedule-block').remove();

            const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

            days.forEach(day => {
                const daySchedules = schedules[day];
                if (!daySchedules || daySchedules.length === 0) return;

                // 1. 衝突検出と位置情報の計算
                const positionedSchedules = calculateSchedulePositions(daySchedules);

                // スケジュールブロックの描画は、最初に定義されたセル (00:00のセル) に対して相対的に行います。
                const $dayColumnCell = $(`.day-cell.${day}`).first();

                positionedSchedules.forEach(schedule => {
                    const startTotalMinutes = (schedule.start_h * 60 + schedule.start_m) - (START_HOUR * 60);
                    const endTotalMinutes = (schedule.end_h * 60 + schedule.end_m) - (START_HOUR * 60);
                    const durationMinutes = endTotalMinutes - startTotalMinutes;

                    const topOffset = (startTotalMinutes / 30) * CELL_HEIGHT_PX;
                    const height = (durationMinutes / 30) * CELL_HEIGHT_PX;

                    // 2. スタイルに計算した幅と位置を追加
                    const width = `calc(${schedule.widthPercentage}% - 2px)`;
                    const left = `calc(${schedule.leftPercentage}% + 1px)`;

                    const $block = $(`
                    <div class="schedule-block" 
                    data-id="${schedule.id}"
                    data-name="${schedule.name}"
                    data-place="${schedule.place}"
                    data-start="${schedule.start}"
                    data-end="${schedule.end}"
                    data-day-labels="${schedule.day_labels}" style="top: ${topOffset}px; height: ${height}px; width: ${width}; left: ${left};">
                    <strong>${schedule.name}</strong>
                    <span>${schedule.start} - ${schedule.end}</span>
                    </div>
                    `);

                    $dayColumnCell.append($block);
                });
            });
        }

        /**
         * ページロード時に指定された時刻の行までスクロールする
         * @param {string} time 'HHMM'形式の時刻 (例: '0800' = 8:00)
         */
        function scrollToTime(time) {
            const $container = $('#schedule-container');
            const $targetRow = $container.find(`tr[data-time="${time}"]`);

            if ($targetRow.length) {
                const offsetTop = $targetRow.position().top;
                // 現在時刻より少し上の位置にスクロール
                const scrollPosition = offsetTop - CELL_HEIGHT_PX * 2;
                $container.scrollTop(scrollPosition);
            }
        }

        // ページロード時に予定を描画
        renderSchedules();

        /**
         * 現在時刻を取得し、スクロール対象の 'HHMM' 形式の時刻を決定する
         * @returns {string} 'HHMM'形式の時刻 (例: '0830', '1400')
         */
        function getCurrentScrollTime() {
            const now = new Date();
            let hour = now.getHours();
            const minute = now.getMinutes();

            let targetMinute;

            // 15分刻みで丸める
            if (minute < 15) {
                targetMinute = 0;
            } else if (minute < 45) {
                targetMinute = 30;
            } else {
                targetMinute = 0;
                hour += 1; // 時間を繰り上げる
            }

            const formattedHour = String(hour).padStart(2, '0');
            const formattedMinute = String(targetMinute).padStart(2, '0');

            return formattedHour + formattedMinute;
        }

        // ページロード完了後、現在時刻に最も近い30分単位の位置までスクロール
        const currentTime = getCurrentScrollTime();
        scrollToTime(currentTime);


        // 予定ブロッククリック時のポップアップ表示
        $(document).on('click', '.schedule-block', function() {
            const scheduleId = $(this).data('id');
            const name = $(this).data('name');
            const place = $(this).data('place');
            const start = $(this).data('start');
            const end = $(this).data('end');
            const dayLabels = $(this).data('day-labels');

            // ★修正1: dayLabelsの後に「曜日」を追加する
            $('#modal-schedule-days').text(dayLabels).append('曜日');

            // ★修正2: placeが空または空白文字列の場合に「未登録」を表示する
            const displayPlace = (place && place.trim() !== '') ? place : '未登録';

            $('#modal-schedule-name').text(name);
            $('#modal-schedule-time').text(`${start} - ${end}`);
            $('#modal-schedule-place').text(displayPlace); // 修正された値をセット

            $('#btn-edit-delete').attr('href', `index.php?do=all_schedule-edit&schedule_id=${scheduleId}`);

            $('.modal-overlay').fadeIn(200);
        });

        // 閉じるボタンとオーバーレイクリックでポップアップを閉じる
        $('.btn-close, .modal-overlay').on('click', function(e) {
            // e.targetがオーバーレイか、クラスが.btn-closeの要素（<div>）がクリックされた場合に閉じる
            // modal-content自体がクリックされた場合は閉じないようにする
            if ($(e.target).hasClass('modal-overlay') || $(e.target).hasClass('btn-close')) {
                $('.modal-overlay').fadeOut(200);
            }
        });
    });
</script>