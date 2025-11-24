<?php
require_once('db_inc.php');

$sql = "SELECT* FROM tb_user ORDER BY student_number";
$rs = $conn->query($sql);
$errorMessage = "";
if (!$rs) die('エラー: ' . $conn->error);
//$row = $rs->fetch_assoc();

echo "<h3 class='text-align: center;'>";
$tz = new DateTimeZone('Asia/Tokyo');
$now = new DateTime('now', $tz);
$current_time_jst = $now->format('Y/m/d H:i:s');
echo  $current_time_jst . "現在の在席状況　";
echo '<a type="button" class="btn btn-primary" style="font-size: clamp(12px, 0.8vw, 30px);" href="?do=all_home">ページを更新</a>';
echo "</h3>";

echo "<table class='table table-hover' style='
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
echo "</tr>";
while ($row = $rs->fetch_assoc()) {
    $uid = $row['user_id'];
    $name = $row['name'];
    $student_number = $row['student_number'];
    if(strcmp($student_number, "guest") == 0){
            continue;
    }
    echo '<tr>';
    echo '<td style="text-align: left;">' . htmlspecialchars($name) . '</td>'; // XSS対策
    echo '<td style="text-align: left;">' . htmlspecialchars($student_number) . '</td>'; // XSS対策

    $sql_zaiseki = "SELECT * FROM  tb_leases WHERE MACaddress IN(
     SELECT MACaddress FROM tb_device NATURAL JOIN tb_MACaddress WHERE user_id=" . $uid .")ORDER BY lease_end_date DESC LIMIT 1";

    // 2. クエリの準備
    $rs_zaiseki = $conn->query($sql_zaiseki);
    if (!$rs_zaiseki) die('エラー: ' . $conn->error);
    $row_zaiseki = $rs_zaiseki->fetch_assoc();

    if($rs_zaiseki->num_rows > 0){
        $lease_start = new DateTime($row_zaiseki['lease_start_date'],$tz);
        $lease_end = new DateTime($row_zaiseki['lease_end_date'],$tz);

        $interval = $lease_end->diff($lease_start);
        $total_seconds = $interval->days * 86400 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
        $half_seconds = floor($total_seconds / 2);
        $midpoint_time = clone $lease_end;
        $midpoint_time->sub(new DateInterval('PT' . $half_seconds . 'S'));
        $midpoint_time->modify('+30 second');

        if($midpoint_time > $now){
                echo '<td style="background-color: #66FF66; text-align: center;">在席中</td>';
                echo '<td style="text-align: left;">-</td>';
        }else{
            $diff = $now->diff($midpoint_time);
            $days = $diff->days;
            $hours = $diff->h;
            $minutes = $diff->i;

            // 経過時間のフォーマット
            $elapsed = "";
            if ($days > 0) $elapsed .= $days . "日";
            if ($hours > 0 || $days > 0) $elapsed .= $hours . "時間";
            $elapsed .= $minutes . "分";

            echo '<td style="background-color: #999999; color: white; text-align: center;">離席中</td>';
            echo '<td style="text-align: left;">'.$elapsed.'<td>';
        }
    }else{
            echo '<td>未登録</td>';
            echo "<td>-</td>";
    }

    echo '</tr>';
}

echo "</table>";