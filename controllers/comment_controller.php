<?php

/**
 * ある記事に投稿されたコメントの一覧を取得する
 */
function get_comments($conn, $article_id)
{
    $stmt = $conn->prepare("SELECT * FROM comments WHERE article_id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        return json_encode([]);
    }

    $response_data = [];
    while ($row = $result->fetch_assoc()) {
        $response_data[] = [
            'id' => $row['id'],
            'article_id' => $row['article_id'],
            'user_id' => $row['user_id'],
            'body' => $row['body'],
        ];
    }

    return json_encode($response_data);
}

/**
 * ある記事に対して、コメントを投稿する
 */
function create_comments($conn, $article_id, $request, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    $body = $request['body'];

    // バリデーション
    if (empty($body)) {
        http_response_code(400);
        return json_encode([
            'message' => 'コメントの本文は、必須項目です'
        ]);
    }

    // "プリペアードステートメント"でググる
    $stmt = $conn->prepare("INSERT INTO comments (article_id, user_id, body) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $article_id, $login_user['id'], $body);
    $stmt->execute();
    $stmt->close();

    http_response_code(201);
    return;
}

/**
 * ある記事に投稿されたコメントの詳細を取得
 */
function get_comment_detail($conn, $comment_id)
{
    $stmt = $conn->prepare("SELECT id, article_id, user_id, body FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $stmt->bind_result($id, $article_id, $user_id, $body); // 変な書き方な気がするけどな
    $stmt->fetch();
    $stmt->close();

    return json_encode([
        'id' => $id,
        'article_id' => $article_id,
        'user_id' => $user_id,
        'body' => $body
    ]);
}

/**
 * コメント内容の更新
 */
function update_comment($conn, $comment_id, $request, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    $body = $request['body'];

    // バリデーション
    if (empty($body)) {
        http_response_code(400);
        return json_encode([
            'message' => 'コメントの本文は、必須項目です'
        ]);
    }

    // コメントの投稿主とログインユーザーが一致しているか確認する
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
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
    $stmt = $conn->prepare("UPDATE comments SET body = ? WHERE id = ?");
    $stmt->bind_param("si", $body, $comment_id);
    $stmt->execute();
    $stmt->close();

    return json_encode([
        'id' => $comment_id,
        'user_id' => $author_id,
        'body' => $body
    ]);
}

/**
 * コメントを削除
 */
function delete_comment($conn, $comment_id, $login_user)
{
    // ログインユーザー情報をチェック
    if (empty($login_user) || empty($login_user['id'])) {
        http_response_code(400);
        return;
    }

    // 記事の投稿主とログインユーザーが一致しているか確認する
    $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
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
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $stmt->close();

    http_response_code(204);
    return;
}