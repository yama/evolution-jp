<?php
/*
 * API for Element base 
 *
 * リソースやスニペット制御用の基底Class
 *
 */

class ElementBase
{
	//リソースのステータス一覧
	const ST_NEW      = 'new';
	const ST_RELEASED = 'released';
	const ST_DRAFT    = 'draft';
	const ST_STANDBY  = 'standby';

	//ログレベル
	const LOG_INFO = 1;
	const LOG_WARN = 2;
	const LOG_ERR  = 3;

	const MAX_BUFF = 255; //文字列の最大長

	public static $modx=null; //MODXオブジェクトを指定しないとこのクラスは動作しません

	private $APIName     = 'ElementBase';  //APIの名前
	private $logLevel    = self::LOG_ERR;  //Output log level
	private $elementType = 'resource';     //エレメントの種類
	private $status      = self::ST_NEW;   //状態
	private $elmid       = 0;              //エレメントID(保存時に必須)
	private $description = '';             //概要
	private $content     = '';             //履歴情報
	private $pub_date    = 0;              //採用日
	
	/*
	 * __construct
	 *
	 * @param $name   継承先のクラスの名前
	 * @param $elm    エレメントの種類
	 * @param $id     リソースID(blank=New resource)
	 * @param $level ログレベル
	 * @return none
	 *
	 */
	public function __construct($name,$elm,$id=0,$level=''){
		if( !empty($name) )
			$this->APIName = $name;

		$this->elementType = $elm;

		if( self::isInt($id,1) ){
			$this->elmid = $id;
			$this->setStatus(self::ST_RELEASED);
		}

		if( self::isInt($level,1) )
			$this->logLevel = $level;
	}

	/*
	 * エレメントID設定
	 *
	 * 連動してステータスも変更される。
	 *
	 * @param $id エレメントID(デフォルト0)
	 * @return bool
	 *
	 */
	public function setElementId($id=0){
		if( empty($id) ){
			$this->elmid = 0;
			$this->setStatus(self::ST_NEW);
		}else{
			if( self::isInt($id,1) ){
				$this->elmid = $id;
				if( $this->getStatus() == self::ST_NEW ){
					$this->setStatus(self::ST_RELEASED);
				}
			}else{
				$this->logWarn('IDの値が不正です。');
				return false;
			}

		}
		return true;
	}

	/*
	 * エレメントID取得
	 *
	 * @return int
	 *
	 */
	public function getElementId(){
		return $this->elmid;
	}

	/*
	 * ステータス設定
	 *
	 * ステータスを設定する
	 *
	 * @param $status エレメントのステータス
	 * @return bool
	 *
	 */
	public function setStatus($status){
		switch($status){
		case self::ST_NEW:
			if( $this->elmid == 0 ){
				$this->status = $status;
				return true;
			}else{
				$this->logWarn('エレメントIDが設定されているのでステータスはNEWに変更できません。');
			}
			break;			
		case self::ST_RELEASED:
		case self::ST_DRAFT:
		case self::ST_STANDBY:
			if( self::isInt($this->elmid,1) ){
				$this->status = $status;
				return true;
			}else{
				$this->logWarn('エレメントIDが設定されていません。');
			}
			break;
		}
		return false;
	}

	/*
	 * ステータス取得
	 *
	 * ステータスを取得する
	 *
	 * @return string
	 *
	 */
	public function getStatus(){
		return $this->status;
	}

	/*
	 * 概要設定
	 *
	 * @param $str 概要文
	 * @return bool
	 *
	 */
	public function setDescription($str=''){
		if( mb_strlen($str) > self::MAX_BUFF ){
			$this->logWarn('Descriptionが長すぎます。最大文字長:'.self::MAX_BUFF);
			return false;
		}
		$this->description = $str;
		return true;
	}

	/*
	 * 概要取得
	 *
	 * @return string
	 *
	 */
	public function getDescription(){
		return $this->description;
	}

	/*
	 * コンテンツ(履歴情報)設定
	 *
	 * 配列形式で受け取ったものをserializeして保管します。
	 * 汎用的に利用するので個別加工のような処理は原則行いません。
	 * (エラーチェックもなし)
	 *
	 * @param $content コンテンツ情報
	 * @return bool
	 *
	 */
	public function setContent($content=array()){
		$this->content = serialize($content);
		return true;
	}

	/*
	 * コンテンツ(履歴情報)取得
	 *
	 * @return array/false
	 *
	 */
	public function getContent(){
		if( empty($this->content) ){
			return false;
		}
		return unserialize($this->content);
	}

	/*
	 * 公開(採用)日設定
	 *
	 * ステータスも同時に修正されます。
	 * 0の場合、公開日がリセットされます。
	 *
	 * @param $date 公開(採用)日
	 * @return bool
	 *
	 */
	public function setPubDate($date=0){
		if( empty($date) ){
			$this->pub_date = 0;
			$this->setStatus(self::ST_NEW);
		}else{
			if( self::isInt($date,1) ){
				$this->pub_date = $date;
				$this->setStatus(self::ST_STANDBY);
			}else{
				$this->logWarn('公開(採用)日の値が不正です。');
				return false;
			}
		}
		return true;
	}

	/*
	 * 公開日取得
	 *
	 * フォーマット指定があると親切かも。
	 *
	 * @return int
	 *
	 */
	public function getPubDate(){
		return $this->pub_date;
	}

	/*
	 * リビジョン追加
	 *
	 * @param $xxx
	 * @return none
	 *
	 */
	//public function addRevision($xxx){
	//}

	//追加予定メソッド
	//loadRevision()   … 読み込み
	//saveRevision()   … 保存(Update)
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
