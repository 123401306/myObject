<?php

namespace App\Model\alidayu;

/**
 * 短信请求
 * 
 */

class sms
{
    //电话
    public $setMobile;
    //参数
    public $setParam;
    //短信模板
    public $setTemplateCode;
    //返回参数
    public $setExtend;
    //短信类型
    public $setType='normal';
    //文本转语音
    public $setCall=false;
    //通用验证码
    public $verificationCode = 1000;
    //签名
    public $setSingName = "淘拍拍";

    //初始化
    public function __construct()
    {

    }

    /**
     * 阿里大鱼信息发送
     */
    public function sendChose()
    {
        if($this->setCall){
            return $this->mobileCall();   
        }
        return $this->mobileMessage();
    }

    //阿里大鱼短信接口请求
    public function mobileMessage()
    {
        $c = new TopClient;
        $c->appkey = env('ALKEY');
        $c->secretKey = env('ALSECRET');
        $req = $c->sendRequest();
        $req->setExtend($this->setExtend);
        $req->setSmsType($this->setType);
        $req->setSmsFreeSignName($this->setSingName);
        $req->setSmsParam(json_encode($this->setParam));
        $req->setRecNum($this->setMobile);
        $req->setSmsTemplateCode($this->setTemplateCode);
        $resp = $c->execute($req);
        if(isset($resp->result) && $resp->result->success){
            return 1;
        }else{
            return $resp;
        }
    }  

    /**
     * 阿里大鱼短信发送
     * 发送短信，并把结果录入数据库
     * @param string $mobile 电话号码
     * @param array  $content  内容
     */
    public function send($phone,$param,$template,$uid=0,$type='')
    {
        //模板设置
        $this->setTemplateCode = $template;
        //短信模板选择(验证码)
        if($param == 'code'){
            //短信验证码
            unset($param);
            $code = rand(1000, 9999);
            $param['code'] = (string)$code;
        }

        //
        if(isset($param['tel']) && $param['tel']){
            $param['tel'] = $this->tel;
        }

        //短信群发数量判断 每次最多发送200个手机.
        $checkPhone = explode(',',$phone);
        if(count($checkPhone) > 200){
            //向上取整
            $i = 0;
            $k = 0;

            foreach ( $checkPhone as $value){
                $arrPhone [$k][$i] = $value;
                $i++;
                //每200个一组
                if($k % 199 == 0){
                    $k++;
                }
            }
            //每200个一组 发送短信
            foreach ($arrPhone as $value){
                $phone = implode(',',$value);
                $this->sendMsg($phone,$param);
            }
        }else{
            return $this->sendMsg($phone,$param);
        }
    }

    //阿里大鱼文本转语音
    public function mobileCall()
    {

    }

    //发送
    public function sendMsg($phone,$param)
    {
        $this->setParam = $param;
        $this->setMobile = $phone;
        $v=$this->sendChose();
        if($v===1) {
            return ['status'=>'success','code'=>200,'msg'=>$this->content($this->setTemplateCode,$param)];
        }else{
            $content="发送短信失败，手机:".$phone."，错误代码：" .$v->code. " 错误信息:" . $v->msg .",". $v->sub_msg .",错误id:" . $v->request_id;
            return ['status'=>'success','code'=>10001,'msg'=>$content];
        }
    }

    //阿里大鱼短信模板内容
    public function content($key,array $array)
    {
        error_reporting(0);
        $template['SMS_12445723'] = "亲爱哒，恭喜您参与我们的0元定金活动您的订单号".$array['oid']."，拍摄满意后查看到底片时支付全款，谢谢您的信任！";
        return $template[$key];
    }
}
