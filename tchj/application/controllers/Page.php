<?php

//本站唯一controller
class Page extends MY_Controller{
	private $userLogin=false;    //标志用户登录状态
	private $errpage='pages/error.php';  //错误显示页面
	//ajax返回的错误标志 403权限问题 401客户端数据问题 440未登录 500服务端错误...
	
	public function __construct(){
		parent::__construct();
	}
	
	//生成应对ajax的返回对象
	private function _getReturn($code=0,$message='',$data=''){
		$return=array('code'=>0,'message'=>'','data'=>'');
		if (is_array($code)){
			$return=$code;
		}else{
			$return['code']=$code;
			$return['message']=$message;
			$return['data']=$data;
		}
		echo json_encode($return);
	}
	
	//默认页面
	public function index(){
		$data['user'] = $this->session->userdata('user_info');;
		$this->load->view('pages/main.php',$data);

	}
	
	//登出
	public function loginout(){
		//清除session
		$this->session->unset_userdata('user_info');
		//清除cookie
		setcookie('autologin','',time()-3600,'/');
		redirect(base_url('login/index'));
	}
	
	//部门管理页面
	public function department(){
		$data['config']=rexGetConfig();
		//管理员权限判定
		$flag=$this->_chkPower([USERM]);
		if ($flag['code']!=''){
			$data['ERROR']=$flag;
			$this->load->view($this->errpage,$data);
			return;
		}
		$this->load->model('Base');
		//获得部门信息
		$result=$this->Base->department();
		$data['department']=$result['data'];
		$this->load->view('pages/department.php',$data);
	}
	//部门数据操作
	public function departmentop($op=''){
		$flag=$this->_chkPower([USERM],'ajax');
		if ($flag['code']!=''){
			$this->_getReturn($flag);
			return;
		}
		$indata=array(
			'id'=>'',
			'text'=>'',
			'level'=>''
		);
		foreach ($indata as $key=>$value){
			$indata[$key]=$this->input->post($key);
		}
		switch($op){
			case 'add':  //添加部门信息
				//NOTICE 检测参数完整性
				if ($indata['level']!="1" && $indata['level']!="2"){
					$this->_getReturn(401,'部门级别参数错误');
					return;
				}
				$this->load->model('Base');
				$result=$this->Base->departmentOP('add',$indata);
				echo json_encode($result);
				break;
			case 'edit':  //修改部门信息
				//NOTICE 检测参数完整性
				if ($indata['id']=='' || $indata['text']==''){
					$this->_getReturn(401,'部门信息参数错误');
					return;
				}
				$this->load->model('Base');
				$result=$this->Base->departmentOP('edit',$indata);
				echo json_encode($result);
				break;
			case 'del':
				//NOTICE 检测参数完整性
				if ($indata['id']==''){
					$this->_getReturn(401,'部门信息参数错误');
					return;
				}
				$this->load->model('Base');
				$result=$this->Base->departmentOP('del',$indata);
				echo json_encode($result);
				break;
			default:
				$this->_getReturn(401,'没有对应的访问接口');
				return;
		}
	}
		
	//账户添加/编辑页面
	public function account($op,$uid=0){
		$data['config']=rexGetConfig();
		$data['op']=$op;
		
		switch($op){
			case 'add':
				$flag=$this->_chkPower([USERM]);
				if ($flag['code']!=''){
					$data['ERROR']=$flag;
					$this->load->view($this->errpage,$data);
					return;
				}
				$this->load->model('Base');
				$result=$this->Base->department();
				$data['department']=$result['data'];
				$result=$this->Base->job();
				$data['job']=$result['data'];
				$result=$this->Base->usertypeInfor();
				$data['usertype']=$result['data'];
				$data['account'] = $this->session->userdata('user_info');
				$data['user']='';
				$this->load->view('pages/account.php',$data);
				break;
			case 'edit':
				$account = $this->session->userdata('user_info');
				if ($uid==0){
					$uid = $account['uid'];
				}
				$flag=$this->_chkPower([],'accedit',array('uid'=>$uid));
				if ($flag['code']!=''){
					$data['ERROR']=$flag;
					$this->load->view($this->errpage,$data);
					return;
				}
				//获得用户信息
				$this->load->model('User');
				$result=$this->User->userInfor(array('uid'=>$uid));
				$user=$result['data'];
				if (count($user)==0){
					$data['ERROR']=array('code'=>401,'message'=>'没有找到对应的账户信息');
					$this->load->view('pages/error.php',$data);
					return;
				}
				$this->load->model('Base');
				$result=$this->Base->department(array('pid'=>$user[0]['pid']));
				$data['department']=$result['data'];
				$result=$this->Base->job(array('jid'=>$user[0]['jid']));
				$data['job']=$result['data'];
				$result=$this->Base->usertypeInfor(array('tid'=>$user[0]['tid']));
				$data['usertype']=$result['data'];
				$data['user']=$user[0];
				$data['account'] = $account;
				$this->load->view('pages/account.php',$data);
				break;
			default:
				$data['ERROR']=array('code'=>401,'message'=>'没有找到对应页面');
				$this->load->view('pages/error.php',$data);
				return;
		}
	}
	
	//账户信息操作controller
	public function accountop($op){
		$flag=$this->_chkPower([],'ajax');
		if ($flag['code']!=''){
			$this->_getReturn($flag);
			return;
		}
		
		$indata=array(
			'uid'=>'',
			'usertype'=>'',
			'username'=>'',
			'oldpwd'=>'',
			'newpwd'=>'',
			'department'=>'',
			'job'=>'',
			'name'=>'',
			'tel'=>''
		);
		foreach ($indata as $key=>$value){
			$indata[$key]=$this->input->post($key);
		}
		switch($op){
			case 'add':
				$flag=$this->_chkPower([USERM]);
				if ($flag['code']!=''){
					$this->_getReturn($flag);
					return;
				}
				//NOTICE 检测参数完整性
				if ($indata['username']=='' || strlen($indata['newpwd'])!=32 || $indata['usertype']==''){
					$this->_getReturn(401,'添加用户的必要信息缺失');
					return;
				}
				if ($indata['usertype']==USERM || $indata['usertype']==USERL){
					$indata['department']=0;
					$indata['job']=0;
				}else if($indata['usertype']==USERU){
					$indata['job']=0;
					if ($indata['department']==''){
						$this->_getReturn(401,'添加用户的必要信息缺失');
						return;
					}
				}else if($indata['usertype']==USERD){
					if ($indata['department']=='' || $indata['job']==''){
						$this->_getReturn(401,'添加用户的必要信息缺失');
						return;
					}
				}else{
					$this->_getReturn(401,'添加用户的相关参数不正确');
					return;
				}
				break;
		}	
	}
}