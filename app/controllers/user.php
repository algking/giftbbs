<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
#doc
#	classname:	User
#	scope:		PUBLIC
#	StartBBS起点轻量开源社区系统
#	author :doudou QQ:858292510 startbbs@126.com
#	Copyright (c) 2013 http://www.startbbs.com All rights reserved.
#/doc

class User extends SB_Controller
{

	function __construct ()
	{
		parent::__construct();
		$this->load->model ('user_m');

	}

	public function index()
	{
		$data['title'] = '用户';
		$data['new_users'] = $this->user_m->get_users(33,'new');
		$data['hot_users'] = $this->user_m->get_users(33,'hot');
		//action
		$data['action'] = 'user';		
		$this->load->view('user',$data);
	}
	public function profile ($uid)
	{
		$data = $this->user_m->get_user_by_id($uid);
		//用户大头像
		$this->load->model('upload_m');
		$data['big_avatar']=$this->upload_m->get_avatar_url($uid, 'big');
		//此用户发贴
		$this->load->model('topic_m');
		$data['user_posts'] = $this->topic_m->get_topics_by_uid($uid,5);
		//此用户回贴
		$this->load->model('comment_m');
		$data['user_comments'] = $this->comment_m->get_comments_by_uid($uid,5);
		//是否被关注
		$this->load->model('follow_m');
		$data['is_followed'] = $this->follow_m->follow_user_check($this->session->userdata('uid'), $uid);

		$this->load->view('user_profile', $data);
		
	}
	public function register ()
	{

		//加载form类，为调用错误函数,需view前加载
		$this->load->helper('form');

		$data['title'] = '注册新用户';
		if ($this->auth->is_login()) {
			show_message('已登录，请退出再注册');
		}
		if($_POST && $this->validate_register_form()){
			$password = $this->input->post('password',true);
			$salt =get_salt();
			$this->config->load('userset');//用户积分
			$data = array(
				'username' => strip_tags($this->input->post('username')),
				'password' => password_dohash($password,$salt),
				'salt' => $salt,
				'email' => $this->input->post('email',true),
				'credit' => $this->config->item('credit_start'),
				'ip' => get_onlineip(),
				'group_type' => 2,
				'gid' => 3,
				'regtime' => time(),
				'is_active' => 1
			);
			$check_register = $this->user_m->check_register($data['email']);
			$check_username = $this->user_m->check_username($data['username']);
			$captcha = $this->input->post('captcha_code');
			if(!empty($check_username)){
				show_message('用户名已存在!!');
			}
			if(!empty($check_register)){
				show_message('邮箱已注册，请换一个邮箱！');
			}
			if($this->user_m->register($data)){
				$uid = $this->db->insert_id();
				$this->session->set_userdata(array ('uid' => $uid, 'username' => $data['username'], 'group_type' => $data['group_type'], 'gid' => $data['gid']) );
				//去除验证码session
				$this->session->unset_userdata('yzm');
				//发送注册邮件
				if($this->config->item('mail_reg')=='on'){
					$subject='欢迎加入'.$this->config->item('site_name');
					$message='欢迎来到 '.$this->config->item('site_name').' 论坛<br/>请妥善保管这封信件。您的帐户信息如下所示：<br/>----------------------------<br/>用户名：'.$data['username'].'<br/>论坛链接: '.site_url().'<br/>----------------------------<br/><br/>感谢您的注册！<br/><br/>-- <br/>'.$this->config->item('site_name');
					send_mail($data['email'],$subject,$message);
					//echo $this->email->print_debugger();
				}

				redirect();
			}

		} else{
			$this->load->view('register',$data);
		}
	}
	
	public function username_check($username)
	{  
		if(!preg_match('/^(?!_)(?!.*?_$)[\x{4e00}-\x{9fa5}A-Za-z0-9_]+$/u', $username)){
			$this->form_validation->set_message('username_check', '%s 只能含有汉字、数字、字母、下划线（不能开头或结尾)');
  			return false;
		} else{
			return true;
		}
	}

	public function check_captcha($captcha)
	{
		if($this->config->item('show_captcha')=='on' && $this->session->userdata('yzm')!=$captcha){
			$this->form_validation->set_message('check_captcha', '%s 不正确!!');
  			return false;
		} else{
			return true;
		}
	}
	
	private function validate_register_form(){
		$this->load->library('form_validation');

		$this->form_validation->set_rules('email', 'Email' , 'trim|required|min_length[3]|max_length[50]|valid_email');
		$this->form_validation->set_rules('username', '昵称' , 'trim|required|min_length[3]|max_length[15]|callback_username_check|xss_clean');
		$this->form_validation->set_rules('password', '用户密码' , 'trim|required|min_length[6]|max_length[40]|matches[password_c]');
		$this->form_validation->set_rules('password_c', '密码验证' , 'trim|required|min_length[6]|max_length[40]');
		$this->form_validation->set_rules('captcha_code', '验证码' , 'trim|required|callback_check_captcha');
		$this->form_validation->set_message('required', "%s 不能为空！");
		$this->form_validation->set_message('min_length', "%s 最小长度不少于 %s 个字符或汉字！");
		$this->form_validation->set_message('max_length', "%s 最大长度不多于 %s 个字符或汉字！");
		$this->form_validation->set_message('matches', "两次密码不一致");
		$this->form_validation->set_message('valid_email', "邮箱格式不对");
		$this->form_validation->set_message('alpha_dash', "邮箱格式不对");
		if ($this->form_validation->run() == FALSE){
			return FALSE;
		}else{
			return TRUE;
		}
	}
	
	public function login ()
	{
		$data['title'] = '用户登录';
		$data['referer']=$this->input->get('referer',true);
		//$data['referer']=($this->input->server('HTTP_REFERER')==site_url('user/login'))?'/':$this->input->server('HTTP_REFERER');
		$data['referer']=$data['referer']?$data['referer']: $this->input->server('HTTP_REFERER');
		if($this->auth->is_login()){
			redirect();
		}
		if($_POST){
			$username = $this->input->post('username',true);
			$password = $this->input->post('password',true);
			$user = $this->user_m->check_login($username, $password);
			$captcha = $this->input->post('captcha_code');
			if($this->config->item('show_captcha')=='on' && $this->session->userdata('yzm')!=$captcha) {
				show_message('验证码不正确');
			}
			if($user){
				//更新session
				$this->session->set_userdata(array ('uid' => $user['uid'], 'username' => $user['username'], 'group_type' => $user['group_type'], 'gid' => $user['gid']));

				//更新积分
				$this->config->load('userset');
				$this->user_m->update_credit($user['uid'],$this->config->item('credit_login'));
				//更新最后登录时间
				$this->user_m->update_user($user['uid'],array('lastlogin'=>time()));
				header("location: ".$data['referer']);
				//redirect($data['referer']);
				exit;
				
			} else {
				show_message('用户名或密错误!');
			}
		} else {
			$this->load->view('login',$data);
		}
		
	}

	public function logout ()
	{
		$this->session->sess_destroy();
		
		$this->load->helper('cookie');
		delete_cookie('uid');
		delete_cookie('username');
		delete_cookie('group_type');
		delete_cookie('gid');
		delete_cookie('openid');
		redirect('user/login');
	}


	public function findpwd()
	{
		if($_POST){
			$username = $this->input->post('username');
			$data = $this->user_m->getpwd_by_username($username);
			if(@$data['email']==$this->input->post('email')){
				$x = md5($username.'+').@$data['password'];
				$string = base64_encode($username.".".$x);
				$subject ='重置密码';
				$message = '尊敬的用户'.$username.':<br/>你使用了本站提供的密码找回功能，如果你确认此密码找回功能是你启用的，请点击下面的链接，按流程进行密码重设。<br/><a href="'.site_url("user/resetpwd?p=").$string.'">'.site_url('user/reset_pwd?p=').$string.'</a><br/>如果不能打开链接，请复制链接到浏览器中。<br/>如果本次密码重设请求不是由你发起，你可以安全地忽略本邮件。';
			if(send_mail($this->input->post('email'),$subject,$message)){
				$data['msg'] = '密码重置链接已经发到您邮箱:'.$data['email'].',请注意查收！';
				}else{
					$data['msg'] = '没有发送成功';
				}
				$data['title'] =  '信息提示';
				$this->load->view('msg',$data);
				//echo $this->email->print_debugger();
			} else {
				show_message('用户名或邮箱错误!!');
			}
		} else{
			$data['title'] = '找回密码';
			$this->load->view('findpwd',$data);
		}

	}

	public function resetpwd()
	{

		$array = explode('.',base64_decode(@$_GET['p']));
		$data = $this->user_m->getpwd_by_username($array['0']);
		//$sql = "select passwords from member where username = '".trim($_array['0'])."'";
		$checkCode = md5($array['0'].'+').@$data['password'];
			
		if(@$array['1'] === $checkCode ){
			if($_POST){
				$password = $this->input->post('password');
				if($this->user_m->update_user(@$data['uid'], array('password'=>$password))){
					$this->session->set_userdata(array ('uid' => $data['uid'], 'username' => $array['0'], 'group_type' => $data['group_type'], 'gid' => $data['gid']));
					redirect('/');
				}
			}
		} else{
			show_message('非法重置！！');
		}
		$data['title'] = '设置新密码';
		$data['p'] = $_GET['p'];
		$this->load->view('findpwd',$data);
	}

}