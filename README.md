# Webアプリケーション開発用 Dev-Container

Webアプリケーションの開発環境をVSCode + Dev-Containerで提供する。
このWebアプリケーション開発環境では、以下の機能を使用可能である。
- JavaScript
    - [JavaScriptドキュメント](https://developer.mozilla.org/ja/docs/Web/JavaScript/Reference)
    - [JavaScript入門 プログラミング学習](https://paiza.jp/works/js/primer)

- PHP
    - [PHPマニュアル](https://www.php.net/manual/ja/index.php)
    - [PHP入門 プログラミング学習](https://paiza.jp/works/php/primerfemale)

- Python
    - [Python 3.12.3ドキュメント](https://docs.python.org/ja/3/)
    - [Python入門 プログラミング学習](https://paiza.jp/works/python/new-primer)

- MySQL
    - [MySQLリファレンス](https://dev.mysql.com/doc/refman/8.0/ja/)
    - [MySQL入門講座](https://paiza.jp/works/search_courses/2304)

- phpMyAdmin
    - [phpMyAdmin 6.0.0ドキュメント](https://docs.phpmyadmin.net/ja/latest/)

- Apache
    - [Apache 2.4ドキュメント](https://httpd.apache.org/docs/2.4/ja/)



## 開発環境の構築
開発を進めるために、リポジトリのクローンやブランチ作成、Dockerが利用できる環境を構築する。

### GitHub関連の作業

#### 1. リポジトリのクローン
`GitHub`上で`Code`をクリックし`Open with GitHub Desktop` をクリック

#### 2. ブランチの作成
1. GitHub Desktopを開く。

2. `Current Branch`ボタンをクリックする。

3. ベースにしたいブランチ(Recent Branches)を選ぶ。　
    - 初回は`main`を選択する。
    - 2回目以降は、ベースにしたいブランチを自分で選ぶ。

4. `New Branch`ボタンをクリックする。

5. ブランチ作成ウインドウの[名前]に新しいブランチの名前を入力する。 
    - そのブランチで何をしたのかわかるような名前にする。
    - Ex: `update_〇〇_function`

6. 1~5までの手順を完了したブランチを作成する。

### Docker環境の構築
Webアプリケーションの開発環境は、Dockerを用いて構築する。
windowsでDockerを利用するにはWSLを用いる。
macやLinuxマシンを使用している場合は、[2. Docker Desktopのインストール](#2-docker-desktopのインストール)に進む。


#### 1. WSLのインストール
Windows上でDockerを利用するには、WSLが必要になる。
以下の手順に従って、WSLのインストールを進める。

1. スタートメニューボタンを右クリックし `Windows PowerShell (管理者)(A)`をクリック
    - `このアプリがデバイスに変更を加えることを許可しますか?` というダイアログが表示されたら `はい` を選択
1. `wsl --install` を実行
    - `このアプリがデバイスに変更を加えることを許可しますか?` というダイアログが表示されたら `はい` を選択
1. `要求された操作は正常に終了しました。変更を有効にするには、システムを再起動する必要があります。` というメッセージが表示されるので、PC を再起動
1. 再起動後 `Installing, this may take a few minutes...` と表示されるので、しばらく待つ
1. `Enter new UNIX username:` というプロンプトに対しては、学籍番号`k22rs〇〇〇`を入力
1. `New password:` に対しては、好きなパスワードを入力
   - このパスワードを忘れた場合、リカバリが困難なので注意
1. `Retype new password:` に対しては、6. と同じパスワードを再度入力
1. Ubunt Linux のプロンプトが表示されたら、`exit` を実行して終了して OK
1. WSL のメモリ使用量が気になる場合は、以下の操作でメモリ使用量の上限を 1GB を抑えることができる。
   1. スタートボタンを右クリックし `Windows Powershell(I)`をクリック
   2. 以下の内容をコピーし、PowerShell 上に貼り付けて実行

```powershell
Set-Content -Path $Env:HOMEPATH\.wslconfig -Force -Value @'
[wsl2]
memory=1GB
'@
```

※このWSLインストールは以下のリポジトリの内容を参考にした。
[VSCode + devcontainer環境の開発](https://github.com/smkwlab/latex-environment/blob/main/.devcontainer/SETUP-devcontainer.md)


#### 2. Docker Desktopのインストール
- Windowsなら[Docker Desktop for Windows](https://docs.docker.com/desktop/install/windows-install/)をインストールする。
- Macなら[Docker Desktop for Mac](https://docs.docker.com/desktop/install/mac-install/)をインストールする。

## webapp-devenvの使い方
webapp_devenvディレクトリにのcontainerディレクトリに移動する。
```powershell
cd USER_GITHUB_REPOSITORIES/webapp_devenv/container
```

web-containerディレクトリに移動したら、以下のコマンドを叩く。(カレントディレクトリにdocker-compose.ymlがあることを確認)
```powershell
docker compose up -d --build
```

コマンドを叩くと、3つのコンテナが起動する。
コンテナが起動すると、以下のようなログが表示される。
```powershell
[+] Running 4/0
 ✔ Container container-db-1          Running                                                                                                                 0.0s 
 ✔ Container container-phpmyadmin-1  Running                                                                                                                 0.0s 
 ✔ Container container-apache-1      Running                                                                                                                 0.0s 
```

各コンテナに入るには、以下のコマンドを叩く。
```powershell
docker exec -it CONTAINERNAME /bin/bash
```
