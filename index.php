<?php

// composerで読み込んだライブラリ
require './vendor/autoload.php';

require './env.php';
require './controllers/article_controller.php';
require './controllers/comment_controller.php';
require './controllers/user_controller.php';


// jsonを返す準備は整いました。本番環境だと、この辺の設定がもう少し増えますね。藤原もあんま分かってない。
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// クライアントからのリクエストデータ(json)をデコードして、phpのオブジェクトに格納
$request_json = file_get_contents('php://input');
$request_data = json_decode($request_json, TRUE);

$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// mysqlのコネクション
$conn = new mysqli(DB_SERVER_NAME, USERNAME, PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// ここから下が、いわゆる「ルーティング」です
// 今回は、原理を知って欲しかったので、正規表現やif elseだらけ担っていますが、
// どんな言語もだいたい、便利なルーティングのライブラリあるので、正規表現もif elseも使わずにかけます

if (preg_match('/^\/login$/', $request_uri) && $request_method === 'POST') {
    echo login_user($conn, $request_data);

} else if (preg_match('/^\/register$/', $request_uri) && $request_method === 'POST') {
    echo register_user($conn, $request_data);

} else if (preg_match('/^\/articles$/', $request_uri)) {
    switch ($request_method) {
        case 'GET':
            echo get_all_article($conn);
            break;
        case 'POST':
            echo create_article($conn, $request_data, token_verify($conn));
            break;
    }

} else if (preg_match('/^\/articles\/([0-9]+)$/', $request_uri, $matches)) {
    $article_id = $matches[1];

    switch ($request_method) {
        case 'GET':
            echo get_article_detail($conn, $article_id);
            break;
        case 'PUT':
            echo update_article($conn, $article_id, $request_data, token_verify($conn));
            break;
        case 'DELETE':
            echo delete_article($conn, $article_id, token_verify($conn));
            break;
    }

} else if (preg_match('/^\/articles\/([0-9]+)\/comments$/', $request_uri, $matches)) {
    $article_id = $matches[1];

    switch ($request_method) {
        case 'GET':
            echo get_comments($conn, $article_id);
            break;
        case 'POST':
            echo create_comments($conn, $article_id, $request_data, token_verify($conn));
            break;
    }

} else if (preg_match('/^\/comments\/([0-9]+)$/', $request_uri, $matches)) {
    $comment_id = $matches[1];

    switch ($request_method) {
        case 'GET':
            echo get_comment_detail($conn, $comment_id);
            break;
        case 'PUT':
            echo update_comment($conn, $comment_id, $request_data, token_verify($conn));
            break;
        case 'DELETE':
            echo delete_comment($conn, $comment_id, token_verify($conn));
            break;
    }

} else {
    http_response_code(404);
    echo json_encode([
        "message" => "route not found"
    ]);
}

// ここでクローズしておけば、間違いないよね
$conn->close();
