<?php
// ====================================================================
// データベース接続設定ファイル
// ====================================================================
include('db_inc.php');
// db_inc.php にて $conn = new mysqli("localhost", "user", "password", "database"); が設定されていることを想定します。

// セッションから 'uid' を取得
$user_id = $_SESSION['uid'] ?? null;
$error_message = '';
$success_message = '';

// ====================================================================
// 端末情報とMACアドレス情報の初期読み込み
// ====================================================================
$device_id = $_GET['did'] ?? null; // URLパラメータから did を取得
$current_device = null;
$current_mac_addresses = [];
$all_initial_mac_ids = []; // ★ 修正点: 編集開始時に有効な全てのMAC_idを保持する配列

if (empty($user_id)) {
    // ユーザー認証エラー時の処理
    $error_message = "エラー: ユーザー情報が取得できませんでした。再度ログインしてください。"; 
} elseif (!isset($conn) || $conn->connect_errno) {
    $error_message = "データベース接続エラーが発生しました。システム管理者にお問い合わせください。";
    if (isset($conn) && $conn->connect_errno) {
        error_log("DB Connection Error: " . $conn->connect_error);
    }
} elseif (empty($device_id) || !is_numeric($device_id)) {
    $error_message = "エラー: 編集対象の端末情報が指定されていません。";
} else {
    // 端末情報を取得
    $stmt_device = $conn->prepare("SELECT device_id, device_name, user_id FROM tb_device WHERE device_id = ? AND device_delete_flag = 0");
    $stmt_device->bind_param("i", $device_id);
    $stmt_device->execute();
    $result_device = $stmt_device->get_result();
    $current_device = $result_device->fetch_assoc();
    $stmt_device->close();

    if (!$current_device) {
        $error_message = "エラー: 指定された端末が見つからないか、削除されています。";
    } else {
        // MACアドレス情報を取得 (未削除のもののみ)
        $stmt_mac = $conn->prepare("SELECT MAC_id, MACaddress, SSID FROM tb_MACaddress WHERE device_id = ? AND MAC_delete_flag = 0");
        $stmt_mac->bind_param("i", $device_id);
        $stmt_mac->execute();
        $result_mac = $stmt_mac->get_result();
        while ($row = $result_mac->fetch_assoc()) {
            
            // ★ 取得した MAC_id を配列に追加 (フォームから削除されたIDを特定するために使用)
            $all_initial_mac_ids[] = $row['MAC_id'];

            // 10進数MACアドレスを16進数（ハイフン区切り）に変換
            if (function_exists('gmp_init')) {
                // GMP拡張が使える場合（推奨）
                $mac_hex = str_pad(gmp_strval(gmp_init($row['MACaddress'], 10), 16), 12, '0', STR_PAD_LEFT);
            } else {
                // 従来の hexdec を使用する場合（数値が大きいと精度問題が発生する可能性あり）
                $mac_hex = str_pad(dechex($row['MACaddress']), 12, '0', STR_PAD_LEFT);
            }
            $row['mac_blocks'] = str_split($mac_hex, 2);
            $current_mac_addresses[] = $row;
        }
        $stmt_mac->close();
    }
}

// bind_param のためのリファレンス参照ヘルパー関数
// (MAC_idのリストを動的にバインドするために必要)
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3 以上
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// ====================================================================
// バックエンド処理（フォーム送信時: 編集または削除）
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // データベース接続、device_id、ユーザーIDの再確認
    if (!isset($conn) || $conn->connect_errno || empty($device_id) || empty($user_id)) {
        // (省略: 接続エラー処理)
    } else {
        
        // --- 削除ボタンが押された場合 (論理削除) ---
        if (isset($_POST['delete_confirm']) && $_POST['delete_confirm'] === 'true') {
            
            if ($conn->begin_transaction()) {
                try {
                    // 1. tb_device の削除フラグを立てる
                    $stmt_device_del = $conn->prepare("UPDATE tb_device SET device_delete_flag = 1 WHERE device_id = ? AND user_id = ?");
                    $stmt_device_del->bind_param("ii", $device_id, $user_id);
                    if (!$stmt_device_del->execute()) { throw new Exception("tb_device 論理削除失敗: " . $stmt_device_del->error); }
                    $stmt_device_del->close();

                    // 2. 関連する tb_MACaddress の削除フラグを立てる
                    $stmt_mac_del = $conn->prepare("UPDATE tb_MACaddress SET MAC_delete_flag = 1 WHERE device_id = ?");
                    $stmt_mac_del->bind_param("i", $device_id);
                    if (!$stmt_mac_del->execute()) { throw new Exception("tb_MACaddress 論理削除失敗: " . $stmt_mac_del->error); }
                    $stmt_mac_del->close();

                    if ($conn->commit()) {
                        $success_message = "端末「" . htmlspecialchars($_POST['deviceName'] ?? '不明') . "」と関連MACアドレスが削除されました。";
                        // 削除成功後は端末一覧画面にリダイレクト
                        echo "<script>window.location.href = 'index.php?do=all_device_list&status=deleted';</script>";
                        exit;
                    } else {
                        throw new Exception("コミット失敗: " . $conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Delete Transaction Error: " . $e->getMessage());
                    $error_message = "端末削除中にエラーが発生しました。詳細はログをご確認ください。";
                }
            } else {
                $error_message = "データベース処理を開始できませんでした。";
            }
            
        } 
        
        // --- 編集ボタンが押された場合 (端末情報とMACアドレス情報の更新/新規登録) ---
        else {
            $device_name = $_POST['deviceName'] ?? null;
            $ssids = $_POST['ssid'] ?? [];
            $mac_blocks = $_POST['mac_block'] ?? [];
            $existing_mac_ids = $_POST['mac_id'] ?? [];
            
            // 必須項目チェックと端末名チェック
            if (empty($device_name) || mb_strlen($device_name) > 32) {
                $error_message = "エラー: 端末名は1〜32文字で入力してください。";
            } else {

                $mac_data = [];
                $valid = true;
                $selected_ssids_check = [];

                // フォームデータのバリデーションと10進数変換
                foreach ($ssids as $key => $ssid) {
                    
                    $blocks = $mac_blocks[$key] ?? null;
                    $mac_id = $existing_mac_ids[$key] ?? null;

                    if (!$blocks || count($blocks) !== 6 || empty($ssid)) {
                        $valid = false;
                        $error_message = "エラー: SSIDまたはMACアドレスの形式が不正です。（キー: " . htmlspecialchars($key) . "）";
                        break;
                    }

                    if (in_array($ssid, $selected_ssids_check)) {
                        $valid = false;
                        $error_message = "エラー: SSIDが重複しています。";
                        break;
                    }
                    $selected_ssids_check[] = $ssid;

                    $mac_hex = implode('', $blocks);
                    if (!ctype_xdigit($mac_hex) || strlen($mac_hex) !== 12) {
                        $valid = false;
                        $error_message = "エラー: MACアドレスは12桁の16進数である必要があります。";
                        break;
                    }

                    if (function_exists('gmp_init')) {
                        $mac_dec = gmp_strval(gmp_init($mac_hex, 16), 10);
                    } else {
                        // GMPがない場合のフォールバック（大きな数値で精度注意）
                        $mac_dec = hexdec($mac_hex); 
                    }

                    $mac_data[] = [
                        'mac_id' => $mac_id, // 既存ID（論理削除用）
                        'mac_dec' => $mac_dec,
                        'ssid' => $ssid
                    ];
                }


                if ($valid) {
                    if ($conn->begin_transaction()) {
                        try {
                            
                            // 1. tb_device の device_name を更新
                            $stmt_device_upd = $conn->prepare("UPDATE tb_device SET device_name = ? WHERE device_id = ? AND user_id = ?");
                            $stmt_device_upd->bind_param("sii", $device_name, $device_id, $user_id);
                            if (!$stmt_device_upd->execute()) { throw new Exception("tb_device 更新失敗: " . $stmt_device_upd->error); }
                            $stmt_device_upd->close();
                            
                            // フォームに残っている既存の MAC_id のリスト
                            $mac_ids_retained = array_filter(array_column($mac_data, 'mac_id'));

                            // 編集開始時に存在したが、フォーム送信時に残っていない ID を特定 (フォームから削除されたID)
                            $mac_ids_removed_from_form = array_diff($all_initial_mac_ids, $mac_ids_retained);

                            // 最終的な論理削除対象IDリスト
                            // フォームから削除されたID と フォームに残っている既存ID（再登録のため一旦削除）を結合
                            $mac_ids_to_delete = array_unique(array_merge($mac_ids_removed_from_form, $mac_ids_retained));
                            
                            if (!empty($mac_ids_to_delete)) {
                                $placeholders = implode(',', array_fill(0, count($mac_ids_to_delete), '?'));
                                
                                $stmt_mac_clear = $conn->prepare("UPDATE tb_MACaddress SET MAC_delete_flag = 1 WHERE MAC_id IN ($placeholders) AND device_id = ?");
                                
                                $types = str_repeat('i', count($mac_ids_to_delete)) . 'i'; 
                                $params = array_merge([$types], $mac_ids_to_delete, [$device_id]);
                                
                                if (!call_user_func_array([$stmt_mac_clear, 'bind_param'], refValues($params))) {
                                    throw new Exception("tb_MACaddress 論理削除バインド失敗");
                                }
                                
                                if (!$stmt_mac_clear->execute()) { throw new Exception("tb_MACaddress 論理削除失敗: " . $stmt_mac_clear->error); }
                                $stmt_mac_clear->close();
                            }
                            
                            
                            // 3. フォームから送られてきたデータをすべて新規登録 (INSERT)
                            $stmt_mac_ins = $conn->prepare("INSERT INTO tb_MACaddress (MACaddress, SSID, device_id, MAC_delete_flag) VALUES (?, ?, ?, 0)");

                            foreach ($mac_data as $data) {
                                $stmt_mac_ins->bind_param("ssi", $data['mac_dec'], $data['ssid'], $device_id);
                                if (!$stmt_mac_ins->execute()) { throw new Exception("tb_MACaddress 新規登録失敗: " . $stmt_mac_ins->error); }
                            }
                            $stmt_mac_ins->close();


                            if ($conn->commit()) {
                                $success_message = "端末情報とMACアドレス情報が正常に更新されました。";
                                // リダイレクト先を did に変更
                                echo "<script>window.location.href = 'index.php?do=all_device_detail&did=" . htmlspecialchars($device_id) . "&status=edited';</script>";
                                exit;
                            } else {
                                throw new Exception("コミット失敗: " . $conn->error);
                            }

                        } catch (Exception $e) {
                            $conn->rollback();
                            error_log("Edit Transaction Error: " . $e->getMessage());
                            $error_message = "データベース更新中にエラーが発生しました。詳細はログをご確認ください。";
                        }
                    } else {
                        $error_message = "データベース処理を開始できませんでした。";
                    }
                }
            }
        }
    }
}

// フォームの表示名を設定（POST送信でエラーが出た場合、入力値を維持するため）
$device_name_display = $current_device['device_name'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($device_name)) {
    $device_name_display = $device_name;
}
?>
    <style>
        /* CSSは端末登録画面と同じものを使用 */
        body { background-color: white; }
        .main-content { padding: 30px; max-width: 800px; margin: 20px auto; background: white; border-radius: 8px; }
        .form-group-label { min-width: 100px; font-weight: bold; }
        .mac-address-group input { width: 40px; max-width: 40px; text-align: center; padding-left: 5px; padding-right: 5px; text-transform: uppercase; }
        .mac-entry-content { border: 1px solid #ced4da; border-radius: 0.25rem; padding: 15px; background-color: white; }
        .form-control, .form-select { box-shadow: none !important; border: 1px solid #ced4da; }
        .form-control:focus, .form-select:focus { border-color: #adb5bd; box-shadow: none !important; }
        .center-buttons { display: flex; justify-content: center; gap: 20px; padding-top: 10px; }
        .add-mac-container { display: flex; justify-content: flex-end; margin-bottom: 1rem; }
        .modal-content { border-radius: 8px; }
        .modal-footer { justify-content: center; }
    </style>

<body>
    
    <div class="main-content">
        <h3 class="mb-4">端末編集・削除</h3>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($current_device): ?>
            <form id="deviceEditForm" method="POST" action="index.php?do=all_device_edit&did=<?= htmlspecialchars($device_id) ?>">

                <div class="row mb-4 align-items-center">
                    <div class="col-auto form-group-label">
                        <label for="deviceName" class="col-form-label">端末名:</label>
                    </div>
                    <div class="col-md-10 col-lg-6">
                        <input type="text" id="deviceName" name="deviceName" class="form-control" required placeholder="端末名を入力" maxlength="32" oninput="truncateDeviceName(this, 32)" value="<?= htmlspecialchars($device_name_display) ?>">
                    </div>
                </div>

                <div class="add-mac-container">
                    <button type="button" id="addMacAddress" class="btn btn-primary">+ MACアドレス追加</button>
                </div>
                <hr class="my-4">

                <div id="macAddressContainer">
                    </div>

                <hr class="my-4">

                <div class="center-buttons">
                    <a href="index.php?do=all_device_detail&did=<?= htmlspecialchars($device_id) ?>" type="button" class="btn btn-secondary">戻る</a>
                    
                    <button type="button" id="deleteDeviceButton" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">端末削除</button>
                    
                    <button type="submit" id="editButton" class="btn btn-primary">編集確定</button>
                </div>
            </form>
        <?php else: ?>
            <p>指定された端末情報が見つかりません。</p>
            <div class="center-buttons">
                <a href="index.php?do=all_device_list" type="button" class="btn btn-secondary">端末一覧に戻る</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">端末削除の確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="fw-bold">端末名: <span id="modalDeviceName"><?= htmlspecialchars($device_name_display) ?></span></p>
                    <p class="text-danger small" style="text-align: left;">
                        本当にこの端末を削除しますか？<br>
                        端末を削除すると登録されているMACアドレスの情報も
                        同時に削除されます。
                    </p>
                    <form id="deleteExecuteForm" method="POST" action="index.php?do=all_device_edit&did=<?= htmlspecialchars($device_id) ?>">
                        <input type="hidden" name="delete_confirm" value="true">
                        <input type="hidden" id="deleteDeviceNameInput" name="deviceName" value="<?= htmlspecialchars($device_name_display) ?>">
                    </form>
                </div>
                <div class="modal-footer pt-0 border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('deleteExecuteForm').submit();">削除</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // =================================================
        // JavaScript 処理
        // =================================================
        const SSID_OPTIONS = ["toshilab", "toshilab2", "toshilab5", "toshilab6", "toshilab6e"];
        let macEntryIndex = 0; // 新規追加時のインデックスとして使用

        // PHPから受け取った既存データ
        const EXISTING_MAC_DATA = <?= json_encode($current_mac_addresses) ?>;
        
        // MACアドレス登録欄の最大数を利用可能なSSIDの項目数に設定
        const MAX_MAC_ENTRIES = SSID_OPTIONS.length;

        /**
         * 端末名が最大長を超えないように切り詰める関数。
         */
        function truncateDeviceName(input, maxLength) {
            if (input.value.length > maxLength) {
                input.value = input.value.substring(0, maxLength);
            }
            // 削除モーダルの表示名も更新
            const modalNameSpan = document.getElementById('modalDeviceName');
            const modalNameInput = document.getElementById('deleteDeviceNameInput');
            if (modalNameSpan) modalNameSpan.textContent = input.value;
            if (modalNameInput) modalNameInput.value = input.value;
        }

        /**
         * MACアドレスブロックの入力時に実行される関数。
         */
        function validateMacBlockInput(input) {
            let value = input.value.replace(/[^0-9a-fA-F]/g, '').toUpperCase();
            if (value.length > 2) {
                value = value.slice(0, 2);
            }
            input.value = value;

            if (value.length === 2) {
                const macBlocks = input.closest('.mac-address-group').querySelectorAll('input[type="text"]');
                let nextInput = null;

                for (let i = 0; i < macBlocks.length; i++) {
                    if (macBlocks[i] === input) {
                        if (i + 1 < macBlocks.length) {
                            nextInput = macBlocks[i + 1];
                            break;
                        }
                    }
                }

                if (nextInput) {
                    nextInput.focus();
                }
            }
        }


        /**
         * MACアドレス登録欄を生成する関数
         */
        function createMacEntry(index, data = null) {
            const macEntry = document.createElement('div');
            macEntry.classList.add('mac-entry', 'mb-4');
            macEntry.setAttribute('data-index', index);
            
            const macId = data ? data.MAC_id : '';
            const selectedSsid = data ? data.SSID : '';
            const macBlocks = data ? data.mac_blocks : ['', '', '', '', '', ''];
            
            const inputIndex = index;

            // 既存のMAC_idを保持するためのhiddenフィールド (編集時は論理削除対象IDとして使用)
            let macIdInput = `<input type="hidden" name="mac_id[${inputIndex}]" value="${macId}">`;

            macEntry.innerHTML = `
                <div class="mac-entry-content">
                    ${macIdInput}
                    <div class="row g-3">
                        <div class="col-12 d-flex justify-content-between align-items-center mb-2">
                            <div class="w-50">
                                <label for="ssid-${inputIndex}" class="form-label fw-bold">SSID:</label>
                                <select id="ssid-${inputIndex}" name="ssid[${inputIndex}]" class="form-select ssid-select" required>
                                    <option value="">選択してください</option>
                                    ${SSID_OPTIONS.map(ssid => 
                                        `<option value="${ssid}" ${ssid === selectedSsid ? 'selected' : ''}>${ssid}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeMacEntry(this)">削除</button>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">MACアドレス:</label>
                            <div class="d-flex align-items-center mac-address-group">
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[0] || ''}">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[1] || ''}">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[2] || ''}">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[3] || ''}">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[4] || ''}">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${inputIndex}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)" value="${macBlocks[5] || ''}">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return macEntry;
        }

        // MACアドレス登録欄を削除
        function removeMacEntry(button) {
            const container = document.getElementById('macAddressContainer');
            const entries = container.querySelectorAll('.mac-entry');

            // 削除制限: 1つ以下にならない
            if (entries.length > 1) {
                const entryToRemove = button.closest('.mac-entry');
                entryToRemove.style.transition = 'opacity 0.5s ease';
                entryToRemove.style.opacity = '0';

                setTimeout(() => {
                    entryToRemove.remove();
                }, 500);

            } else {
                alert("MACアドレス登録欄は1つ以上必要です。");
            }
        }

        // MACアドレス登録欄を追加
        document.getElementById('addMacAddress').addEventListener('click', () => {
            const container = document.getElementById('macAddressContainer');
            const entries = container.querySelectorAll('.mac-entry');

            if (entries.length < MAX_MAC_ENTRIES) {
                // 新規要素には、現在の macEntryIndex を使い、追加後にインクリメント
                const newEntry = createMacEntry(macEntryIndex++);
                container.appendChild(newEntry);
            } else {
                //alert(`MACアドレス登録欄は最大${MAX_MAC_ENTRIES}つまでです (SSIDの項目数に制限されています)。`);
            }
        });

        // 編集ボタン押下時の処理 (SSID重複チェック)
        document.getElementById('deviceEditForm').addEventListener('submit', function(event) {
            const ssidSelects = document.querySelectorAll('.ssid-select');
            const selectedSsids = [];
            let hasDuplicateSsid = false;

            ssidSelects.forEach(select => {
                const value = select.value;
                if (value) {
                    if (selectedSsids.includes(value)) {
                        hasDuplicateSsid = true;
                    } else {
                        selectedSsids.push(value);
                    }
                }
            });

            if (hasDuplicateSsid) {
                event.preventDefault();
                alert("SSIDが重複しています。登録欄ごとに異なるSSIDを選択してください。");
                return;
            }
        });

        // 初期表示で既存のMACアドレス登録欄を生成、既存データがない場合は1つ生成
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('macAddressContainer');

            if (EXISTING_MAC_DATA.length > 0) {
                EXISTING_MAC_DATA.forEach(data => {
                    // 既存要素には、現在の macEntryIndex を使い、追加後にインクリメント
                    const entry = createMacEntry(macEntryIndex++, data);
                    container.appendChild(entry);
                });
            } else if (container.children.length === 0) {
                // 既存データがないが、端末自体は存在する
                const initialEntry = createMacEntry(macEntryIndex++);
                container.appendChild(initialEntry);
            }
            
            // 端末名入力欄のイベントリスナーをセットアップ
            document.getElementById('deviceName').addEventListener('input', function() {
                truncateDeviceName(this, 32);
            });
            // 初期ロード時の削除モーダル名を設定
            truncateDeviceName(document.getElementById('deviceName'), 32);
        });
    </script>
</body>