<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use App\Models\Usermanagement_model;

class User_management extends \CodeIgniter\Controller {
	
	function __construct(){
		$this->session = \Config\Services::session();
		$this->db = \Config\Database::connect();
		$this->uri = new \CodeIgniter\HTTP\URI(current_url());
		$this->security = \Config\Services::security();

		$result['lkp_user_list'] = $this->db->query("select * from tbl_users where status = 1 and user_id = '".$this->session->get('login_id')."'")->getRow();
		if(isset($result['lkp_user_list'])){
			$this->country_id = $result['lkp_user_list']->country_id;
		}else{
			$this->country_id = 1;
		}
	}

	public function create_user(){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}

		$subquery="";
        if($this->session->get('role') == 2){
            $role_list = $this->db->query("select * from tbl_role where role_id ='".$this->session->get('role')."'and status = 1 ")->getRow();
        }else{
        	$role_list = $this->db->query("select * from tbl_role where status = 1 ")->getResultArray();
        }        

		$result = array('role_list' => $role_list);
		
		$result['lkp_county_list'] = $this->db->query("select * from lkp_county where  county_status = 1 ")->getResultArray();

		$result['lkp_country_list'] = $this->db->query("select * from lkp_country where  status = 1 order by slno")->getResultArray();

		$result['lkp_user_list'] = $this->db->query("select * from tbl_users where user_id = '".$this->session->get('login_id')."' and status = 1 ")->getRowArray();

		$headerresult['country_id'] = $this->country_id;
		
		return view('common/header', $headerresult)
			.view('user_management/create_user', $result)
			.view('common/footer');
	}

	public function get_countys(){

		$country_id = $this->request->getVar('country_id');

		$result['lkp_county_list'] = $this->db->query("select * from lkp_county where county_status = 1 and country_id = '".$country_id."' order by county_name ")->getResultArray();

		return $this->response->setJSON(array(
			'csrfName' => $this->security->getCSRFTokenName(),
			'csrfHash' => $this->security->getCSRFHash(),
			'status' => 1,
			'result' => $result
		));
	}

	public function manage_user(){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}

		$this->db->select('*');
		$this->db->where('year_status', 1);
		$year_list = $this->db->get('lkp_year')->result_array();

		$result = array(
			'year_list' => $year_list
		);
		return view('common/header')
			.view('user_management/manage_user', $result)
			.view('common/footer');
	}

	public function insert_user(){
		/*if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			echo json_encode(array(
				'msg' => 'Session expired please refresh the to login.',
				'status' => 0,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			));
			exit();
		}*/

		date_default_timezone_set('UTC');
		$baseurl = base_url();

		$check_email = $this->db->query("select * from tbl_users where email_id = '".$this->request->getVar('emailid')."' and status = 1 ")->getRowArray();

		$check_username = $this->db->query("select * from tbl_users where username = '".$this->request->getVar('user_name')."' and status = 1 ")->getRowArray();

		if(!empty($check_email) || !empty($check_username)){
			echo json_encode(array(
				'msg' => 'Either email or username is already in use.',
				'status' => 0,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			));
			$this->session->setFlashdata('success', "Either email or username is already in use.");
			exit();
		}

		$first_name = $this->request->getVar('first_name');
        $last_name = $this->request->getVar('last_name');
        // $role = $this->request->getVar('role');
        $email_id = $this->request->getVar('emailid');
        $username = $this->request->getVar('user_name');
        $password = $this->request->getVar('password');
        $mobile_number = $this->request->getVar('mobile_number');
        $user_type = $this->request->getVar('user_type');
        $country_id = $this->request->getVar('country');
        $county_id = $this->request->getVar('county');
        $organization = $this->request->getVar('organization');
        $organization_role = $this->request->getVar('organization_role');
        $reason = $this->request->getVar('reason');
		$salt = bin2hex(random_bytes(32));
		$saltedPW =  $password . $salt;
		$hashedPW = hash('sha256', $saltedPW);

        $user_array = array(
        	'username' => $username,
        	'email_id' => $email_id,
        	'password' => $hashedPW,
        	'salt' => $salt,
        	'first_name' => $first_name,
        	'last_name' => $last_name,
        	'role_id' => $user_type,
        	// 'approve_status' => 1,
        	'country_id' => $country_id,
        	'county_id' => $county_id,
        	'mobile_no' => $mobile_number,
        	'organization' => $organization,
        	'organization_role' => $organization_role,
        	'reason_for_dashboard' => $reason,
        	'forgot_pass' => NULL,
        	'added_by' => NULL,
        	'added_datetime' => date('Y-m-d H:i:s'),
        	'ip_address' => $this->request->getIPAddress(),
        	'status' => 1
        );
		$query =1;

		$builder = $this->db->table('tbl_users');
		$query = $builder->insert($user_array);		
		if(!$query) {
			return $this->response->setJSON(array(
				'msg' => 'Something went wrong please refresh the page and try again.',
				'status' => 0,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			));
		} else{
			$userManagementModel = new Usermanagement_model();
			if($user_type == 5){
				$user_role="County Admin";
				$user_details = array(
					// 'created_by' => $this->session->get('name'),
					'name' =>$first_name.' '.$last_name,
					'user_role' => $user_role,
					'county_name' => $userManagementModel->get_county_name_byID($county_id),
				);
			}else if($user_type == 6){
				$user_role="Country Admin";
				$user_details = array(
					// 'created_by' => $this->session->get('name'),
					'name' =>$first_name.' '.$last_name,
					'user_role' => $user_role,
				);
			}
			
			$this->send_mail($user_details);
			return $this->response->setJSON(array(
				'msg' => 'User added Successfully.',
				'status' => 1,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			));
		}
		exit();
	}

	public function send_mail($userdetails){
		$config = Array(
			'protocol' => 'smtp',
			'smtp_host' => 'ssl://smtp.googlemail.com',
			'smtp_port' => 465,
			'smtp_user' => 'amrtapplications@gmail.com',
			'smtp_pass' => 'oykicofhwzegtmwk',
			'mailtype' => 'html',
			'charset' => 'iso-8859-1',
			'wordwrap' => TRUE
		);

		$email = \Config\Services::email($config);

		//$this->load->library('email', $config);
		$email->setNewLine("\r\n");
		$email->setFrom('amrtapplications@gmail.com','Tails ADPT');
		// $this->email->to('praveen@unmiti.com');
		// $this->email->to('ggunasagar@gmail.com');
		$email->setTo('l.njuguna@cgiar.org');
		$email->setSubject('New User account creation alert');
		$email->setMailType("html");
		// $body = 'test';
		$name = $userdetails['name'];
		$user_role = $userdetails['user_role'];
		// print_r($county_name);exit;
		if($user_role == "County Admin"){
			$county_name = $userdetails['county_name'];
			$body ="Dear Admin,<br> New Account created <br/> <b>Account Details</b> : <br> User Name : ".$name."<br>Role : ".$user_role."<br> Sub-national name : ".$county_name."<br/> <a href='http://3.108.47.178/Login/' >Please click here to login and approve</a>";
		}else{
			$body ="Dear Admin,<br> New Account created <br/> <b>Account Details</b> : <br> User Name : ".$name."<br>Role : ".$user_role."<br/> <a href='http://3.108.47.178/Login/' >Please click here to login and approve</a>";
		}
		$email->setMessage($body);
		if($email->send()) {
			$result = array(
				'msg' => 'Message sent successfully',
				'status' => 1,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			);
			return $this->response->setJSON($result);
			exit();
		}
		else {
			$result = array(
				'msg' => 'Please try after some time',
				'status' => 0,
				'csrfName' => $this->security->getCSRFTokenName(),
				'csrfHash' => $this->security->getCSRFHash(),
			);
			return $this->response->setJSON($result);
			exit();
		}
	}

	public function approve_user(){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}
		$result = array();
		$time = time();
		$tablename="tbl_users";
		$user_id = $this->request->getVar('user_id');
		$insert_array =array();
		$insert_array['approve_status']=1;

		$builder = $this->db->table($tablename);
		$builder->where('user_id', $user_id);
		$query = $builder->update($insert_array);
		if($query){
			echo json_encode(array(
				'status' => 1,
				'msg' => 'USer Approved successfully.'
			));
		}else {
			echo json_encode(array('status' => 0, 'msg' => 'Sorry! Something went wrong, please try after some time'));
		}
		
		exit();
	}

	public function search_user_info(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$this->db->select('CONCAT(user.user_id, " - ", user.role_id) as id, user.first_name, user.last_name, user.role_id, role.role_name');
		$this->db->from('tbl_users AS user');
		$this->db->join('tbl_role AS role', 'role.role_id = user.role_id');
		if(isset($_POST['searchTerm'])){
			$this->db->group_start();
			$this->db->like('user.first_name', $_POST['searchTerm']);
			$this->db->or_like('user.last_name', $_POST['searchTerm']);
			$this->db->group_end();
		}
		$this->db->where('user.status', 1)->where('user.role_id >', 2);
		$search_result = $this->db->get()->result_array();

		echo json_encode($search_result);
		exit();
	}

	public function get_program_list_byyear(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$year = $this->request->getVar('year_val');

		$program_list = $this->Usermanagement_model->program_list($year);

		$result = array('program_list' => $program_list, 'status' => 1);

		if(isset($_POST['search_user'])){
			$user_id = $this->request->getVar('search_user');
			$type = $this->request->getVar('type');

			if($type == 'approval'){
				$table_name = "tbl_user_approval_indicator";
			}else{
				$table_name = "tbl_user_indicator";
			}

			$this->db->select('GROUP_CONCAT(lkp_program_id) as programs');
			$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('lkp_cluster_id IS NULL')->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
			$programs = $this->db->get($table_name)->row_array();
			if($programs == NULL){
				$result['user_programs'] = array();
			}else{
				$result['user_programs'] = explode(",", $programs['programs']);
			}

			$this->db->select('GROUP_CONCAT(lkp_cluster_id) as clusters');
			$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
			$clusters = $this->db->get($table_name)->row_array();
			if($clusters == NULL){
				$result['user_clusters'] = array();
			}else{
				$result['user_clusters'] = explode(",", $clusters['clusters']);
			}

			$this->db->select('GROUP_CONCAT(indicator_id) as indicators');
			$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('sub_indicator_id IS NULL');
			$indicators = $this->db->get($table_name)->row_array();
			if($indicators == NULL){
				$result['user_indicators'] = array();
			}else{
				$result['user_indicators'] = explode(",", $indicators['indicators']);
			}

			$this->db->select('GROUP_CONCAT(sub_indicator_id) as subindicators');
			$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id);
			$subindicators = $this->db->get($table_name)->row_array();
			if($subindicators == NULL){
				$result['user_subindicators'] = array();
			}else{
				$result['user_subindicators'] = explode(",", $subindicators['subindicators']);
			}
		}

		echo json_encode($result);
		exit();
	}

	public function get_po_list_for_result_tracker(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$year = $this->request->getVar('year');
		// Get default po, output, indicator list
		$po_list = $this->Usermanagement_model->po_list($year);
		
		$result = array('po_list' => $po_list, 'status' => 1);
		if(isset($_POST['search_user'])){
			$user_id = $this->request->getVar('search_user');

			// Get pos assigned to user
			$this->db->select('GROUP_CONCAT(po_id) as pos');
			$this->db->where('year_id', $year);
			$this->db->where('user_id', $user_id)->where('status', 1);
			$this->db->where('output_id IS NULL')->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
			$pos = $this->db->get('tbl_user_resulttrack_indicator')->row_array();
			if($pos == NULL){
				$result['user_pos'] = array();
			}else{
				$result['user_pos'] = explode(",", $pos['pos']);
			}

			// Get outputs assigned to user
			$this->db->select('GROUP_CONCAT(output_id) as outputs');
			$this->db->where('year_id', $year);
			$this->db->where('user_id', $user_id)->where('status', 1);
			$this->db->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
			$outputs = $this->db->get('tbl_user_resulttrack_indicator')->row_array();
			if($outputs == NULL){
				$result['user_outputs'] = array();
			}else{
				$result['user_outputs'] = explode(",", $outputs['outputs']);
			}

			// Get indicators assigned to user
			$this->db->select('GROUP_CONCAT(indicator_id) as indicators');
			$this->db->where('status', 1);
			$this->db->where('year_id', $year);
			$this->db->where('user_id', $user_id);
			$this->db->where('sub_indicator_id IS NULL');
			$indicators = $this->db->get('tbl_user_resulttrack_indicator')->row_array();
			if($indicators == NULL){
				$result['user_indicators'] = array();
			}else{
				$result['user_indicators'] = explode(",", $indicators['indicators']);
			}
		}

		echo json_encode($result);
		exit();
	}

	public function get_po_list_byyear2(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$year = $this->request->getVar('year_val');

		$po_list = $this->Usermanagement_model->po_list($year);

		$this->db->select('GROUP_CONCAT(country_id) as countries');
		$this->db->where('year_id', $year)->where('status', 1);
		$countries = $this->db->get('lkp_country_crop')->row_array();

		$countries_array = explode(",", $countries['countries']);

		$this->db->select('*');
		$this->db->where('status', 1)->where_in('country_id', $countries_array);
		$country_list = $this->db->get('lkp_country')->result_array();

		$result = array('po_list' => $po_list, 'status' => 1, 'country_list' => $country_list);

		
		/*$this->db->distinct()->select('lp.*');
						$this->db->join('tbl_user_approval_indicator AS tua', 'tua.po_id = lp.po_id');
						$this->db->where('tua.user_id', $useridd);
						//if($crop) $this->db->where_in('tua.crop_id', $crop);
						//if($country) $this->db->where_in('tua.country_id', $country);
						$this->db->where('tua.status', 1)->where('tua.year_id', $year);
						$this->db->where('lp.po_status', 1)->order_by('lp.po_name');
						$po = $this->db->get('lkp_po AS lp')->result_array(); */

		echo json_encode($result);
		exit();
		
	}

	public function user_list($value=''){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}

		$this->db->select('user.*, role.role_name');
		$this->db->from('tbl_users as user');
		$this->db->join('tbl_role as role', 'role.role_id = user.role_id');
		$this->db->where('user.status', 1)->where('role.status', 1);
		$user_list = $this->db->get()->result_array();
		
		$result = array('user_list' => $user_list);

		return view('common/header');
		return view('user_management/user_list', $result);
		return view('common/footer');
	}

	public function get_crop_bycountry(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$year = $this->request->getVar('year_val');
		$country = $this->request->getVar('country_val');

		$this->db->select('GROUP_CONCAT(crop_id) as crops');
		$this->db->where('year_id', $year)->where('status', 1)->where_in('country_id', $country);
		$crops = $this->db->get('lkp_country_crop')->row_array();

		$crops_array = explode(",", $crops['crops']);

		$this->db->select('*');
		$this->db->where('crop_status', 1)->where_in('crop_id', $crops_array);
		$crop_list = $this->db->get('lkp_crop')->result_array();

		$result = array('crop_list' => $crop_list, 'status' => 1);

		echo json_encode($result);
		exit();
	}

	public function map_user(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$action = $this->request->getVar('action');
		if(!$action || strlen($action) === 0) {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Unexpected error occured. Please refresh the page and try again.'
			));
			exit();
		}

		$search_user = $this->request->getVar('search_user');
		$year_val = $this->request->getVar('year_val');
		$country_val = $this->request->getVar('country_val');
		$crop_val = $this->request->getVar('crop_val');

		$program_val = $this->request->getVar('program_val');
		$cluster_val = $this->request->getVar('cluster_val');
		$indicator_val = $this->request->getVar('indicator_val');
		$subindicator_val = $this->request->getVar('subindicator_val');

		// Clear old assignments according to posted action
		switch ($action) {
			case 'reporting':
				// Clear country and crop assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_country_crop', array('status' => 0));

				// Clear indicator assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_indicator', array('status' => 0));
			break;

			case 'approval':
				// Clear country and crop assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_approval_country_crop', array('status' => 0));
			
				// Clear indicator assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_approval_indicator', array('status' => 0));
			break;

			case 'review':
				// Clear country and crop assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_review', array('status' => 0));
			break;

			case 'resultTracker':
				// Clear country and crop assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_resulttrack_country_crop', array('status' => 0));
			
				// Clear indicator assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_resulttrack_indicator', array('status' => 0));
			break;

			case 'planning':
				// Clear country and crop and po assignments
				$this->db->where('year_id', $year_val);
				$this->db->where('user_id', $search_user);
				$this->db->update('tbl_user_planning', array('status' => 0));
			break;
		}

		$all_poIds = array();
		
		// PO assignment
		if($program_val) {
			foreach ($program_val as $key => $po) {
				$insert_array = array(
					'user_id' => $search_user,
					'year_id' => $year_val,
					'lkp_program_id' => $po,
					'lkp_cluster_id' => NULL,
					'indicator_id' => NULL,
					'sub_indicator_id' => NULL,
					'added_by' => $this->session->get('login_id'),
					'added_datetime' => date('Y-m-d H:i:s'),
					'ip_address' => $this->request->getIPAddress(),
					'status' => 1
				);
				array_push($all_poIds, $po);

				switch ($action) {
					case 'reporting':
						$query = $this->db->insert('tbl_user_indicator', $insert_array);
					break;

					case 'approval':
						$query = $this->db->insert('tbl_user_approval_indicator', $insert_array);
					break;
				}
			}
		}
		// Output assignment
		if($cluster_val) {
			foreach ($cluster_val as $key => $output) {
				$get_outputinfo = $this->db->where('lkp_year', $year_val)->where('form_id', $output)->where('relation_status', 1)->where('form_type', 1)->get('rpt_form_relation')->row_array();

				if($get_outputinfo == NULL){
					echo json_encode(array(
						'msg' => 'Something went wrong while assigning output to user. Please refresh the page and try again.',
						'status' => 0
					));
					exit();
				}

				$insert_array = array(
					'user_id' => $search_user,
					'year_id' => $year_val,
					'lkp_program_id' => $get_outputinfo['lkp_program_id'],
					'lkp_cluster_id' => $output,
					'indicator_id' => NULL,
					'sub_indicator_id' => NULL,
					'added_by' => $this->session->get('login_id'),
					'added_datetime' => date('Y-m-d H:i:s'),
					'ip_address' => $this->request->getIPAddress(),
					'status' => 1
				);
				array_push($all_poIds, $get_outputinfo['lkp_program_id']);
			
				switch ($action) {
					case 'reporting':
						$query = $this->db->insert('tbl_user_indicator', $insert_array);
					break;

					case 'approval':
						$query = $this->db->insert('tbl_user_approval_indicator', $insert_array);
					break;
				}
			}
		}
		// Indicator assignment
		if($indicator_val) {
			foreach ($indicator_val as $key => $indicator) {
				$get_outputinfo = $this->db->where('lkp_year', $year_val)->where('form_id', $indicator)->where('relation_status', 1)->where('form_type', 2)->get('rpt_form_relation')->row_array();

				if($get_outputinfo == NULL){
					echo json_encode(array(
						'msg' => 'Something went wrong while assigning output to user. Please refresh the page and try again.',
						'status' => 0
					));
					exit();
				}

				$insert_array = array(
					'user_id' => $search_user,
					'year_id' => $year_val,
					'lkp_program_id' => $get_outputinfo['lkp_program_id'],
					'lkp_cluster_id' => $get_outputinfo['lkp_cluster_id'],
					'indicator_id' => $indicator,
					'sub_indicator_id' => NULL,
					'added_by' => $this->session->get('login_id'),
					'added_datetime' => date('Y-m-d H:i:s'),
					'ip_address' => $this->request->getIPAddress(),
					'status' => 1
				);
				array_push($all_poIds, $get_outputinfo['lkp_program_id']);
				
				switch ($action) {
					case 'reporting':
						$query = $this->db->insert('tbl_user_indicator', $insert_array);
					break;

					case 'approval':
						$query = $this->db->insert('tbl_user_approval_indicator', $insert_array);
					break;
				}
			}
		}
		// Sub-Indicator assignment
		if($subindicator_val) {
			foreach ($subindicator_val as $key => $subindicator) {
				$get_outputinfo = $this->db->where('lkp_year', $year_val)->where('form_id', $subindicator)->where('relation_status', 1)->where('form_type', 3)->get('rpt_form_relation')->row_array();

				if($get_outputinfo == NULL){
					echo json_encode(array(
						'msg' => 'Something went wrong while assigning output to user. Please refresh the page and try again.',
						'status' => 0
					));
					exit();
				}

				$insert_array = array(
					'user_id' => $search_user,
					'year_id' => $year_val,
					'lkp_program_id' => $get_outputinfo['lkp_program_id'],
					'lkp_cluster_id' => $get_outputinfo['lkp_cluster_id'],
					'indicator_id' => $get_outputinfo['indicator_id'],
					'sub_indicator_id' => $subindicator,
					'added_by' => $this->session->get('login_id'),
					'added_datetime' => date('Y-m-d H:i:s'),
					'ip_address' => $this->request->getIPAddress(),
					'status' => 1
				);
				
				array_push($all_poIds, $get_outputinfo['lkp_program_id']);
				switch ($action) {
					case 'reporting':
						$query = $this->db->insert('tbl_user_indicator', $insert_array);
					break;

					case 'approval':
						$query = $this->db->insert('tbl_user_approval_indicator', $insert_array);
					break;
				}
			}
		}

		echo json_encode(array(
			'msg' => 'User mapping done successfully.',
			'status' => 1
		));
		exit();
	}

	public function user_mapping_details(){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}

		$user_id = $this->uri->segment(3);

		if($user_id == '' || $user_id == NULL){
			show_404();
		}

		$year = 1;

		$user_details = $this->db->where('user_id', $user_id)->where('status', 1)->get('tbl_users')->row_array();

		// $this->db->select('cou.country_name, crop.crop_name');
		// $this->db->join('lkp_country as cou', 'cou.country_id = cc.country_id');
		// $this->db->join('lkp_crop as crop', 'crop.crop_id = cc.crop_id');
		// $this->db->where('user_id', $user_id);
		// $this->db->where('cc.status', 1);
		// $user_countrycrop_mapdetails = $this->db->get('tbl_user_country_crop as cc')->result_array();
		// $approval_countrycrop_mapdetails = $this->db->get('tbl_user_approval_country_crop as cc')->result_array();

		// $po_list = $this->Usermanagement_model->po_list($year);
		$po_list = $this->Usermanagement_model->program_list($year);

		$this->db->select('GROUP_CONCAT(lkp_program_id) as pos');
		$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('lkp_cluster_id IS NULL')->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
		$pos = $this->db->get('tbl_user_indicator')->row_array();
		$user_pos = explode(",", $pos['pos']);

		$this->db->select('GROUP_CONCAT(lkp_cluster_id) as outputs');
		$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('indicator_id IS NULL')->where('sub_indicator_id IS NULL');
		$outputs = $this->db->get('tbl_user_indicator')->row_array();
		$user_outputs = explode(",", $outputs['outputs']);

		$this->db->select('GROUP_CONCAT(indicator_id) as indicators');
		$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id)->where('sub_indicator_id IS NULL');
		$indicators = $this->db->get('tbl_user_indicator')->row_array();
		$user_indicators = explode(",", $indicators['indicators']);

		$this->db->select('GROUP_CONCAT(sub_indicator_id) as subindicators');
		$this->db->where('year_id', $year)->where('status', 1)->where('user_id', $user_id);
		$subindicators = $this->db->get('tbl_user_indicator')->row_array();
		$user_subindicators = explode(",", $subindicators['subindicators']);
		
		// $result = array('user_countrycrop_mapdetails' => $user_countrycrop_mapdetails, 'po_list' => $po_list, 'user_pos' => $user_pos, 'user_outputs' => $user_outputs, 'user_indicators' => $user_indicators, 'user_subindicators' => $user_subindicators, 'user_details' => $user_details);
		$result = array('po_list' => $po_list, 'user_pos' => $user_pos, 'user_outputs' => $user_outputs, 'user_indicators' => $user_indicators, 'user_subindicators' => $user_subindicators, 'user_details' => $user_details);
		
		return view('common/header');
		return view('user_management/user_mapping_details', $result);
		return view('common/footer');
	}

	public function reset_pass(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$user = $this->request->getVar('user_id');
		if(!$user || strlen($user) === 0) {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Unexpected error occured. Please refresh the page and try again.'
			));
			exit();
		}

		// $password = 'Avisa@123';
		$password = 'Mpro@123';
		$salt = bin2hex(random_bytes(32));
		$saltedPW =  $password . $salt;
		$hashedPW = hash('sha256', $saltedPW);

		$user_array = array(
			'password' => $hashedPW,
			'salt' => $salt
		);
		$query = $this->db->where('user_id', $user)->update('tbl_users', $user_array);
		
		echo json_encode(array(
			'status' => 1,
			'msg' => 'Password successfully reset to Avisa@123.'
		));
		exit();
	}

	public function change_role(){
		if($this->session->get('login_id') == '') {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Session Expired! Please login again to continue.'
			));
			exit();
		}

		$role = $this->request->getVar('role');
		$user = $this->request->getVar('user_id');
		if(!$user || strlen($user) === 0
		|| !$role || strlen($role) === 0) {
			echo json_encode(array(
				'status' => 0,
				'msg' => 'Unexpected error occured. Please refresh the page and try again.'
			));
			exit();
		}

		$user_array = array(
			'role_id' => $role
		);
		$query = $this->db->where('user_id', $user)->update('tbl_users', $user_array);
		
		echo json_encode(array(
			'status' => 1,
			'msg' => 'Role changed successfully.'
		));
		exit();
	}

	public function reporting_user(){
		if($this->session->get('login_id') == '' || $this->session->get('login_id') == NULL){
			return redirect()->to($this->baseurl);
		}

		$this->db->select('user.user_id, user.first_name, user.last_name, user.email_id, user.role_id, role.role_name, tru.status');
		$this->db->from('tbl_reporting_user as tru');
		$this->db->join('tbl_users as user', 'tru.user_id = user.user_id');
		$this->db->join('tbl_role as role', 'role.role_id = user.role_id');
		$this->db->where('user.status', 1);
		$this->db->order_by('user.first_name');
		$user_list = $this->db->get()->result_array();
		foreach ($user_list as $key => $user) {
			$this->db->select('cou.country_name, crop.crop_name');
			$this->db->join('lkp_country as cou', 'cou.country_id = cc.country_id');
			$this->db->join('lkp_crop as crop', 'crop.crop_id = cc.crop_id');
			$this->db->where('user_id', $user['user_id']);
			$this->db->where('cc.status', 1);
			$user_list[$key]['user_countrycrop_mapdetails'] = $this->db->get('tbl_user_country_crop as cc')->result_array();
		}

		$result = array('user_list' => $user_list);
		
		return view('common/header');
		return view('user_management/reporting_user', $result);
		return view('common/footer');
	}

	public function update_permissions(){
		if(($this->session->get('login_id') == '')) {
			echo json_encode(array(
				'msg' => 'Your session has expired. Please login and try again',
				'status' => 0
			));
			exit();
		}else{
			$this->load->model('Usermanagement_model');
			$update_permissions = $this->Usermanagement_model->update_permissions();
			
			if(!$update_permissions){
				echo json_encode(array('msg'=>'Sorry! Please try after sometime.','status' => 0));
	            exit();
			}else{
				echo json_encode(array('msg'=>'Reporting status updated successfully.','status' => 1));
	            exit();
			}
		}
	}
}