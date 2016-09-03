<?php
/**
 * カタログからリストを作成 Ver.16Q3
 */

//定数設定
define('SOURCEMODE', '2chan');// ソース選択。2chanでふたば本家のカタログ、nijiranで虹覧からカタログ取得。通常は2chanでOK。

define('SOURCE_URL_2CHAN', 'http://jun.2chan.net/b/futaba.php?mode=cat');// ソースのURLを指定(本家)
define('SOURCE_URL_NIJIRAN', 'http://nijiran.hobby-site.org/nijiran/fCatalog_JUN.html');// ソースのURLを指定(虹覧)
define('LIST_THRESHOLD', 50);// リスト掲載の閾値を設定。現在は50レスに設定
define('THREAD_PREFIX', 'http://jun.2chan.net/b/');// 完全修飾URLでない場合の接頭辞（主に本家用）
define('LIST_FILE', './crawl_list.lst');// リストファイルの名前
define('RUN_MODE', 'web');//実行モード切替：webからの実行を許可する場合はweb、コンソールのみはcli

//webからの起動を拒否する
if(RUN_MODE == 'cli'){
	if(php_sapi_name() != 'cli'){
		header('HTTP/1.1 403 Forbidden', true, 403);
		header('Content-type:text/plain; Charset=utf-8');
		exit("これはコンソールアプリです。web経由での呼び出しは禁止されています。\n");
	}
}elseif(RUN_MODE == 'web'){
	header('Content-type:text/html; Charset=utf-8');
}

//error_report エラー報告を行う。可能な限り処理を続行する
function error_report($str){
	echo "ERROR:\n";
	echo ">{$str}\n";
}
//error_halt エラー報告を行う。その場で処理を終了する。
function error_halt($str){
	echo "ERROR:\n";
	echo ">{$str}\n";
	exit;
}
//info_report 
function info_report($str){
	echo "SYSTEM MESSAGE:\n";
	echo ">{$str}\n";
}

//カタログページ取得：取得元振り分けと実際の取得
$catalogpage = null;
switch (SOURCEMODE) {
	case '2chan':
		$catalogpage = file_get_contents(SOURCE_URL_2CHAN) or error_halt('Connection Failed');
		$catalogpage = mb_convert_encoding($catalogpage, 'utf8', 'sjis-win');
		break;
	case 'nijiran':
		$catalogpage = file_get_contents(SOURCE_URL_NIJIRAN) or error_halt('Connection Failed');
		break;
	default:
		$catalogpage = false;
		break;
}
//カタログページ取得：エラー処理
if($catalogpage === false){
	//空文字と区別の為、厳密な型比較を行う。
	error_halt('SOURCEMODEが未定義、もしくはカタログサーバーでエラーが発生しています。');
}

//リスト作成：取得したHTMLを解析する：接続先に合わせて解析方法を変える
$thread_list = array();
require('simple_html_dom.php');
switch (SOURCEMODE) {
	case '2chan':
		//本家カタログの場合はcookie未設定時（初期状態）で表示されるスレを取得する
		$dom = str_get_html($catalogpage);
		foreach($dom->find('td') as $element){
			$url = THREAD_PREFIX . $element->find('a', 0)->href;
			$count = $element->find('font', 0)->innertext;
			if($count >= LIST_THRESHOLD){
				$list[] = $url;
			}
		}
		break;
	case 'nijiran':
		//虹覧の場合は「レスが少ないスレ」を取得しない
		$dom = str_get_html($catalogpage);
		$area = $dom->find('table[class=nom]', 0);
		foreach($area->find('td[id]') as $element){
			//虹覧はtdタグが不完全で子要素を明示的に指定できないため、暗示的に第１子要素（divタグ）を選択し、そこから各要素を選択する
			$url = $element->children(0)->find('a', 0)->href;
			$count = $element->children(0)->find('span[class=cow]', 0)->innertext;
			if($count >= LIST_THRESHOLD){
				$list[] = $url;
			}
		}
		break;
	default:
		//上の分岐で既にhaltをかけているためここでは処理なし。
		break;
}

//出来上がったlistをテキストベースにエンコード
$text = implode("\n", $list);
//エンコードしたテキストベースをファイルに書き込み
file_put_contents(LIST_FILE, $text) or error_halt('クロールリストファイルの書き込みに失敗しました。');
//書き込みでエラーが出なかったら成功メッセージを表示。
info_report("クロールリストファイルの書き込みが完了しました。");
?>
