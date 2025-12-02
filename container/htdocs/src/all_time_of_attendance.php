
<table class="table table-bordered" style="width: 70%; margin:auto">
<?php

$uid = 4;

include("db_inc.php");
function present_probability($uid, $week_number, $start_time, $end_time){
    $tz = new DateTimeZone('Asia/Tokyo');
    $now = new DateTime('now', $tz);
    $now_date_str = $now->format('Y-m-d');

    global $conn;
    
    $sql_period = "SELECT* FROM tb_calculation_period WHERE cast('".$now_date_str."' as DATE) >= period_start AND cast('".$now_date_str."' as DATE) <= period_end";
    $rs_period = $conn->query($sql_period);
    $errorMessage1 = "";
    if (!$rs_period) die('エラー: ' . $conn->error);

    $row_period = $rs_period->fetch_assoc();
    
    $period_start = $row_period['period_start'];
    $period_end = $row_period['period_end'];

    $sql_present="SELECT count(DISTINCT DATE(lease_start_date)) as count_present
    FROM tb_leases
    WHERE MACaddress IN(
    SELECT MACaddress
    FROM tb_device
    NATURAL JOIN tb_MACaddress
    WHERE user_id=".$uid."
    )
    AND DAYOFWEEK(lease_start_date) = ".$week_number."
    AND (cast(lease_end_date as DATE) >= '".$period_start."' AND cast(lease_start_date as DATE) <= '".$period_end."')
    AND (cast(lease_end_date as TIME) >= '".$start_time."' AND cast(lease_start_date as TIME) < '".$end_time."')
    ";
    $rs_present = $conn->query($sql_present);
    $errorMessage2 = "";
    if (!$rs_present) die('エラー: ' . $conn->error);
    $row_present = $rs_present->fetch_assoc();

    $start_time =  new DateTime($period_start);
    $interval = $start_time ->diff($now);
    $days = $interval->days;

    $day_of_week_start = (int)$start_time->format('w');
    $day_of_week_now = (int)$now->format('w');
    $week_count = 0;
    
    $days=$days-(7-$day_of_week_start);
    if($day_of_week_start <= $week_number-1){
        $week_count++;
    }

    $week_count+=((int)($days/7));
    if($days%7 >= $week_number-1){
        $week_count++;
    }

    $probability=(int)(($row_present['count_present']*100)/$week_count);

    return $probability;
}



$day_labels_jp = ['日', '月', '火', '水', '木', '金', '土'];

function time_format($time){
    if($time < 10){
        $format_time = "0".$time.":00:00";
    }else{
        $format_time = $time.":00:00";
    }
    return $format_time;
}

echo "<th style='width: 5%;'>時刻</th>";
foreach($day_labels_jp as $day_of_week){
    echo "<th style='text-align: center;'>".$day_of_week."</th>";
}
for($i = 0; $i < 24; $i++){
    echo "<tr><td>".$i."</td>";
    for($j = 1; $j <= 7; $j++){
        $start_time = time_format($i);
        $end_time = time_format($i+1);
        $present_count=present_probability($uid,$j,$start_time,$end_time);
        if($present_count >= 60){
            $color = "white";
        }else{
            $color = "black";
        }
        $red_green_value = (int)(255 - ($present_count * 255 / 100));
        echo "<td style='color : ".$color."; background-color: rgb(".$red_green_value.",".$red_green_value.", 255);'>".$present_count."%</td>";
    }
    echo "</tr>";
}
?>
</table>