<div class="d-flex justify-content-center align-items-center vh-100" style="width:100%;">
    <form action="?do=sys_check" method="post" class="form login-form">
        <table class="table table-hover" style="text-align: center;">
            <tr>
                <td>学籍番号：</td>
                <td><input type="text"  name="unumber" class="form-control"></td>
            </tr>
            <tr>
                <td>パスワード：</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="password" id="password" name="pass" class="form-control" style="flex: 1;">
                    </div>
                </td>
            </tr>
            </table>
            <div style="text-align: center; margin-top: 20px;">
            <button class="btn btn-primary" style="width: clamp(100px, 10vw, 800px);">ログイン</button>
            </div>
    </form>
</div>