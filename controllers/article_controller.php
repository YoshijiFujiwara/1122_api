<?php


/**
 * 全ての記事情報を取得する処理
 */
function get_all_article($conn)
{
    $sql = "SELECT * FROM articles";
    $result = $conn->query($sql);

    $response_data = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response_data[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'title' => $row['title'],
                'body' => $row['body']
            ];
        }
    }

    return json_encode($response_data);
}

/**
 * 記事の作成
 */
function create_article($conn, $request, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    $title = $request['title'];
    $body = $request['body'];

    // バリデーション
    if (empty($title) || empty($body)) {
        return json_encode([
            'message' => '記事のタイトルと本文は、必須項目です'
        ]);
    }

    // "プリペアードステートメント"でググる
    $stmt = $conn->prepare("INSERT INTO articles (user_id, title, body) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $login_user['id'], $title, $body);

    $stmt->execute();
    $stmt->close();

    http_response_code(201);
    return;
}


/**
 * 一つの記事の詳細を取得する
 */
function get_article_detail($conn, $article_id)
{
    $stmt = $conn->prepare("SELECT id, user_id, title, body FROM articles WHERE id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $user_id, $title, $body); // 変な書き方な気がするけどな
    $stmt->fetch();
    $stmt->close();

    if (empty($id)) {
        http_response_code(404);
        return;
    }

    return json_encode([
        'id' => $id,
        'user_id' => $user_id,
        'title' => $title,
        'body' => $body
    ]);
}

/**
 * 記事の内容をアップデートする
 */
function update_article($conn, $article_id, $request, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    $title = $request['title'];
    $body = $request['body'];

    // バリデーション
    if (empty($title) || empty($body)) {
        http_response_code(400);
        return json_encode([
            'message' => '記事のタイトルと本文は、必須項目です'
        ]);
    }

    // 記事の投稿主とログインユーザーが一致しているか確認する
    $stmt = $conn->prepare("SELECT user_id FROM articles WHERE id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($author_id);
    $stmt->fetch();
    $stmt->close();

    if ((int)$author_id !== $login_user['id']) {
        http_response_code(400);
        return;
    }

    // 以上がOKなら、アップデートしようね
    $stmt = $conn->prepare("UPDATE articles SET title = ?, body = ? WHERE id = ?");
    $stmt->bind_param("ssi", $title, $body, $article_id);
    $stmt->execute();
    $stmt->close();

    return json_encode([
        'id' => $article_id,
        'user_id' => $author_id,
        'title' => $title,
        'body' => $body
    ]);
}

/**
 * 記事の削除
 */
function delete_article($conn, $article_id, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    // 記事の投稿主とログインユーザーが一致しているか確認する
    $stmt = $conn->prepare("SELECT user_id FROM articles WHERE id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($author_id);
    $stmt->fetch();
    $stmt->close();

    if ((int)$author_id !== $login_user['id']) {
        http_response_code(400);
        return;
    }

    // 以上がOKなら、削除する
    $stmt = $conn->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $stmt->close();

    http_response_code(204);
    return;
}