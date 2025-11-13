<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在席状況可視化システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/styles.css">
    <meta http-equiv="refresh" content="60">
</head>

<body>
    <header>
        <div class="header-top bg-primary text-white d-flex justify-content-between align-items-center py-2 px-4">
            <h1 class="h4 mb-0" style="white-space: nowrap; font-size: clamp(14px, 3vw, 32px);">在席状況可視化システム</h1>
            <div class="d-flex align-items-center">
                <?php
                if (isset($_SESSION['urole'])) {
                    echo '<span class="me-3">' . $_SESSION['student_number'] . '</span>';
                    echo '<span class="me-3">' . $_SESSION['uname'] . '</span>';
                    echo '<span class="me-3">   </span>';
                    echo '<a href="?do=sys_logout" class="text-white text-decoration-none" style="font-size: clamp(12px, 1vw, 32px);">ログアウト</a>';
                }
                ?>
            </div>
        </div>

        <?php
        if (isset($_SESSION['urole'])) {
            echo '<nav class="navbar navbar-expand navbar-dark bg-dark p-0" style=>';
            echo '<div class="container-fluid px-4">';
            echo '<ul class="navbar-nav">';
            $menu = array(
                '在席状況' => 'all_home',
                '在席時間帯' => 'tmp',
                'スケジュール' => 'tmp',
                '登録端末' => 'tmp'
            );

            foreach ($menu as $label => $action) {
                echo '<li class="nav-item">';
                if ($action == '') {
                    // リンクなしで名前を表示
                    echo '<li class="nav-link large-nav-link" style="color: white; font-size: clamp(10px, 1vw, 24px);">' . $label . '</li>';
                } else {
                    echo '<li><a class="nav-link large-nav-link" style="color: white; font-size: clamp(10px, 1vw, 24px);" href="?do=' . $action . '">' . $label . '</a></li>';
                }
                echo '</li>';
            }


            echo '</ul>';
            echo '</div>';
            echo '</nav>';
        }
        ?>


    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>