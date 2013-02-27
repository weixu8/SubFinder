<?php

class SubFinder extends CI_Controller {
	
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper('string');
		date_default_timezone_set('Asia/Calcutta');
	}
	
		
	
	public function sreq(){
	
		$phonevol = preg_replace('/^91/', '', $_REQUEST['msisdn']); // Gupshup uses a 91 at the start. Remove that.
		$keyword = strtolower($_REQUEST['keyword']);
		$content = $_REQUEST['content'];
		
		//Get the details of the volunteer who has requested the substitution
		$query = $this->db->from('volunteer')->where('phone', $phonevol)->get(); //Get details of volunteer who send the request message
		
		if($query->num_rows() == 0){
			echo "Message to $phonevol: Your phone number doesn't exist on the MAD database. Please contact your Ops fellow for more details.<br>";
			exit();
		}
		
		$req_vol = $query->row();
		
		//Generate the random 4 character request id
		$req_id = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789',5)),0,4);
		
		
		//Check whether the request id already exist
		$query = $this->db->from('request')->where('req_id',$req_id)->get();
		
		while(1){
			if($query->num_rows() > 0){
				$req_id = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789',5)),0,4);
				$query = $this->db->from('request')->where('req_id',$req_id)->get();
			}
			else{
				break;
			}
		}
		
		//Remove the keyword from the content
		$content = str_replace('SREQ ','', $content);
		$content = trim($content);
		
		
		//Check if request message contains argument for specifying sub requirement for a class which is not the next class
		if(is_numeric($content))	
			$extra = $content;
		else
			$extra = 0;
		
		//Create the date and time in format suitable for the MySQL table
		$day_time = new DateTime("Next $req_vol->day_time +$extra week");
		$date_time = $day_time->format('Y-m-d H:i:s');
		
		$date = $day_time->format('d-m-Y');
		
		//Check if the volunteer has already made a request for the same day
		$query = $this->db->from('request')->where('req_vol_id',$req_vol->id)->where('date_time',$date_time)->get();
		
		if($query->num_rows() > 0){
			echo "Message to $phonevol: Your request for $date has already been registered and is under process.<br>";
			exit();
		}
		
		//Message the volunteer about the the ID number
		echo "Request Vol: $req_vol->name <br>";
		echo "Message to $phonevol: Your request for $date has been registered under the REQ ID: $req_id. <br>";
		

				

		//Insert the request details into the 'request' table
		$data = array(
		   'req_id' => $req_id ,
		   'req_vol_id' => $req_vol->id ,
		   'date_time' => $date_time
		);

		$this->db->insert('request', $data); 
		
		$this->db->from('volunteer')->where_not_in('id', $req_vol->id)->where('city',$req_vol->city);
		$query = $this->db->get();
		
		list($name) = explode(" ",$req_vol->name);
		list($center) = explode(" ",$req_vol->center);
		
		
		foreach ($query->result() as $selectedvol){
			
			echo "<br>Selected Vol: $selectedvol->name <br>";
			echo "Message to $selectedvol->phone: 
			$name requires a substitute at $center 
			on $req_vol->day_time($date). To sub text 'SFOR $req_id' to 1234567890.<br>" ;
				
			
		}
	}
	
	
	public function sfor(){
		
		$phonevol = preg_replace('/^91/', '', $_REQUEST['msisdn']); // Gupshup uses a 91 at the start. Remove that.
		$keyword = strtolower($_REQUEST['keyword']);
		$content = $_REQUEST['content'];
		
		
		//Get the details of the volunteer who has send the message
		$query = $this->db->from('volunteer')->where('phone', $phonevol)->get();
		
		if($query->num_rows() == 0){
			echo "Message to $phonevol: Your phone number doesn't exist on the MAD database. Please contact your Ops fellow for more details<br>";
			exit();
		}
				
		$int_vol = $query->row();
		
		$flag_req_exist = false;
		
		$content = str_replace('SFOR ','', $content);
		$content = trim($content);
		
		//Check if the request id that is specified in the message exist
		$query = $this->db->from('request')->get();
		foreach($query->result() as $request){
			if($content == $request->req_id)
				$flag_req_exist = true;
		}
		
		//Check if the volunteer interest has already been registered in the database
		$flag_int_already_reg = false;
		
		if($flag_req_exist == false)
			echo "Message to $int_vol->phone: The REQ ID that you have specified doesn't exist. Please check and resend message.<br>";
		else{
			$query0 = $this->db->from('request')->where('req_id',$content)->get();
			$request = $query0->row();
			$name = "int_vol_";
			for($i = 1; $i<=20; $i++){
				if($request->{$name.$i} == $int_vol->id){
					$flag_int_already_reg = true;
					echo "Message to $int_vol->phone: Your response to the request($request->req_id) has already 
					been registered. Please wait for confirmation.<br>";
					break;
				}
			}
		}
		
		
		//Check if the sub request is still open
		if($flag_req_exist == true && $flag_int_already_reg == false){			
			$query1 = $this->db->from('request')->where('req_id',$content)->get();
			$request = $query1->row();
			if($request->sub_vol != -1)
				echo "Message to $int_vol->phone: We have already found a volunteer to sub for the request($request->req_id). Thank you for your response.<br>";
			else{
				$name = "int_vol_";
				for($i = 1; $i<=20; $i++){
					if($request->{$name.$i} == -1){
						
						$data = array(
						   $name.$i => $int_vol->id ,
						);
						
						//Insert the interested volunteers id into the 'request' table
						$this->db->where('req_id',$content)->update('request', $data); 
						
						echo "Message to $int_vol->phone: Your response to the request($request->req_id) has been registered. Please wait for confirmation.<br>";
						$query2 = $this->db->from('volunteer')->where('id',$request->req_vol_id)->get();
						
						//Inform the volunteer who has made the request about the interested volunteer
						list($int_vol_name) = explode(" ",$int_vol->name);
						$req_vol = $query2->row();
						echo "Message to $req_vol->phone: $int_vol_name is interested to sub for you. To confirm text 'SCNF $request->req_id $i' to 1234567890.<br>";
						break;
					}
				}
			}		
		}	
	}
	
	public function scnf(){
	
		$phonevol = preg_replace('/^91/', '', $_REQUEST['msisdn']); // Gupshup uses a 91 at the start. Remove that.
		$keyword = strtolower($_REQUEST['keyword']);
		$content = $_REQUEST['content'];
		
		//Get the details of the volunteer who has made the request
		$query = $this->db->from('volunteer')->where('phone', $phonevol)->get();
			
		if($query->num_rows() == 0){
			echo "Message to $phonevol: Your phone number doesn't exist on the MAD database. Please contact your Ops fellow for more details<br>";
			exit();
		}
				
		$req_vol = $query->row();
		
		list($req_id, $vol_no) = explode(" ",str_replace('SCNF ','', $content));
		$req_id = trim($req_id);
		$vol_no = trim($vol_no);
		
		//Check if the request id that has been specified exist
		$flag_req_exist = false;
		$query = $this->db->from('request')->where('req_id',$req_id)->get();
			
		if($query->num_rows() > 0)
			$flag_req_exist = true;
		else{
			echo "Message to $req_vol->phone: The REQ ID that you have specified doesn't exist. Please check and resend the message.<br>";
			exit();
		}
		
		
		$flag_vol_exist = false;
		
		$request = $query->row();
		$name = "int_vol_";
		
		//Check if the volunteer number specified exist
		$query1 = $this->db->from('volunteer')->where('id',$request->{$name.$vol_no})->get();
		if($query1->num_rows() > 0)
			$flag_vol_exist = true;
		else{
			
			echo "Message to $req_vol->phone: The Volunteer ID you have specified doesn't exist. Please check and resend the message.<br>";
			exit();
		}
		
		//Check if a sub has already been confirmed for the request
		$query2 = $this->db->from('request')->where('req_id',$req_id)->where('sub_vol !=',-1)->get();
		if($query2->num_rows() > 0){
			$request = $query2->row();
			$query3 = $this->db->from('volunteer')->where('id',$request->sub_vol)->get();
			$sub_vol = $query3->row();
			list($sub_vol_name) = explode(" ",$sub_vol->name);
			echo "Message to $req_vol->phone: You have already confirmed $sub_vol_name for the request($req_id).<br>";
			exit();
		}
		
		
		//If both are true then update the confirmed sub(sub_conf) field in the request table
		if($flag_req_exist==true && $flag_vol_exist==true){
			$sub_vol = $query1->row();
			
			$data = array(
						   'sub_vol' => $sub_vol->id
						);
			$this->db->where('req_id',$req_id)->update('request', $data); 
			
			$date_time = new DateTime($request->date_time);
			$date = $date_time->format('d-m-Y');
			list($req_vol_name) = explode(" ",$req_vol->name);
			list($center) = explode(" ",$req_vol->center);
			
			echo "Message to $sub_vol->phone: You have been confirmed to sub for $req_vol_name($req_vol->phone) at $center on $req_vol->day_time($date).<br>";
			echo "Message to $req_vol->phone: You have confirmed $sub_vol->name($sub_vol->phone) to sub for you on $req_vol->day_time($date).<br>";
			
			//Message the other interested volunteers about the confirmation
			$name = "int_vol_";
			for($i = 1; $i<=20; $i++){
				if($request->{$name.$i} != -1 && $request->{$name.$i} != $sub_vol->id){
					
					$query2 = $this->db->from('volunteer')->where('id',$request->{$name.$i})->get();
					$int_vol = $query2->row();	
					echo "Message to $int_vol->phone: We have found a volunteer to sub for the request($request->req_id). Thank you for your response.<br>";
					
				}
			}
		}	
	

	}
	
	
	public function srem(){
		
		$phonevol = preg_replace('/^91/', '', $_REQUEST['msisdn']); // Gupshup uses a 91 at the start. Remove that.
		$keyword = strtolower($_REQUEST['keyword']);
		$content = $_REQUEST['content'];
		
		
		//Get details about the volunteer who has send the message
		$query = $this->db->from('volunteer')->where('phone', $phonevol)->get();
			
		if($query->num_rows() == 0){
			echo "Message to $phonevol: Your phone number doesn't exist on the MAD database. Please contact your Ops fellow for more details<br>";
			exit();
		}
				
		$req_vol = $query->row();	
		
		$content = str_replace('SREM ','', $content);
		$content = trim($content);
		
		//Check if the request id that has been specified exist
		
		$query = $this->db->from('request')->where('req_id',$content)->get();
			
		if($query->num_rows() == 0){
			echo "Message to $req_vol->phone: The REQ ID that you have specified doesn't exist. Please check and resend the message.<br>";
			exit();
		}
		
		//Check if the volunteer making the remove request is the same as the one who made the sub request
		
		$request = $query->row();
		
		if($request->req_vol_id != $req_vol->id){
			echo "Message to $req_vol->phone: The request($content) has been created by another volunteer. You can only remove requests created by you.<br>";
			exit();
		}
		
		//Delete and inform the volunteer about the same
		$this->db->delete('request', array('req_id' => $content)); 
		
		echo "Message to $req_vol->phone: The request($content) has been removed from the database.<br>";
		
		
		//Inform all the volunteers who had expressed interest in subbing about the removal of the request
		$name = "int_vol_";
		for($i = 1; $i<=20; $i++){
			if($request->{$name.$i} != -1){
				
				$query1 = $this->db->from('volunteer')->where('id',$request->{$name.$i})->get();
				$int_vol = $query1->row();	
				echo "Message to $int_vol->phone: The request($content) has been removed and is no longer required. Thank you for your response.<br>";
				
			}
		}
		
	}
}
//http://localhost/index.php/subfinder/sreq?msisdn=919633977657&keyword=SREQ&content=SREQ
//http://localhost/index.php/subfinder/sfor?msisdn=919746419487&keyword=SFOR&content=SFOR+9tdn
//http://localhost/index.php/subfinder/scnf?msisdn=919746419487&keyword=SCNF&content=SCNF+9tdn+2
//http://localhost/index.php/subfinder/srem?msisdn=919746419487&keyword=SREM&content=SREM+9tdn
?>		
