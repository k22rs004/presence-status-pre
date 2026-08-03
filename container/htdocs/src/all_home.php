<script>setTimeout(function () {location.reload();}, 60000)</script>
<?php
require_once('db_inc.php');

$sql = "SELECT* FROM tb_user ORDER BY student_number";
$rs = $conn->query($sql);
$errorMessage = "";
if (!$rs) die('エラー: ' . $conn->error);

echo "<h3 class='text-align: center;'>";
$tz = new DateTimeZone('Asia/Tokyo');
$now = new DateTime('now', $tz);
$current_time_jst = $now->format('Y/m/d H:i:s');
echo  $current_time_jst . "現在の在席状況　";
echo '<a type="button" class="btn btn-primary" style="font-size: clamp(12px, 0.8vw, 30px);" href="?do=all_home">ページを更新</a>';
echo "</h3>";

echo "<table class='table table-hover table-bordered' style='
    width: clamp(100px, 50vw, 800px);
    height:auto;
    white-space: nowrap;
    font-size: clamp(14px, 1vw, 20px);
    margin: 0 auto;
    '>";
echo "<tr>";
echo "<td style='text-align: center;'>氏名</td>";
echo "<td style='text-align: center;'>ID</td>";
echo "<td style='text-align: center;'>在席状況</td>";
echo "<td style='text-align: center;'>離席からの経過時間</td>";
echo "<td style='text-align: center;'>離席した日時</td>";
echo "<td style='text-align: center;'>在席開始日時</td>";
echo "<td style='text-align: center;'>現在の予定</td>";
echo "<td style='text-align: center;'>場所</td>";
echo "<td style='text-align: center;'>開催時刻</td>";
echo "<td style='text-align: center;'>鍵の所持</td>";
echo "</tr>";
while ($row = $rs->fetch_assoc()) {
    $uid = $row['user_id'];
    $name = $row['name'];
    $student_number = $row['student_number'];
    if (strcmp($student_number, "guest") == 0) {
        continue;
    }
    echo '<tr>';
    echo '<td style="text-align: left;"><a href="?do=all_time_of_attendance2&uid=' . $uid . '">' . htmlspecialchars($name) . '</a></td>'; // XSS対策
    echo '<td style="text-align: left;">' . htmlspecialchars($student_number) . '</td>'; // XSS対策

    $sql_zaiseki = "SELECT * FROM  tb_leases WHERE MACaddress IN(
     SELECT MACaddress FROM tb_device NATURAL JOIN tb_MACaddress WHERE user_id=".$uid." AND MAC_delete_flag=0)ORDER BY lease_end_date DESC LIMIT 1";

    // 2. クエリの準備
    $rs_zaiseki = $conn->query($sql_zaiseki);
    if (!$rs_zaiseki) die('エラー: ' . $conn->error);
    $row_zaiseki = $rs_zaiseki->fetch_assoc();

    if ($rs_zaiseki->num_rows > 0) {
        $lease_start = new DateTime($row_zaiseki['lease_start_date'], $tz);
        $lease_end = new DateTime($row_zaiseki['lease_end_date'], $tz);

        $interval = $lease_end->diff($lease_start);
        $total_seconds = $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
        $half_seconds = floor($total_seconds / 2);
        $midpoint_time = clone $lease_end;
        $midpoint_time->sub(new DateInterval('PT' . $half_seconds . 'S'));
        $midpoint_time->modify('+30 second');

        if ($midpoint_time > $now) {
            echo '<td style="background-color: #66FF66; text-align: center;">在席中</td>';
            echo '<td style="text-align: right;">-</td>';
            echo '<td style="text-align: right;">-</td>';

            $sql_zaiseki_start = "
                WITH target_leases AS (
                SELECT *
                FROM tb_leases
                WHERE MACaddress IN (
                    SELECT MACaddress
                    FROM tb_device NATURAL JOIN tb_MACaddress
                    WHERE user_id = " . $uid . "
                    AND MAC_delete_flag=0
                    )
                ORDER BY lease_start_date
            ),
            lag_calc AS (
                SELECT
                    *,
                    LAG(lease_start_date) OVER (
                        ORDER BY lease_start_date  
                    ) AS previous_start_date
                FROM target_leases
            )
            SELECT *
            FROM lag_calc
            WHERE TIMESTAMPDIFF(SECOND, previous_start_date, lease_start_date) > 360
            ORDER BY lease_end_date DESC
            LIMIT 1;";
            $rs_zaiseki_start = $conn->query($sql_zaiseki_start);
            if (!$rs_zaiseki_start) die('エラー: ' . $conn->error);
            $row_zaiseki_start = $rs_zaiseki_start->fetch_assoc();
            $datetime_start_obj = new DateTime($row_zaiseki_start['lease_start_date'], $tz);

            $zaiseki_kaishi = $datetime_start_obj->format('m/d H:i') ?? $lease_start->format('m/d H:i');

            echo '<td style="text-align: right;">' . $zaiseki_kaishi . '</td>';
        } else {
            $diff = $now->diff($midpoint_time);
            $days = $diff->days;
            $hours = $diff->h;
            $minutes = $diff->i;

            // 経過時間のフォーマット
            $elapsed = "";
            if ($days > 0) $elapsed .= $days . "日";
            if ($hours > 0 || $days > 0) $elapsed .= time_format($days,$hours) . "時間";
            $elapsed .= time_format($hours,$minutes) . "分";

            echo '<td style="background-color: #999999; color: white; text-align: center;">離席中</td>';
            echo '<td style="text-align: right;">' . $elapsed . '</td>';

            echo '<td style="text-align: right;">' . $midpoint_time->format('m/d H:i') . '</td>';
            echo '<td style="text-align: right;">-</td>';
        }
    } else {
        echo '<td>未登録</td>';
        echo '<td style="text-align: right;">-</td>';
        echo '<td style="text-align: right;">-</td>';
        echo '<td style="text-align: right;">-</td>';
    }
    $current_time = $now->format('H:i:s');

    $day_of_week_num = (int)$now->format('w');
    $power = 6 - $day_of_week_num;
    $current_day_mask = 1 << $power;

    $sql_schedule = "SELECT * FROM tb_schedule WHERE user_id = " . $uid .
        " AND (schedule_start <= '" . $current_time . "' AND schedule_end >= '" . $current_time . "')
        AND (schedule_day_of_week & " . $current_day_mask . ") != 0
        ORDER BY schedule_id DESC";
    $rs_schedule = $conn->query($sql_schedule);
    if (!$rs_schedule) die('エラー: ' . $conn->error);
    $row_schedule = $rs_schedule->fetch_assoc();

    $schedule_name = $row_schedule['schedule_name'] ?? '-';
    $schedule_place = $row_schedule['schedule_place'] ?? '-';
    $schedule_start = $row_schedule['schedule_start'] ?? '-';
    $schedule_end = $row_schedule['schedule_end'] ?? '-';


    echo "<td style='text-align: left;'>" . htmlspecialchars($schedule_name) . "</td>";
    echo "<td style='text-align: left;'>" . htmlspecialchars($schedule_place) . "</td>";
    if ($schedule_start !== '-' && $schedule_end !== '-') {
        echo "<td style='text-align: right;'>" . schedule_time_format($schedule_start) . " ~ " . schedule_time_format($schedule_end) . "</td>";
    } else {
        echo "<td style='text-align: right;'>-</td>";
    }

    //鍵を保持しているか？

    $sql_key = "SELECT * FROM tb_key WHERE user_id=".$uid." AND holder_flag=1 ORDER BY key_record DESC LIMIT 1";

    // 2. クエリの準備
    $rs_key = $conn->query($sql_key);
    if (!$rs_key) die('エラー: ' . $conn->error);
    $row_key = $rs_key->fetch_assoc();

    if($rs_key->num_rows > 0){
        echo "<td style='background-color: #66FF66; text-align: center; font-weight: bold;'>○</td>";
    }else{
        echo "<td style='text-align: center;'>-</td>";
    }
    echo '</tr>';
}

echo "</table>";

function time_format($time_L,$time)
{
    $format_time = "";
    if ($time < 10 && $time_L > 0) {
        $format_time = "0" . $time;
    } else {
        $format_time = $time;
    }
    return $format_time;
}

function schedule_time_format($time)
{
    $schedule_datetime = new DateTime($time);
    $schedule_time = $schedule_datetime->format('H:i');
    return $schedule_time;
}
