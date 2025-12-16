<?php
// ====================================================================
// データベース接続設定ファイル
// ====================================================================
include('db_inc.php');
// db_inc.php にて $conn = new mysqli("localhost", "user", "password", "database"); が設定されていることを想定します。

// セッションから 'uid' を取得
// 実際にはセッション開始 (session_start()) が必要ですが、ファイル全体から判断しここでは省略されています。
$user_id = $_SESSION['uid'] ?? null;
$error_message = '';

// ユーザーIDが取得できない場合の処理
if (empty($user_id)) {
    // ユーザー認証エラー時の処理
    // $error_message = "エラー: ユーザー情報が取得できませんでした。再度ログインしてください。"; 
    // 実際の本番環境ではログインページなどにリダイレクトすべきです: header('Location: login.php'); exit;
}

// ====================================================================
// バックエンド処理（フォーム送信時）
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // データベース接続が確立しているか確認
    // $conn は db_inc.php で初期化されていることを前提とする
    if (!isset($conn) || $conn->connect_errno) {
        // 接続が未定義またはエラーの場合
        $error_message = "データベース接続エラーが発生しました。システム管理者にお問い合わせください。";
        if (isset($conn) && $conn->connect_errno) {
            error_log("DB Connection Error: " . $conn->connect_error);
        }
    } else {

        $device_name = $_POST['deviceName'] ?? null;
        $ssids = $_POST['ssid'] ?? [];
        $mac_blocks = $_POST['mac_block'] ?? [];

        // 必須項目チェック
        if (empty($device_name) || empty($ssids) || empty($mac_blocks)) {
            $error_message = "エラー: 必須項目が不足しています。";
        } else {

            // 端末名最大文字数チェック (サーバー側での簡易チェック)
            if (mb_strlen($device_name) > 32) {
                $error_message = "エラー: 端末名は32文字以内で入力してください。";
            } else {

                // MACアドレスの10進数変換とバリデーション
                $mac_data = [];
                $valid = true;
                $selected_ssids_check = []; // SSID重複チェック用

                foreach ($ssids as $key => $ssid) {
                    $blocks = $mac_blocks[$key] ?? null;

                    if (!$blocks || count($blocks) !== 6 || empty($ssid)) {
                        $valid = false;
                        $error_message = "エラー: SSIDまたはMACアドレスの形式が不正です。";
                        break;
                    }

                    // SSID重複チェック (サーバー側)
                    if (in_array($ssid, $selected_ssids_check)) {
                        $valid = false;
                        $error_message = "エラー: SSIDが重複しています。MACアドレス登録欄ごとに異なるSSIDを選択してください。";
                        break;
                    }
                    $selected_ssids_check[] = $ssid;


                    $mac_hex = implode('', $blocks);

                    // サーバー側での厳密な16進数チェック (大文字・小文字、12桁)
                    if (!ctype_xdigit($mac_hex) || strlen($mac_hex) !== 12) {
                        $valid = false;
                        $error_message = "エラー: MACアドレスは12桁の16進数である必要があります。";
                        break;
                    }

                    // 16進数から10進数 (BIGINT) へ変換
                    if (function_exists('gmp_init')) {
                        // GMP関数が使える場合
                        $mac_dec = gmp_strval(gmp_init($mac_hex, 16), 10);
                    } else {
                        // 64bit OSかつPHPのinteger型に収まらない場合は問題が発生するため、注意が必要。
                        $mac_dec = hexdec($mac_hex);
                    }

                    $mac_data[] = [
                        'mac_dec' => $mac_dec,
                        'ssid' => $ssid
                    ];
                }

                if ($valid) {
                    // データベース処理 (mysqli トランザクション)
                    $success = false;

                    // 実際のDB操作ロジックを復元
                    if ($conn->begin_transaction()) {
                        try {
                            // 1. tb_device に端末情報を登録
                            $stmt_device = $conn->prepare("INSERT INTO tb_device (device_name, user_id) VALUES (?, ?)");
                            $stmt_device->bind_param("si", $device_name, $user_id);

                            if (!$stmt_device->execute()) {
                                throw new Exception("tb_device への登録失敗: " . $stmt_device->error);
                            }
                            $stmt_device->close();

                            $device_id = $conn->insert_id;

                            // 2. tb_MACaddress にMACアドレス情報を登録
                            $sql_mac = "INSERT INTO tb_MACaddress (MACaddress, SSID, device_id) VALUES (?, ?, ?)";
                            $stmt_mac = $conn->prepare($sql_mac);

                            foreach ($mac_data as $data) {
                                $stmt_mac->bind_param("ssi", $data['mac_dec'], $data['ssid'], $device_id);

                                if (!$stmt_mac->execute()) {
                                    throw new Exception("tb_MACaddress への登録失敗: " . $stmt_mac->error);
                                }
                            }
                            $stmt_mac->close();

                            if ($conn->commit()) {
                                $success = true;
                            } else {
                                throw new Exception("コミット失敗: " . $conn->error);
                            }
                        } catch (Exception $e) {
                            $conn->rollback();
                            error_log("Transaction Error: " . $e->getMessage());
                            $error_message = "データベース登録中にエラーが発生しました。詳細はログをご確認ください。";
                        }
                    } else {
                        error_log("Failed to start transaction: " . $conn->error);
                        $error_message = "データベース処理を開始できませんでした。";
                    }

                    // 登録成功後のリダイレクト
                    if ($success) {
                        echo "<script>";
                        echo 'window.location.href = "?do=all_device_list";';
                        echo "</script>";
                        exit;
                    }
                }
            }
        }
    }
}
?>
<style>
    /* ================================================= */
    /* カスタムCSS */
    /* ================================================= */
    body {
        background-color: white;
    }

    .main-content {
        padding: 30px;
        max-width: 800px;
        margin: 20px auto;
        background: white;
        border-radius: 8px;
        /*box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);*/
    }

    .form-group-label {
        min-width: 100px;
        font-weight: bold;
    }

    /* MACアドレスの各ブロックの幅調整 */
    .mac-address-group input {
        width: 40px;
        max-width: 40px;
        text-align: center;
        padding-left: 5px;
        padding-right: 5px;
        /* 強制的に大文字化 */
        text-transform: uppercase;
    }

    /* MACアドレス欄のコンテナに枠線をつける */
    .mac-entry-content {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 15px;
        background-color: white;
    }

    /* 入力欄の立体感の除去 */
    .form-control,
    .form-select {
        /* 影を無効化 */
        box-shadow: none !important;
        /* ボーダーをフラットに */
        border: 1px solid #ced4da;
    }

    .form-control:focus,
    .form-select:focus {
        /* フォーカス時の青い光彩（アウトライン）を無効化 */
        border-color: #adb5bd;
        /* 僅かに濃いグレー */
        box-shadow: none !important;
    }

    /* 登録ボタンと戻るボタンを中央配置 */
    .center-buttons {
        display: flex;
        justify-content: center;
        /* 中央寄せ */
        gap: 20px;
        /* ボタンの隙間を広げる */
        padding-top: 10px;
    }

    /* MACアドレス追加ボタンを右寄せに配置するためのコンテナ */
    .add-mac-container {
        display: flex;
        justify-content: flex-end;
        /* 右寄せ */
        margin-bottom: 1rem;
    }
</style>

<body>

    <div class="main-content">
        <h3 class="mb-4">端末登録</h3>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form id="deviceRegistrationForm">

            <div class="row mb-4 align-items-center">
                <div class="col-auto form-group-label">
                    <label for="deviceName" class="col-form-label">端末名:</label>
                </div>
                <div class="col-md-10 col-lg-6">
                    <input type="text" id="deviceName" name="deviceName" class="form-control" required placeholder="端末名を入力" maxlength="32" oninput="truncateDeviceName(this, 32)">
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
                <a href="?do=all_device_list" type="button" class="btn btn-secondary">戻る</a>
                <button type="submit" id="registerButton" class="btn btn-primary">登録確定</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // =================================================
        // JavaScript 処理
        // =================================================
        const SSID_OPTIONS = ["toshilab", "toshilab2", "toshilab5", "toshilab6", "toshilab6e"];
        let macEntryIndex = 0; // MACアドレス登録欄のインデックス

        // MACアドレス登録欄の最大数を利用可能なSSIDの項目数に設定
        const MAX_MAC_ENTRIES = SSID_OPTIONS.length;

        /**
         * 端末名が最大長を超えないように切り詰める関数。
         * @param {HTMLInputElement} input - 入力要素
         * @param {number} maxLength - 最大文字数
         */
        function truncateDeviceName(input, maxLength) {
            if (input.value.length > maxLength) {
                input.value = input.value.substring(0, maxLength);
            }
        }


        /**
         * MACアドレスブロックの入力時に実行される関数。（★修正済み★）
         * 入力値が16進数のみであることを保証し、2文字入力完了後に次のフィールドに移動します。
         * @param {HTMLInputElement} input - MACアドレスの入力要素
         */
        function validateMacBlockInput(input) {
            // 1. 16進数以外の文字を削除する (入力制御)
            let value = input.value.replace(/[^0-9a-fA-F]/g, '');

            // 2. 値を大文字に変換する
            value = value.toUpperCase();

            // 3. 2文字に制限する
            if (value.length > 2) {
                value = value.slice(0, 2);
            }

            input.value = value;

            // 4. 2文字入力が完了したら次のフィールドにフォーカスを移動
            if (value.length === 2) {
                // 現在の入力要素と同じグループのすべての入力要素を取得
                const macBlocks = input.closest('.mac-address-group').querySelectorAll('input[type="text"]');
                let nextInput = null;

                // macBlocksリスト内で現在の入力要素の次の要素を探す
                for (let i = 0; i < macBlocks.length; i++) {
                    if (macBlocks[i] === input) {
                        // 次の要素が存在するか確認し、存在すればフォーカスを移動
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


        // MACアドレス登録欄を生成する関数
        function createMacEntry(index) {
            const macEntry = document.createElement('div');
            macEntry.classList.add('mac-entry', 'mb-4');
            macEntry.setAttribute('data-index', index);

            // oninput="validateMacBlockInput(this)" を各入力フィールドに追加
            macEntry.innerHTML = `
                <div class="mac-entry-content">
                    <div class="row g-3">
                        <div class="col-12 d-flex justify-content-between align-items-center mb-2">
                            <div class="w-50">
                                <label for="ssid-${index}" class="form-label fw-bold">SSID:</label>
                                <select id="ssid-${index}" name="ssid[]" class="form-select ssid-select" required>
                                    <option value="">選択してください</option>
                                    ${SSID_OPTIONS.map(ssid => `<option value="${ssid}">${ssid}</option>`).join('')}
                                </select>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeMacEntry(this)">削除</button>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">MACアドレス:</label>
                            <div class="d-flex align-items-center mac-address-group">
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
                                <span class="mx-1">:</span>
                                <input type="text" name="mac_block[${index}][]" maxlength="2" pattern="[0-9a-fA-F]{2}" class="form-control" required placeholder="" oninput="validateMacBlockInput(this)">
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

            // 追加制限: SSIDの数まで
            if (entries.length < MAX_MAC_ENTRIES) {
                const newEntry = createMacEntry(macEntryIndex++);
                container.appendChild(newEntry);
            } else {
                //alert(`MACアドレス登録欄は最大${MAX_MAC_ENTRIES}つまでです (SSIDの項目数に制限されています)。`);
            }
        });

        // 登録ボタン押下時の処理 (SSID重複チェック)
        document.getElementById('deviceRegistrationForm').addEventListener('submit', function(event) {

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
                alert("SSIDが重複しています。MACアドレス登録欄ごとに異なるSSIDを選択してください。");
                return;
            }

            // PHP側でリダイレクトを行うため、ここではアクションを指定
            this.action = 'index.php?do=all_device_add'; // 自身のファイルにPOSTする
            this.method = 'POST';
        });

        // 初期表示でデフォルトのMACアドレス登録欄を1つ生成
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('macAddressContainer');
            if (container.children.length === 0) {
                const initialEntry = createMacEntry(macEntryIndex++);
                container.appendChild(initialEntry);
            }
        });
    </script>
</body>