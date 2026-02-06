<?php
//XSS対策
function h($s){
    return htmlspecialchars($s, ENT_QUOTES, "UTF-8");
}

//セッションにトークンセット
function setToken(){
    $token = sha1(uniqid(mt_rand(), true));
    $_SESSION['token'] = $token;
}

//セッション変数のトークンとPOSTされたトークンをチェック
function checkToken(){
    if(empty($_SESSION['token']) || ($_SESSION['token'] != $_POST['token'])){
        echo 'Invalid POST', PHP_EOL;
        exit;
    }
}

//POSTされた値のバリデーション
function validation($datas,$confirm = true)
{
    $errors = [];

    //ユーザー名のチェック
    if(empty($datas['name'])) {
        $errors['name'] = 'Please enter username.';
    }else if(mb_strlen($datas['name']) > 20) {
        $errors['name'] = 'Please enter up to 20 characters.';
    }

    //パスワードのチェック（正規表現）
    if(empty($datas["password"])){
        $errors['password']  = "Please enter a password.";
    }else if(!preg_match('/\A[a-z\d]{8,100}+\z/i',$datas["password"])){
        $errors['password'] = "Please set a password with at least 8 characters.";
    }
    //パスワード入力確認チェック（ユーザー新規登録時のみ使用）
    if($confirm){
        if(empty($datas["confirm_password"])){
            $errors['confirm_password']  = "Please confirm password.";
        }else if(empty($errors['password']) && ($datas["password"] != $datas["confirm_password"])){
            $errors['confirm_password'] = "Password did not match.";
        }
    }

    return $errors;
}
