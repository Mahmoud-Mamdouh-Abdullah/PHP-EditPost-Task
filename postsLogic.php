<?php

use function PHPSTORM_META\type;

require_once(BASE_PATH . '/DAL/basic_dal.php');

function getPosts(
    $posts_count,
    $page = 1,
    $category_id = null,
    $tag_id = null,
    $user_id = null,
    $q = null,
    $order_field = "publish_date",
    $order_by = "desc"
) {
    $offset = ($page - 1) * $posts_count;

    $sql = "SELECT p.*, c.name as c_name, u.username as username
    FROM posts p
    JOIN categories c
    ON c.id = p.category_id
    JOIN users u
    ON u.id = p.user_id
    WHERE 1=1";

    $types = '';
    $vals = [];
    $sql = addWhereConditions($sql, $category_id, $tag_id, $user_id, $q, $types, $vals);
    $sql .= " ORDER BY $order_field $order_by LIMIT $offset,$posts_count;";


    $posts = getRows($sql, $types, $vals);
    for ($i = 0; $i < count($posts); $i++) {
        $posts[$i]['number_of_comment'] = getPostComments($posts[$i]['id']);
        $posts[$i]['tags'] = getPostTags($posts[$i]['id']);
    }
    return $posts;
}

function getPostCount($category_id = null, $tag_id = null, $user_id = null, $q = null)
{
    $sql = "SELECT COUNT(0) AS posts_counts FROM posts p
    WHERE 1=1";
    $types = '';
    $vals = [];
    $sql = addWhereConditions($sql, $category_id, $tag_id, $user_id, $q, $types, $vals);
    $result = getRow($sql, $types, $vals);
    if ($result == null) return 0;
    return $result['posts_counts'];
}

function addWhereConditions($sql, $category_id = null, $tag_id = null, $user_id = null, $q = null, &$types, &$vals)
{
    if ($category_id != null) {
        $types .= 'i';
        array_push($vals, $category_id);
        $sql .= " AND category_id=?";
    }
    if ($user_id != null) {
        $types .= 'i';
        array_push($vals, $user_id);
        $sql .= " AND user_id=?";
    }
    if ($tag_id != null) {
        $types .= 'i';
        array_push($vals, $tag_id);
        $sql .= " AND p.id IN (SELECT post_id FROM post_tags WHERE tag_id=?)";
    }
    if ($q != null) {
        $types .= 'ss';
        array_push($vals, '%' . $q . '%');
        array_push($vals, '%' . $q . '%');
        $sql .= " AND (title like ? OR content like ?)";
    }
    return $sql;
}

function getMyPosts($page_size, $page, $user_id, $q, $order_field, $order_by)
{
    return [
        'data' => getPosts($page_size, $page, null, null, $user_id, $q, $order_field, $order_by),
        'count' => getPostCount(null, null, $user_id, $q)
    ];
}


function getPostComments($post_id)
{
    $sql = "SELECT COUNT(0) AS number_of_comment FROM comments WHERE post_id = $post_id;";
    $result = getRow($sql);
    if ($result == null) return 0;
    return $result['number_of_comment'];
}

function getPostTags($post_id)
{
    $sql = "SELECT t.id, t.name FROM post_tags pt
            JOIN tags t 
            ON t.id = pt.tag_id
            WHERE pt.post_id = $post_id;";

    return getRows($sql);
}

function getPostByID($post_id)
{
    $sql = "SELECT * FROM posts WHERE id=?";
    $post = getRow($sql, 'i', [$post_id]);
    $sql = "SELECT * FROM post_tags WHERE post_id=?";
    $post['tags'] = getRows($sql, 'i', [$post_id]);
    return $post;
}

function validatePostCreate($request)
{
    $errors = [];
    return $errors;
}
function addNewPost($request, $user_id, $image)
{
    $sql = "INSERT INTO posts(id,title,content,image,publish_date,category_id,user_id)
    VALUES (null,?,?,?,?,?,?)";
    $post_id = addData($sql, 'ssssii', [
        $request['title'],
        $request['content'],
        $image,
        $request['publish_date'],
        $request['category_id'],
        $user_id
    ]);
    if ($post_id) {
        if (isset($request['tags'])) {
            foreach ($request['tags'] as $tag_id) {
                addData(
                    "INSERT INTO post_tags (post_id,tag_id) VALUES (?,?)",
                    'ii',
                    [$post_id, $tag_id]
                );
            }
        }
        return true;
    }
    return false;
}

function editPost($post_id, $request, $image)
{
    $sql = "UPDATE `posts` SET title = ?, content = ?, publish_date = ?, category_id = ?";
    $types = 'sssi';
    $vals = [
        $request['title'],
        $request['content'],
        $request['publish_date'],
        $request['category_id']
    ];
    if ($image != null) {
        $sql .= ", image = ?";
        $types .= 's';
        array_push($vals, $image);
    }
    $sql .= " WHERE id = ?;";
    $types .= 'i';
    array_push($vals, $post_id);
    editData($sql, $types, $vals);

    $sql = "DELETE FROM `post_tags` WHERE post_id = ?";
    deleteData($sql, 'i', [$post_id]);

    if (isset($request['tags'])) {
        foreach ($request['tags'] as $tag_id) {
            addData(
                "INSERT INTO post_tags (post_id,tag_id) VALUES (?,?)",
                'ii',
                [$post_id, $tag_id]
            );
        }
    }
}

function checkIfUserCanEditPost($post)
{
    if (session_status() != PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['user']))
        return false;
    return $_SESSION['user']['type'] == 1 || $_SESSION['user']['id'] == $post['user_id'];
}

function getUploadedImage($files)
{
    move_uploaded_file($files['image']['tmp_name'], BASE_PATH . '/post_images/' . $files['image']['name']);
    return $files['image']['name'];
}
