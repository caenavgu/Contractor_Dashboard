<?php
declare(strict_types=1);
header('Content-Type: text/plain');

echo "METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . PHP_EOL;
echo "CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? '0') . PHP_EOL;
echo "post_max_size: " . ini_get('post_max_size') . PHP_EOL;
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . PHP_EOL;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo PHP_EOL . "--- \$_POST ---" . PHP_EOL;
    var_dump($_POST);

    echo PHP_EOL . "--- \$_FILES ---" . PHP_EOL;
    var_dump($_FILES);
} else {
    ?>
    <form method="post" enctype="multipart/form-data">
      <input type="text" name="email" placeholder="email"><br>
      <input type="file" name="file"><br>
      <button type="submit">POST</button>
    </form>
    <?php
}
