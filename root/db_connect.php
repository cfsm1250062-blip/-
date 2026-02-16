<?php
/* ①　データベースの接続情報を定数に格納する */
const DB_HOST = 'mysql:dbname=user_login;host=localhost';
const DB_USER = 'root';
const DB_PASSWORD = 'root';

//②　例外処理を使って、DBにPDO接続する
try {
    $pdo = new PDO(DB_HOST,DB_USER,DB_PASSWORD,[
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES =>false
	]);
} catch (PDOException $e) {
	echo 'ERROR: Could not connect.'.$e->getMessage()."\n";
	exit();
}
