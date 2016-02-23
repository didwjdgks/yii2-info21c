<?php
namespace info21c\web;

use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\web\MultiFieldSession;

class DbSession extends MultiFieldSession
{
  public $db='db48';
  public $sessionTable='info21c_session';

  public function init(){
    parent::init();
    $this->db=Instance::ensure($this->db,Connection::className());
  }

  public function regenerateID($deleteOldSession=false){
    $oldID=session_id();

    // if no session is started, there is nothing to regenerate
    if(empty($oldID)) return;

    parent::regenerateID(false);
    $newID=session_id();

    $row=(new Query())->from($this->sessionTable)
      ->where(['session_id'=>$oldID])
      ->createCommand($this->db)
      ->queryOne();
    if($row!==false){
      if($deleteOldSession){
        $this->db->createCommand()
          ->update($this->sessionTable,['session_id'=>$newID],['session_id'=>$oldID])
          ->execute();
      }else{
        $row['session_id']=$newID;
        $this->db->createCommand()
          ->insert($this->sessionTable,$row)
          ->execute();
      }
    }else{
      $this->db->createCommand()
        ->insert($this->sessionTable,$this->composeFields($newID,''))
        ->execute();
    }
  }

  public function readSession($id){
    $query=(new Query())->from($this->sessionTable)
      ->where('(modified+lifetime)>:expire and session_id=:id',[':expire'=>time(),':id'=>$id]);
    
    $data=$query->select(['session_data'])->scalar($this->db);
    return $data===false ? '' : $data;
  }

  public function writeSession($id,$data){
    try{
      $query=new Query;
      $exists=$query->select(['session_id'])
        ->from($this->sessionTable)
        ->where(['session_id'=>$id])
        ->createCommand($this->db)
        ->queryScalar();
      if($exists!==false){
        $this->db->createCommand()
          ->update($this->sessionTable,['modified'=>time(),'session_data'=>$data],['session_id'=>$id])
          ->execute();
      }
    }catch(\Exception $e){
      $exception=\yii\web\ErrorHandler::convertExceptionToString($e);
      // its too late to use Yii logging here
      error_log($exception);
      echo $exception;

      return false;
    }

    return true;
  }

  public function destorySession($id){
    $this->db->createCommand()
      ->delete($this->sessionTable,['session_id'=>$id])
      ->execute();

    return true;
  }

  public function gcSession($maxLifetime){
    return true;
  }
}

