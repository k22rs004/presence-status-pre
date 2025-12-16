<?php

include('db_inc.php');

$device_id = $_GET['did'] ?? 0;

$sql = "SELECT* FROM tb_device WHERE device_id=?";
$stmt = $conn->prepare($sql);
$errorMessage = "";
if(!$stmt) {
    $errorMessage = 'プリペア失敗: ' . $conn->error;
    die('エラー: ' . $errorMessage);
}
$stmt->bind_param("i", $device_id);
$result = $stmt->execute();
if ($result === false) {
    // 実行に失敗した場合のエラー処理
    $errorMessage = '実行失敗: ' . $stmt->error;
    die('エラー: ' . $errorMessage);
}
$rs = $stmt->get_result();
$row = $rs->fetch_assoc();
$user_id = $row['user_id'] ?? -1;
if($user_id != $_SESSION['uid']){
    echo '<h1>閲覧できません</h1>';
}

$sql_mac = "SELECT* FROM tb_MACaddress WHERE device_id=? AND MAC_delete_flag=0";
$stmt_mac = $conn->prepare($sql_mac);
$errorMessage = "";
if(!$stmt_mac) {
    $errorMessage = 'プリペア失敗: ' . $conn->error;
    die('エラー: ' . $errorMessage);
}
$stmt_mac->bind_param("i", $device_id);
$result_mac = $stmt_mac->execute();
if ($result_mac === false) {
    // 実行に失敗した場合のエラー処理
    $errorMessage = '実行失敗: ' . $stmt_mac->error;
    die('エラー: ' . $errorMessage);
}
$rs_mac = $stmt_mac->get_result();
echo "<h2 style='margin :auto; font-size: clamp(14px, 2vw, 30px); margin-left:20%;
margin-top:5%;'>端末名：".$row['device_name']."</h2>";

echo '<table class="table table-hover table-bordered" style="
    width: clamp(300px, 40vw, 800px);
    height:auto;
    white-space: nowrap;
    font-size: clamp(16px, 1vw, 20px);
    margin: auto;
    margin-top: 10px;">';
echo "<tr>";
echo "<th>SSID</th>";
echo "<th>MACアドレス</th>";
echo "</tr>";
while($row_mac = $rs_mac->fetch_assoc()){
    $ssid=htmlspecialchars($row_mac['SSID']);
    $macaddress = formatDecimalToMacAddress($row_mac['MACaddress']);
    echo "<tr>";
    echo "<td>".$ssid."</td>";
    echo "<td>".$macaddress."</td>";
    echo "</tr>";
}
echo "</table>";

// ここから修正
echo '<div class="buttons" style="
    /* 幅は中央寄せのために残すか削除しても良い */
    width: clamp(300px, 40vw, 800px); 
    margin: 10px auto 0;
    display: flex; 
    justify-content: center; /* ボタン全体を中央寄せ */
    gap: 5%; /* ボタン同士の間隔を15pxに設定（近接させる） */
">';
// 戻るボタンを先に記述
echo '<div>'; 
echo '<a href="?do=all_device_list" class="btn btn-secondary">戻る</a>';
echo '</div>';
// 編集・削除ボタンを後に記述
echo '<div>'; 
echo '<a href="?do=all_device_edit&did='.$device_id.'" class="btn btn-primary">編集・削除</a>';
echo '</div>';
echo "</div>";
// 修正ここまで

function formatDecimalToMacAddress(int $decimal_val): string {
    // 1. 10進数を16進数に変換
    // 結果: "aabbccddeeff" のような文字列
    $hex_string = dechex($decimal_val);

    // MACアドレスは通常12桁（6バイト）なので、必要に応じてゼロパディングを行う
    // 例: 0x123456789ABC の場合、12桁
    // 例: 0xABCD の場合、00000000ABCD となるようにパディング
    $padded_hex = str_pad($hex_string, 12, '0', STR_PAD_LEFT);

    // 2. 16進数文字列を2文字ずつ区切る
    // 結果: ['AA', 'BB', 'CC', 'DD', 'EE', 'FF'] のような配列
    $hex_array = str_split($padded_hex, 2);

    // 3. 配列の要素をコロンで結合し、大文字に変換して返す
    // 結果: "AA:BB:CC:DD:EE:FF"
    return strtoupper(implode(':', $hex_array));
}

?>