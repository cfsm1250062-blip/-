ファイル構成はコレ

root/
 ├ README.txt(わいやでー)
 ├ PHP+MySQLで簡易ログインシステムを作るweb
 ├ Enetoku_logo.png //enetokuのロゴ
 ├ db_connect.php　　//データベースの接続用
 ├ functions.php //ユーザー定義関数
 ├ register.php //新規登録処理
 ├ login.php //ログイン処理
 ├ logout.php //ログアウト処理
 ├ welcome.php //ログイン後のウェルカムページ
 ├ api_readings.php //ユーザー別ファイル + 権限
 └ data/
	├ .htaccess
	└ users/
 〇〇.phpをserverのルートにおいて、PHPmyadminの方でデータベースを作ってね。

テーブル名をuser_loginで制作して、以下のSQLを入力してください。

SQL文だよ:
CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


______________________________________________________________________________________________
あと、なんだっけ…あぁそうそう、xampp/PHPmyadmin/config.inc.phpの21行目"$cfg['Servers'][$i]['password'] = '(ここ)';"に設定したパスワードを入力しないとadminでログインできなくなるし動かないよ。
そのへんは調べてね。


解説は"PHP+MySQLで簡易ログインシステムを作る"のwebショーカットをおいたからそれを見てね。あとはよろしく。


