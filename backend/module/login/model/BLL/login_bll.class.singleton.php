<?php
     session_start();
	class login_bll {
		private $dao;
		private $db;
		static $_instance;
        
		function __construct() {
			$this -> dao = login_dao::getInstance();
			$this->db = db::getInstance();
		}

		public static function getInstance() {
			if (!(self::$_instance instanceof self)) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function submit_login_BLL($infoLogin) {
            $password = $infoLogin['log_password'];
            $user = $infoLogin['log_username'];
            $res_user = $this -> dao -> select_user($this->db,$user,"cars-city");
            if(!isset($res_user)){
                return array('error'=>'Usuario o contraseña incorrectos','src'=>'error_login');
            } else {
                if (password_verify($password,$res_user->contrasena)){
                    if ($res_user->verificado==1){
                        $token = middleware_auth::encode($res_user->id);
                        $_SESSION['id'] = $res_user->id;
                        $_SESSION['time'] = time();
                        return $token;
                    } else{
                        return array('code'=>'112','msg'=>'Usuario no verificado, revise su correo para verificarlo');
                    }
                }else {
                    return array('error'=>'Usuario o contraseña incorrectos','src'=>'error_login');
                }
            }
		}
        public function submit_register_BLL($infoRegister) {
            $check = true;
            $token = common::generate_token_secure(20);
            $uuid = "cars-city|".common::generate_token_secure(10);
            $password_hash = password_hash($infoRegister['reg_password'],PASSWORD_DEFAULT);
            $avatar = "https://avatars.dicebear.com/api/avataaars/" . $infoRegister['reg_username'] . ".svg?b=%23c2c2c2&r=50";
            $infoUser = json_decode(json_encode(['uuid'=>$uuid,'user' => $infoRegister['reg_username'], 'email' => $infoRegister['reg_email'], 'password' => $password_hash, 'avatar' => $avatar, 'token'=> $token]));
            $check_user = $this -> dao -> select_user($this->db,$infoUser->user,"cars-city");
            $check_email = $this -> dao -> select_user_email($this->db,$infoUser->email,"cars-city");
            if (isset($check_user)) {
                $check = false;
                return array('error'=>'Usuario no disponible','src'=>'error_reg_username');
            }
            if (isset($check_email)) {
                $check = false;
                return array('error'=>'Email no disponible','src'=>'error_reg_email');
            }
            if ($check){
                $new_user = $this -> dao -> register_user($this->db,$infoUser);
                if($new_user){
                    $message = ['type' => 'verify',  
                                'user' => $infoUser->user,
                                'email' => $infoUser->email,
                                'url' => $infoRegister["url"] . $token ];
                    $email = json_decode(mail::send_email($message), true);
                    return array('code'=>'110','msg'=>'Se ha enviado un correo de verificacion');
                }
                return array('error'=>'Algo ha ido mal al crear el token','src'=>'error_reg');
            }else {
                return array('error'=>'Algo ha ido mal al crear el token','src'=>'error_reg');
            }
		}

        public function sign_in_BLL($infoUser){
            $res_user = $this -> dao -> select_user($this->db,$infoUser->user,$infoUser->entity);

            if (isset($res_user)){
                $token = middleware_auth::encode($res_user->id);
                $_SESSION['id'] = $res_user->id;
                $_SESSION['time'] = time();
                return $token;
            } else {
                $new_user = $this -> dao -> register_social_user($this->db,$infoUser);
                if ($new_user){
                    $token = middleware_auth::encode($new_user->id);
                    $_SESSION['id'] = $new_user->id;
                    $_SESSION['time'] = time();
                    return $token;
                }
            }
            return array('error'=>'Algo ha ido mal al crear el token','src'=>'error_login');
        }
        public function auth0_credentials_BLL() {
            return auth0::getCredentials();
        }
        public function data_user_BLL($token){
            $user = middleware_auth::decode($token)->id;
            if ($user==false){
                return false;
            }
            $res_user = $this -> dao -> select_user_id($this->db,$user);
            return $res_user;
        }

        public function logout_BLL(){
            $_SESSION['id'] = "";
            $_SESSION['time'] = "";
            session_destroy();
            return $_SESSION['id'];
        }

        public function control_user_BLL($token){
            if (!isset($_SESSION['id'])){
                return false;
            } else {
                $user = middleware_auth::decode($token)->id;
                if ($user==false){
                    return false;
                }
                if ($user == $_SESSION['id']){
                    return true;
                } else {
                    return false;
                }
            }
        }

        public function activity_BLL(){
            if (!isset($_SESSION["time"])){
                return "inactivo";
            } else {
                if ((time() - $_SESSION["time"])>=300){
                    return "inactivo";
                } else {
                    return time() - $_SESSION["time"];
                }
            }
        }

        public function refresh_cookie_BLL(){
            session_regenerate_id();
            $_SESSION["time"] = time();
            return $_SESSION["time"];
        }

        function refresh_token_BLL($token) {
            $user = middleware_auth::decode($token)->id;
            if ($user==false){
                return false;
            }
            if ($user == $_SESSION['id']) {
                $new_token = middleware_auth::encode($user);
                return $new_token;
            } else {
                return false;
            }
        }
        
        function verify_user_BLL($token) {
            $user_info = $this -> dao -> check_user($this->db,$token);
            if (!isset($user_info)){
                exit;
            }
            if ($user_info->verificado==1){
                return "Email ya verificado";
            }

            return $this -> dao -> verify_user($this->db,$user_info->id);
        }

        function recover_password_BLL($infoRecover) {
            $user_info = $this -> dao -> check_user($this->db,$infoRecover->token);
            if (!isset($user_info)){
                exit;
            }
            if ($user_info->verificado==1){
                exit;
            }
            if ($infoRecover->rec_password==$infoRecover->rec_password_2){
                $password_hash = password_hash($infoRecover->rec_password,PASSWORD_DEFAULT);
                return $this -> dao -> recover_password($this->db,$user_info->id,$password_hash);
            }
        }
        function recover_email_BLL($userEmail) {
            $token = common::generate_token_secure(20);
            $user_info = $this -> dao -> select_user_email($this->db,$userEmail["email"],"cars-city");
            $user_status = $this -> dao -> change_user_status($this->db,$user_info->id,$token);
            if(!isset($user_info)){
                return;
            }
            $message = ['type' => 'recover',  
                                'user' => $user_info->usuario,
                                'url' => $userEmail["url"] . $token];
            $email = json_decode(mail::send_email($message), true);
            return array('code'=>'110','msg'=>'Se ha enviado un correo de recuperacion');
        }
	}

?>