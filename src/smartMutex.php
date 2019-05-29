<?php
//Yii框架下的互斥量组件,利用redis模拟互斥量
namespace smartCode\smartMutex;
//====================================================
class smartMutex{
	//yii框架的redis连接句柄
	private $redis=NULL;
	//缓存进redis的前缀
	private $cachePrefix=NULL;
	//缓存进redis的超时秒数
	private $cacheTimeout=NULL;
	//加锁的尝试次数
	private $lockTryTimes=NULL;
	//当前进程加锁成功的互斥量集合
	private $mutexList=array();
	//====================================================
	//构造
	public function __construct($redis,$cPrefix='smartCode_smartMutex',$cTimeout=15,$tTimes=10){
		$this->redis=$redis;
		$this->cachePrefix=$cPrefix;
		$this->cacheTimeout=$cTimeout;
		$this->lockTryTimes=$tTimes;
	}
	//====================================================
	//析构
	public function __destruct(){
		//在析构时释放所有当前进程加锁成功的互斥量
		foreach($this->mutexList as $key=>$val) $this->release($key);
	}
	//====================================================
	//取得加工后的互斥量缓存key
	private function getCacheKey($key){return "{$this->cachePrefix}_{$key}";}
	//====================================================
	//加锁互斥量
	private function lock($key){
		//生成key
		$lockKey=$this->getCacheKey($key);
		//尝试加锁
		$this->redis->MULTI();//原子操作
		$this->redis->SETNX($lockKey,1);//设置key
		$this->redis->EXPIRE($lockKey,$this->cacheTimeout);//15秒超时
		$re=$this->redis->EXEC();//执行
		//加锁失败
		if($re[0]!=1) return false;
		//加锁成功,加入互斥量池
		$this->mutexList[$key]=true;
		//返回成功
		return true;
	}
	//====================================================
	//尝试加锁互斥量
	public function tryLock($key){
		$count=0;
		$re=false;
		while(!$re && ++$count<$this->lockTryTimes) $re=$this->lock($key);
		return $re;
	}
	//====================================================
	//解锁
	public function release($key){
		//生成key
		$lockKey=$this->getCacheKey($key);
		//没有锁直接返回成功
		if(!isset($this->mutexList[$key])) return;
		//删除key
		Yii::$app->redis->DEL($lockKey);
		//从互斥量池去除
		unset($this->mutexList[$key]);
	}
}