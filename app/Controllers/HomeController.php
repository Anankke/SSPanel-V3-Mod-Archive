<?php

namespace App\Controllers;

use App\Models\InviteCode;
use App\Models\User;
use App\Models\Code;
use App\Models\Payback;
use App\Models\Paylist;
use App\Services\Auth;
use App\Services\Config;
use App\Utils\Tools;
use App\Utils\Telegram;

/**
 *  HomeController
 */
class HomeController extends BaseController
{

    public function index()
    {
        return $this->view()->display('index.tpl');
    }

    public function code()
    {
        $codes = InviteCode::where('user_id', '=', '0')->take(10)->get();
        return $this->view()->assign('codes', $codes)->display('code.tpl');
    }

    public function down()
    {

    }

    public function tos()
    {
        return $this->view()->display('tos.tpl');
    }
	
	public function staff()
    {
        return $this->view()->display('staff.tpl');
    }
	
	
	public function telegram()
    {
		try {
			$bot = new \TelegramBot\Api\Client(Config::get('telegram_token'));
			// or initialize with botan.io tracker api key
			// $bot = new \TelegramBot\Api\Client('YOUR_BOT_API_TOKEN', 'YOUR_BOTAN_TRACKER_API_KEY');

			$bot->command('ping', function ($message) use ($bot) {
				$bot->sendMessage($message->getChat()->getId(), 'Pong!这个群组的 ID 是 '.$message->getChat()->getId().'!');
			});

			$bot->run();

		} catch (\TelegramBot\Api\Exception $e) {
			$e->getMessage();
		}
    }
	
	public function page404($request, $response, $args)
    {
		$pics=scandir(BASE_PATH."/public/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/404/");
		
		if(count($pics)>2)
		{
			$pic=$pics[rand(2,count($pics)-1)];
		}
		else
		{
			$pic="4041.png";
		}
		
		$newResponse = $response->withStatus(404);
		$newResponse->getBody()->write($this->view()->assign("pic","/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/404/".$pic)->display('404.tpl'));
        return $newResponse;
    }
	
	public function page405($request, $response, $args)
    {
        $pics=scandir(BASE_PATH."/public/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/405/");
		if(count($pics)>2)
		{
			$pic=$pics[rand(2,count($pics)-1)];
		}
		else
		{
			$pic="4051.png";
		}
		
		$newResponse = $response->withStatus(405);
		$newResponse->getBody()->write($this->view()->assign("pic","/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/405/".$pic)->display('405.tpl'));
        return $newResponse;
    }
	
	public function page500($request, $response, $args)
    {
        $pics=scandir(BASE_PATH."/public/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/500/");
		if(count($pics)>2)
		{
			$pic=$pics[rand(2,count($pics)-1)];
		}
		else
		{
			$pic="5001.png";
		}
		
		$newResponse = $response->withStatus(500);
		$newResponse->getBody()->write($this->view()->assign("pic","/theme/".(Auth::getUser()->isLogin==false?Config::get("theme"):Auth::getUser()->theme)."/images/error/500/".$pic)->display('500.tpl'));
        return $newResponse;
    }
	
	public function pmw_pingback($request, $response, $args)
    {
		
		if(Config::get('pmw_publickey')!="")
		{
			\Paymentwall_Config::getInstance()->set(array(
				'api_type' => \Paymentwall_Config::API_VC,
				'public_key' => Config::get('pmw_publickey'),
				'private_key' => Config::get('pmw_privatekey')
			));
			
			
			
			$pingback = new \Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
			if ($pingback->validate()) {
				$virtualCurrency = $pingback->getVirtualCurrencyAmount();
				if ($pingback->isDeliverable()) {
				// deliver the virtual currency
				} else if ($pingback->isCancelable()) {
				// withdraw the virual currency
				} 
				
				$user=User::find($pingback->getUserId());
				$user->money=$user->money+$pingback->getVirtualCurrencyAmount();
				$user->save();
				
				$codeq=new Code();
				$codeq->code="Payment Wall 充值";
				$codeq->isused=1;
				$codeq->type=-1;
				$codeq->number=$pingback->getVirtualCurrencyAmount();
				$codeq->usedatetime=date("Y-m-d H:i:s");
				$codeq->userid=$user->id;
				$codeq->save();
			  
			  
				
				
				if($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=NULL)
				{
					$gift_user=User::where("id","=",$user->ref_by)->first();
					$gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
					$gift_user->save();
					
					$Payback=new Payback();
					$Payback->total=$pingback->getVirtualCurrencyAmount();
					$Payback->userid=$user->id;
					$Payback->ref_by=$user->ref_by;
					$Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
					$Payback->datetime=time();
					$Payback->save();
					
				}
			  
			  
			  
				echo 'OK'; // Paymentwall expects response to be OK, otherwise the pingback will be resent
				
				
				if(Config::get('enable_donate') == 'true')
				{
					if($user->is_hide == 1)
					{
						Telegram::Send("姐姐姐姐，一位不愿透露姓名的大老爷给我们捐了 ".$codeq->number." 元呢~");
					}
					else
					{
						Telegram::Send("姐姐姐姐，".$user->user_name." 大老爷给我们捐了 ".$codeq->number." 元呢~");
					}
				}
			
			
			} else {
				echo $pingback->getErrorSummary();
			}
		}
		else
		{
			echo 'error';
		}
    }

	public function alipay_callback($request, $response, $args)
    {
        require_once(BASE_PATH."/alipay/alipay.config.php");
		require_once(BASE_PATH."/alipay/lib/alipay_notify.class.php");
		
		
		if(Config::get('enable_alipay')!='false')
		{
			//计算得出通知验证结果
			$alipayNotify = new \SPAYNotify($alipay_config);
			$verify_result = $alipayNotify->verifyNotify();

			if($verify_result) {//验证成功
					/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
					//请在这里加上商户的业务逻辑程序代

					
					//——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
					
					//获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
					
					//商户订单号

					$out_trade_no = $_POST['out_trade_no'];

					//支付宝交易号

					$trade_no = $_POST['trade_no'];

					//交易状态
					$trade_status = $_POST['trade_status'];
					
					$trade = Paylist::where("id",'=',$out_trade_no)->where('status',0)->where('total',$_POST['total_fee'])->first();
			
					if($trade == NULL)
					{
						exit("success");
					}
					
					$trade->status = 1;
					$trade->save();

					//status
					$trade_status = $_POST['trade_status'];


					if($_POST['trade_status'] == 'TRADE_FINISHED') {
						//判断该笔订单是否在商户网站中已经做过处理
							//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
							//请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
							//如果有做过处理，不执行商户的业务程序
								
						//注意：
						//退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知

						//调试用，写文本函数记录程序运行情况是否正常
						//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
						
						
						
						$user=User::find($trade->userid);
						$user->money=$user->money+$_POST['total_fee'];
						$user->save();
						
						$codeq=new Code();
						$codeq->code="支付宝 充值";
						$codeq->isused=1;
						$codeq->type=-1;
						$codeq->number=$_POST['total_fee'];
						$codeq->usedatetime=date("Y-m-d H:i:s");
						$codeq->userid=$user->id;
						$codeq->save();
					  
					  
						
						
						if($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=NULL)
						{
							$gift_user=User::where("id","=",$user->ref_by)->first();
							$gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
							$gift_user->save();
							
							$Payback=new Payback();
							$Payback->total=$_POST['total_fee'];
							$Payback->userid=$user->id;
							$Payback->ref_by=$user->ref_by;
							$Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
							$Payback->datetime=time();
							$Payback->save();
							
						}
						
					}
					else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
						//判断该笔订单是否在商户网站中已经做过处理
							//如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
							//请务必判断请求时的total_fee、seller_id与通知时获取的total_fee、seller_id为一致的
							//如果有做过处理，不执行商户的业务程序
								
						//注意：
						//付款完成后，支付宝系统发送该交易状态通知

						//调试用，写文本函数记录程序运行情况是否正常
						//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
						
						$user=User::find($trade->userid);
						$user->money=$user->money+$_POST['total_fee'];
						$user->save();
						
						$codeq=new Code();
						$codeq->code="支付宝 充值";
						$codeq->isused=1;
						$codeq->type=-1;
						$codeq->number=$_POST['total_fee'];
						$codeq->usedatetime=date("Y-m-d H:i:s");
						$codeq->userid=$user->id;
						$codeq->save();
					  
					  
						
						
						if($user->ref_by!=""&&$user->ref_by!=0&&$user->ref_by!=NULL)
						{
							$gift_user=User::where("id","=",$user->ref_by)->first();
							$gift_user->money=($gift_user->money+($codeq->number*(Config::get('code_payback')/100)));
							$gift_user->save();
							
							$Payback=new Payback();
							$Payback->total=$_POST['total_fee'];
							$Payback->userid=$user->id;
							$Payback->ref_by=$user->ref_by;
							$Payback->ref_get=$codeq->number*(Config::get('code_payback')/100);
							$Payback->datetime=time();
							$Payback->save();
							
						}
						
					}

					//——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
						
					echo "success";		//请不要修改或删除
					
					if(Config::get('enable_donate') == 'true')
					{
						if($user->is_hide == 1)
						{
							Telegram::Send("姐姐姐姐，一位不愿透露姓名的大老爷给我们捐了 ".$codeq->number." 元呢~");
						}
						else
						{
							Telegram::Send("姐姐姐姐，".$user->user_name." 大老爷给我们捐了 ".$codeq->number." 元呢~");
						}
					}
					
					/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			}
			else {
				//验证失败
				echo "fail";

				//调试用，写文本函数记录程序运行情况是否正常
				//logResult("这里写入想要调试的代码变量值，或其他运行的结果记录");
			}
		}
	}
}
