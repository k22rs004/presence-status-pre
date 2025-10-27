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
echo '<a type="button" class="btn btn-primary"href="?do=all_home">ページを更新</a>';
echo "</h3>";

echo "<table class='table table-hover' style='width: 50%; text-align: center; margin: auto;'>";
echo "<tr>";
echo "<td>氏名</td>";
echo "<td>学籍番号</td>";
echo "<td>在席状況</td>";
echo "</tr>";
while ($row = $rs->fetch_assoc()) {
    echo '<tr>';
    $uid = $row['user_id'];
    $name = $row['name'];
    $student_number = $row['student_number'];
    echo '<td>' . htmlspecialchars($name) . '</td>'; // XSS対策
    echo '<td>' . htmlspecialchars($student_number) . '</td>'; // XSS対策

    $sql_zaiseki = "SELECT * FROM  tb_leases WHERE MACaddress IN(
     SELECT MACaddress FROM tb_device NATURAL JOIN tb_MACaddress WHERE user_id=" . $uid .")ORDER BY lease_end_date DESC LIMIT 1";

    // 2. クエリの準備
    $rs_zaiseki = $conn->query($sql_zaiseki);
    if (!$rs_zaiseki) die('エラー: ' . $conn->error);
    $row_zaiseki = $rs_zaiseki->fetch_assoc();

    if($rs_zaiseki->num_rows > 0){

        $lease_end = new DateTime($row_zaiseki['lease_end_date'],$tz);

        if($lease_end > $now){
            echo "<td style='background-color: 00FF00'>在席中</td>";
        }else{
            $diff = $now->diff($lease_end);
            $days = $diff->days;
            $hours = $diff->h;
            $minutes = $diff->i;

            // 経過時間のフォーマット
            $elapsed = "";
            if ($days > 0) $elapsed .= $days . "日";
            if ($hours > 0 || $days > 0) $elapsed .= $hours . "時間";
            $elapsed .= $minutes . "分";

            echo "<td>離席中  " . $elapsed . "経過 </td>";
        }
    }else{
        echo "<td>未登録</td>";
    }

    echo '</tr>';
}

echo "</table>";
