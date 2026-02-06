ファイル構成はコレ

root/
 ├ README.txt(わいやでー)
 ├ PHP+MySQLで簡易ログインシステムを作るweb
 |
 ├ db_connect.php　　//データベースの接続用
 ├ functions.php //ユーザー定義関数
 ├ register.php //新規登録処理
 ├ login.php //ログイン処理
 ├ logout.php //ログアウト処理
 └ welcome.php //ログイン後のウェルカムページ
 
 〇〇.phpをserverのルートにおいて、PHPmyadminの方でデータベースを作ってね。

SQL文だよ:

CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


______________________________________________________________________________________________
あと、なんだっけ…あぁそうそう、PHPmyadminのユーザー設定で、パスワードを設定しておくとスムーズなのかもしれない…
そのへんは調べてね。

解説は"PHP+MySQLで簡易ログインシステムを作る"のwebショーカットをおいたからそれを見てね。あとはよろしく。