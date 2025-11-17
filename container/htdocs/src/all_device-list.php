<div class="container-fluid pt-5">
    <div class="main-content-area">
        <h2 style="margin :auto; font-size: clamp(14px, 2vw, 30px); margin-left:10%;">端末一覧</h2>

        <div class="d-flex justify-content-center align-items-center mb-4">
            <input type="text" placeholder="端末名を入力" class="form-control me-1" style="width: 30%; min-width: 350px">
            <button class="btn btn-primary me-3 fw-bold" style="font-size: clamp(16px, 0.8vw, 30px);">検索</button>
        </div>
        <div class="button-container" style="text-align: right;">
            <button class="btn btn-primary fw-bold" style="margin-right:20%; font-size: clamp(14px, 0.8vw, 30px);">+端末追加</button>
        </div>
    </div>
</div>
<table class="table table-hover table-bordered" style="
    width: clamp(300px, 20vw, 800px);
    height:auto;
    white-space: nowrap;
    font-size: clamp(16px, 1vw, 20px);
    margin: auto;
    margin-top: 10px;">

    <tr>
        <td style="text-align: center;">端末名</td>
    </tr>
    
<?php
require_once('db_inc.php');

$sql = "SELECT* FROM tb_device WHERE user_id =".$_SESSION['uid'];
$rs = $conn->query($sql);
$errorMessage = "";
if (!$rs) die('エラー: ' . $conn->error);

while ($row = $rs->fetch_assoc()) {
    $device_name = htmlspecialchars($row['device_name']);
    echo "<tr><td>";
    echo $device_name;
    echo "</td></tr>";
}

?> 
</table>