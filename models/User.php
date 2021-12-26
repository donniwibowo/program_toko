<?php
	class User extends SnlActiveRecord {
		public $user_id, $username, $password, $email, $firstname, $lastname, $status, $secret_key, $created_on, $created_by, $updated_on, $updated_by, $is_deleted;
		public $password_repeat;

		public function __construct() {
		    $this->classname = 'User';
			$this->table_name = 'tbl_user';
			$this->primary_key = 'user_id';
		}

		public function rules() {
			return array(
				'required'	=> array('username', 'password', 'email', 'firstname'),
				'unique'	=> array('username'),
				'repeat'	=> array('password'),
				'email'		=> array('email')
				// 'integer'	=> array('status')
			);
		}

		public static function model() {
			$model = new User();
			return $model;
		}

		public function getLabel($field, $with_rule = FALSE) {
			$labels = array(
				'user_id' => 'User Id',
				'username' => 'Username',
				'password' => 'Password',
				'email' => 'Email',
				'firstname' => 'Nama Depan',
				'lastname' => 'Nama Belakang',
				'status' => 'Status',
				'secret_key' => 'Secret Key',
				'created_on' => 'Created On',
				'created_by' => 'Created By',
				'updated_on' => 'Updated On',
				'updated_by' => 'Updated By',
				'is_deleted' => 'Is Deleted',
				'password_repeat' => 'Repeat Password',
			);

			$label = isset($labels[$field]) ? $labels[$field] : ucwords(str_replace('_', ' ', $field));

			if($with_rule) {
				if(isset($this->rules()['required'])) {
					foreach($this->rules()['required'] as $value) {
						if($value == $field) {
							return $label.' <span class="required">*</span>';
						}
					}
				}
			}

			return $label;
		}

		public function getData() {
			$data = array();
			$refclass = new ReflectionClass($this);
			foreach ($refclass->getProperties() as $property) {
			    $name = $property->name;
			    if ($property->class == $refclass->name) {
			    	$data[$property->name] = $this->$name;
			    }
			}

			return $data;
		}

		public function setAttributes($post = array()) {
			$attributes = $this->getData();
			foreach ($attributes as $key => $value) {
				if(isset($post[$key])) {
					$this->$key = $post[$key];
				}
			}
		}

		public function beforeSave() {
			// $this->secret_key = md5($this->username);
			if($this->isNewRecord) {
				$this->created_on = Snl::app()->dateNow();
				$this->created_by = Snl::app()->user()->user_id;
				$this->updated_on = Snl::app()->dateNow();
				$this->updated_by = Snl::app()->user()->user_id;

				$this->password = SecurityHelper::encrypt($this->password, $this->secret_key);
			} else {
				$this->updated_on = Snl::app()->dateNow();
				$this->updated_by = Snl::app()->user()->user_id;

				$old_password = User::getOldPassword($this->user_id);
				if($old_password != $this->password) {
					$this->password = SecurityHelper::encrypt($this->password, $this->secret_key);
				}
			}
		}

		public function delete() {
			$this->is_deleted = 1;
			if($this->save()) {
				return TRUE;
			}

			return FALSE;
		}

		public function validateLogin() {
			$model = User::model()->findByAttribute(array(
				'condition' => 'username = :username AND status = :status AND is_deleted = 0',
				'params'	=> array(':username' => $this->username, ':status' => 1)
			));

			if($model == NULL) {
				return FALSE;
			} else {
				if(SecurityHelper::encrypt($this->password, $model->secret_key) == $model->password) {
					$this->generateSessionLogin($model);
					return TRUE;
				}
			}

			return FALSE;
		}

		public function generateSessionLogin($model) {
			$data = new stdClass();
			$data->user_id 		= $model->user_id;
			$data->username 	= $model->username;
			$data->completeName = $model->firstname.' '.$model->lastname;
			$data->firstname 	= $model->firstname;
			$data->lastname 	= $model->lastname;
			$data->email 		= $model->email;
			$data->status 		= $model->status;

			Snl::session()->createSession(SecurityHelper::encrypt('backendlogin'), json_encode($data));
		}

		public static function getOldPassword($id) {
			$model = User::model()->findByPk($id);
			if($model !== NULL) {
				return $model->password;
			}

			return '';
		}
	}