<?php
/**
 * 爬虫程序 -- 原型
 *
 * 从给定的url获取图片
 *
 * @param string $url
 * @return string
 */
function getUrlContent($url) {
	$handle = fopen($url, "r");
	if ($handle) {
		$content = stream_get_contents($handle, -1); //读取资源流到一个字符串,第二个参数需要读取的最大的字节数。默认是-1（读取全部的缓冲数据）
		return $content;
	} else {
		return false;
	}
}

/**
 * 爬虫
 *
 * @param string $url
 * @return array
 */
function crawler($url, $key_word) {
	$content = getUrlContent($url);
	$content_url = _filterUrl($content, $key_word);

	return $content_url;
}

/**
 * 将未匹配到的单词进行归纳，写入文件
 * @param  string $key_word 未匹配到的单词
 * @return null           
 */
function writeNoImgWord($key_word){
	$file_path = "words_no_match.txt";
	$no_match_file = fopen($file_path, "a");
	fwrite($no_match_file, $key_word."\n");
}

/**
 * 从html内容中筛选图片链接
 *
 * @param string $web_content
 * @return array
 */
function _filterUrl($web_content, $key_word) {
	$reg_tag_a = '/100vw\" srcSet=\"([^\"]+)\"/i';
	$result = preg_match_all($reg_tag_a, $web_content, $match_result);

	$all_url_arr = array();
	if ($result == 0) {
		echo "未搜索到图片" . "\n";
		writeNoImgWord($key_word);

		return;
	}

	// 
	// 最大匹配10个结果
	$match_pic_count = $result < 10 ? $result : 10;
	for ($x = 0; $x < $match_pic_count; $x++) {
		$url_cell = explode(",", $match_result[1][$x])[0];
		array_push($all_url_arr, $url_cell);
	}

	return $all_url_arr;
}

/**
 * 将检测到的URL地址，通过编辑，修改其规格，并且将连接转换为可下载链接
 * @param  array $content_url 
 * @return array              修正后的URL数组
 */
function fixURL($content_url) {

	$content_url = $content_url;
	$fixedURLArr = array();
	for ($x = 0; $x < count($content_url); $x++) {
		// 1: &amp; --> &
		$content_url[$x] = str_replace("&amp;", "&", $content_url[$x]);
		// 2: 删除 100w
		$content_url[$x] = explode(" ", $content_url[$x])[0];
		// 3: 添加 &dl=dog.jpg
		$content_url[$x] .= "&dl=dog.jpg";
		// 4: w=500 --> w=1024
		$content_url[$x] = str_replace("w=100", "w=1024", $content_url[$x]);
		array_push($fixedURLArr, $content_url[$x]);
	}

	//写修正后的url
	// writeURLArr($fixedURLArr, "fixed_url.txt");

	return $fixedURLArr;
}

/**
 * 下载图片
 * @param  string $pic_url   目标单词下载图片的url
 * @param  string $key_word  搜索的目标单词
 * @param  int $index     最佳图片的索引
 * @param  array &$abs_arr  待选图片宽高比组成数组
 * @param  string $save_path 保存图片的路径
 * @return null            
 */
function downloadPic($pic_url, $key_word, $index, &$abs_arr, $save_path = 'temp/') {
	$arrContextOptions = array(
		"ssl" => array(
			"verify_peer" => false,
			"verify_peer_name" => false,
		),
	);

	// 将整个文件读入一个字符串
	$content = file_get_contents($pic_url, false, stream_context_create($arrContextOptions));
	$file_name = $save_path . $key_word . $index . ".jpg";
	$scale_tar = 2; // 宽高目标比为2

	file_put_contents($file_name, $content); //将一个字符串写入文件

	list($width, $height) = getimagesize($file_name);

	$scale = $width / $height;
	$scale_abs = abs(($scale - $scale_tar));

	array_push($abs_arr, $scale_abs);
}

/**
 * 获取最佳匹配在数组中的index
 * @param  array $abs_arr 所有图片宽高比值组成的数组
 * @return int          最佳图片索引
 */
function findMinValueIndex($abs_arr) {
	foreach ($abs_arr as $key => $value) {
		if ($value == min($abs_arr)) {
			$min_value_index = $key;
		}
	}
	return $min_value_index;
}

/**
 * 移动目标图片
 * @param  int $min_index 目标图片索引
 * @param  string $key_word  图片名字
 */
function moveTargetFile($min_index, $key_word) {
	copy("temp/" . $key_word . $min_index . ".jpg", "target/" . $key_word . $min_index . ".jpg");
	rename("target/" . $key_word . $min_index . ".jpg", "target/" . $key_word . ".jpg");
}

/**
 * [initUrl description]
 * @param  [type] $key_word [description]
 * @return [type]           [description]
 */
function initUrl($key_word){

	$current_url = "https://unsplash.com/search/photos/" . $key_word; //初始url
	echo "*****request url:" . $current_url . "\n";
	return $current_url;
}


/**
 * 从本地文件读取数据
 * @return [type] [description]
 */
function crawlerGetWordsFromFile(){

	$words_file = "words.txt";
	$key_words = fopen($words_file, "r");
	while(!feof($key_words)) {
	   	$key_word = fgetss($key_words);
	   	$key_word = trim($key_word);
	  	$current_url = initUrl($key_word);
		$abs_arr = array();

		$contnet_result = crawler($current_url, $key_word);

		if (!$contnet_result) {
			// match failed
			continue;
		}

		$fixed_url_arr = fixURL($contnet_result);

		for ($x = 0; $x < count($fixed_url_arr); $x++) {
			// 下载图片
			downloadPic($fixed_url_arr[$x], $key_word, $x, $abs_arr);
		}

		$min_index = findMinValueIndex($abs_arr);

		// 移动目标图片
		moveTargetFile($min_index,$key_word);
	}
}

/**
 * 从数据库获取数据
 * @return [type] [description]
 */
function crawlerGetWordsFromDB(){
	$db_result = selectWords();
	echo "Row count=" . $db_result->num_rows . "\n";

	if ($db_result->num_rows > 0) {
		while ($row = $db_result->fetch_assoc()) {
			# code...
			$db_word = $row["word"];
			$key_word = trim($db_word);

			$initUrl =  initUrl($key_word);

			$abs_arr = array();

			$contnet_result = crawler($initUrl);

			if (!$contnet_result) {
				// match failed
				continue;
			}

			$fixed_url_arr = fixURL($contnet_result);

			for ($x = 0; $x < count($fixed_url_arr); $x++) {
				// 下载图片
				downloadPic($fixed_url_arr[$x], $key_word, $x, $abs_arr);
			}

			$min_index = findMinValueIndex($abs_arr);

			// 移动目标图片
			moveTargetFile($min_index,$key_word);
		}
	} else {
		echo "数据库获取数据失败" . "\n";
		return;
	}
}


/**
 * 数据库检索key_word
 * @return  数据库返回结果
 */
function selectWords() {
	$db_conn = connectDB();
	$sql = "SELECT word FROM wordmemo_word WHERE book_id = 15492";
	$result = $db_conn->query($sql);
	return $result;
}

/**
 * 连接数据库
 * @return mysqli 数据库连接对象
 */
function connectDB() {
	//连接数据库
	$dbhost = 'yourDbHost';
	$dbuser = 'userName';
	$dbpass = 'userPassword';
	$dbname = 'dbName';

	// $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
	$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

	if ($conn->connect_error) {
		# code...
		die('Could not connect: ' . mysqli_error());
	}
	echo "connect success" . "\n";
	return $conn;
}

/**
 * 传入一个数组，将该数组写入文件
 * @param  array $content_url 传入url数组
 * @param  string $file_name   要写入文件的路径
 * @return null              
 */
function writeURLArr($content_url, $file_name) {
	$file_path = $file_name;
	$fp_puts = fopen($file_path, "w"); //记录url列表

	$url_result_str = implode("\n", $content_url);
	fputs($fp_puts, $url_result_str);
}

/**
 * 传入一个字符串，将该字符串写入文件
 * @param  string $content_url 传入的单个URL是一个字符串
 * @param  string $file_name   要写入文件的路径
 * @return null              
 */
function writeURLStr($content_url, $file_name) {
	$file_path = $file_name;
	$fp_puts = fopen($file_path, "w"); //记录url列表

	fputs($fp_puts, $content_url);
}

/**
 * 测试用主程序
 */
function main() {
	
	crawlerGetWordsFromFile();

	// crawlerGetWordsFromDB();
}

main();

?>