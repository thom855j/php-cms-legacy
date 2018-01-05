<?php

$opts = getopt("", ["keep-db"]);
$keepDB = isset($opts["keep-db"]);

if (($id = array_search("--keep-db", $argv)) !== false) {
    var_dump($id);
    array_splice($argv, $id, 1);
}

if (!isset($argv[1])) { // name of the file
    $testFiles = [];
    function walkDir(string $dirStr)
    {
        global $testFiles;
        $dir = opendir($dirStr);
        while (($file = readdir($dir)) !== false) {
            if ($file !== "." && $file !== ".." && is_dir($file)) {
                walkDir("$dirStr/$file");
                continue;
            }
            if (preg_match("/test\.php$/i", $file) === 1) {
                $file = str_replace(__dir__ . "/", "", $dirStr . "/" . $file);
                $testFiles[] = $file;
            }
        }
        closedir($dir);
    }
    walkDir(__dir__);
    sort($testFiles); // for some reason they are not yet in alphabetical order ...

    $testFilesCount = count($testFiles);
    echo "Testing $testFilesCount files.\n";

    if ($keepDB) {
        require_once __dir__ . "/functions.php";
        // destroy and rebuild DB here
        // so that it is not done for all individual tests
        $testConfig = getConfig();
        $testDb = getTestDB();
        rebuildDB();
    }

    foreach ($testFiles as $id => $relativeFilePath) {
        // echo ($id + 1) . ") $relativeFilePath\n";
        echo ".";

        $strDropDB = $keepDB ? "--keep-db" : "";
        $result = shell_exec(PHP_BINARY . " " . __file__ . " $relativeFilePath $strDropDB");
        if (trim($result) !== "") {
            echo $result;
            exit;
        }
    }

    echo "\n\033[33;42m OK, all tests run successfully ! \033[m";
    exit;
}

if (!isset($argv[2])) { // name of the function
    // get all function names that begins by "test_"
    $content = file_get_contents($argv[1]);
    $matches = [];
    preg_match_all("/function (test_[a-z_]+)\(/i", $content, $matches);

    foreach ($matches[1] as $funcToRun) {
        $strDropDB = $keepDB ? "--keep-db" : "";
        $result = shell_exec(PHP_BINARY . " " . __file__ . " $argv[1] $funcToRun $strDropDB");
        if (trim($result) !== "") {
            echo $result;
            exit;
        }
    }
    exit;
}

// --------------------------------------------------
// setup

const IS_TEST = true;

require_once __dir__ . "/functions.php";

$testConfig = getConfig();

$testDb = getTestDB();

if ($keepDB) {
    $testDb->query("use `$testConfig[db_name]`");
} else {
    rebuildDB();
}

// create the first three users
$passwordHash = password_hash("Az3rty", PASSWORD_DEFAULT);
$testDb->query(
    "INSERT INTO users(name, email, email_token, password_hash, password_token, password_change_time, role, creation_date, is_banned) VALUES 
    ('admin', 'admin@email.com', '', '$passwordHash', '', 0, 'admin', '1970-01-01', 0), 
    ('writer', 'writer@email.com', '', '$passwordHash', '', 0, 'writer', '1970-01-02', 0), 
    ('commenter', 'com@email.com', '', '$passwordHash', '', 0, 'commenter', '1970-01-03', 0)"
);


$_SERVER["SERVER_PROTOCOL"] = "HTTP/1.1"; // needed/used by setHTTPHeader()
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["REQUEST_URI"] = "/index.php";
$_SERVER["SCRIPT_NAME"] = realpath(__dir__ . "/../public/index.php");

// --------------------------------------------------

require_once __dir__ . "/asserts.php";

session_start(); // session needs to start here instead of the front controller called from loadSite()
// mostly so that we can populate the $_SESSION superglobal

$currentTestFile = $argv[1];
require_once __dir__ . "/$currentTestFile";

$functionToRun = $argv[2];
$currentTestName = str_replace(["test_", "_"], ["", " "], $functionToRun);
$functionToRun();
