<?php

use \Firebase\JWT\JWT;

/**
 * ユーザー登録
 */
function register_user($conn, $request) {
    $email = $request['email'];
    $password = $request['password'];

    // バリデーション
    if (empty($email) || empty($password)) {
        http_response_code(400);
        return json_encode([
            'message' => 'メールアドレスとパスワードは必須です'
        ]);
    }

    // 要注意！！
    // 今回は省略しているが、この時点では、『仮登録』状態とかにしておいて
    // 本登録用のリンクを記載したメールを送信したりするのが良いかなと思います。
    // 今回はめんどくさいので、省略しています。
    // 頑張って調べて、実装してみようね！
    // このままだと、赤の他人のメアドでユーザー登録できるので、7pay的な感じになっちゃいます。

    // パスワードのハッシュ化。この辺のは公式読もう
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $hashed_password); // ハッシュ化したパスワードを入れる!!
    $stmt->execute();
    $stmt->close();

    http_response_code(201);
    return;
}

/**
 * ログイン
 */
function login_user($conn, $request) {
    $email = $request['email'];
    $password = $request['password'];

    // バリデーション
    if (empty($email) || empty($password)) {
        http_response_code(400);
        return json_encode([
            'message' => 'メールアドレスとパスワードは必須です'
        ]);
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($password, $hashed_password)) {
        http_response_code(400);
        return json_encode([
            'message' => 'メールアドレスかパスワードが正しくありません',
        ]);
    }

    // トークンの寿命設定
    $nbf = time(); // トークンが有効になる日時(普通はnowだよな)
    $exp = time() + (60 * 60); // トークンの期限を一時間後に
    $iat = time(); // トークンの発行日を”今”に設定
    $payload = [
        'iss' => ISSUER,
        'iat' => $iat,
        'nbf' => $nbf,
        'exp' => $exp,
        'email' => $email
    ];

    // トークンを生成
    $jwt = JWT::encode($payload, SERVER_KEY);

    return json_encode([
        'token' => $jwt
    ]);
}

/**
 * トークンを検証して、ログインユーザーのデータを配列で返す
 */
function token_verify($conn) {
    if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
        list($type, $token) = explode(" ", $_SERVER["HTTP_AUTHORIZATION"], 2);

        if (strcasecmp($type, "Bearer") == 0) {
            // JWT::decodeは上手く行かないことがある
            try {
                $decoded_token = JWT::decode($token, SERVER_KEY, ['HS256']);
            } catch (Exception $e) {
                http_response_code(404);
                echo json_encode([
                    'message' => $e->getMessage()
                ]);
                $conn->close();
                exit();
            }

            $email = $decoded_token->email;

            $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($user_id, $email);
            $stmt->fetch();
            $stmt->close();

            return [
                'id' => $user_id,
                'email' => $email
            ];
        }
    }
}