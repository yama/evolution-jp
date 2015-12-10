<?php
/*
 * API for Element base 
 *
 * リソースやスニペット制御用の基底Class
 *
 */

class ElementBase
{
	const LOG_INFO = 1;
	const LOG_WARN = 2;
	const LOG_ERR  = 3;

	public static $modx=null; //MODXオブジェクトを指定しないとこのクラスは動作しません

	private $logLevel    = self::LOG_ERR; // Output log level
	private $APIName     = 'ElementBase'; //APIの名前
	private $elementType = ''; //エレメントの種類
	
	/*
	 * __construct
	 *
	 * @param $id    リソースID(blank=New resource)
	 * @param $status 読み込むリソースのステータス(新規の時は利用されない)
	 * @param $level ログレベル
	 * @return none
	 *
	 */
	public function __construct($elm,$name='',$level=''){
		$this->elementType = $elm;
		if( !empty($name) )
			$this->APIName = $name;
		
		if( self::isInt($level,1) )
			$this->logLevel = $level;
	}

	//追加予定メソッド
	//loadRevision()   … 読み込み
	//saveRevision()   … 保存(Update)
	//addRevision()    … 追加
	//eraseRevision()   … 削除
	//getRevisionList() … リビジョンのリスト
	//getRevisionInfo() … 指定リビジョン情報


	
	/*
	 * logging / loginfo / logwarn / logerr
	 *
	 * @param level Log level
	 * @param msg Log massages
	 * @return bool   
	 *
	 */
	protected function logging($level,$msg=''){
		$this->lastLog = $msg;
		if( $this->logLevel <= $level )
			parent::$modx->logEvent(4,$level,$msg,$this->APIName);
	}
	
	protected function loginfo($msg=''){
		$this->logging(self::LOG_INFO,$msg);   
	}
	
	protected function logwarn($msg=''){
		$this->logging(self::LOG_WARN,$msg);   
	}
	
	protected function logerr($msg=''){
		$this->logging(self::LOG_ERR,$msg);   
	}

	//--- Static function
	/*
	 * Number check
	 *
	 * @param $param Input data
	 * @param $min   Minimum value
	 * @param $max   Maximum value
	 * @return bool
	 *
	 */
	protected  static function isInt($param,$min=null,$max=null){
		if( !preg_match('/\A[0-9]+\z/', $param) ){
			return false;
		}
		if( !is_null($min) && preg_match('/\A[0-9]+\z/', $min) && $param < $min ){
			return false;
		}
		if( !is_null($max) && preg_match('/\A[0-9]+\z/', $max) && $param > $max ){
			return false;
		}
		return true;
	}  

	/*
	 * bool型をIntに変換
	 *
	 * DBに登録できるようboolを0/1に変換。
	 * $paramに1/0が渡ってきた場合はそのまま返す。
	 * 認識できない$paramはすべて 0 とする。
	 *
	 * @param $param bool or 0/1
	 * @return 0/1
	 *
	 */
	protected static function bool2Int($param){
		if( $param === true || $param == 1 ){
			return 1;
		}
		return 0;
	}

}
