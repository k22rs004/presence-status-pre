<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在席状況可視化システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header>
        <div class="header-top bg-primary text-white d-flex justify-content-between align-items-center py-2 px-4">
            <h1 class="h4 mb-0">在席状況可視化システム</h1>
            <div class="d-flex align-items-center">
                <?php
                if (isset($_SESSION['urole'])) {
                    echo '<span class="me-3">' . $_SESSION['student_number'] . '</span>';
                    echo '<span class="me-3">' . $_SESSION['uname'] . '</span>';
                    echo '<span class="me-3">   </span>';
                    echo '<a href="?do=sys_logout" class="text-white text-decoration-none">ログアウト</a>';
                }
                ?>
            </div>
        </div>

        <?php
        if (isset($_SESSION['urole'])) {
            echo '<nav class="navbar navbar-expand navbar-dark bg-dark p-0">';
            echo '<div class="container-fluid px-4">';
            echo '<ul class="navbar-nav">';
            $menu = array(
                '在席状況' => 'all_home',
                '在席時間帯' => '#',
                'スケジュール' => '',
                '登録端末' => ''
            );

            foreach ($menu as $label => $action) {
                echo '<li class="nav-item">';
                if ($action == '') {
                    // リンクなしで名前を表示
                    echo '<li class="nav-link large-nav-link" style="color: white !important;">' . $label . '</li>';
                } else {
                    echo '<li><a class="nav-link large-nav-link" style="color: white !important;" href="?do=' . $action . '">' . $label . '</a></li>';
                }
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';
            echo '</nav>';
        }
        ?>
        <!--
        <li class="nav-item">
            <a class="nav-link large-nav-link" style="color: white !important;" href="#">在席状況</a>
        </li>
        <li class="nav-item">
            <a class="nav-link large-nav-link" style="color: white !important;" href="#">在席時間帯</a>
        </li>
        <li class="nav-item">
            <a class="nav-link large-nav-link" style="color: white !important;" href="#">スケジュール</a>
        </li>
        <li class="nav-item">
            <a class="nav-link large-nav-link" style="color: white !important;" href="#">登録端末一覧</a>
        </li>
        <li class="nav-item">
            <a class="nav-link large-nav-link" style="color: white;" href="#">パスワード変更</a>
        </li>
        -->


    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>