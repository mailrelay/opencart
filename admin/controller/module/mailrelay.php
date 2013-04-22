<?php
$dirname = dirname( __FILE__ );
$path = array();
$path[] = $dirname . DIRECTORY_SEPARATOR . 'library';
$path[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $path));

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance ()->registerNamespace('Zend');

class ControllerModuleMailrelay extends Controller {

    private $error = array(); 
	
    private function validate() {
        if (!$this->user->hasPermission('modify', 'module/mailrelay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
	}
	
    public function install() {
        $this->load->model('module/mailrelay');
        $this->model_module_mailrelay->createMailrelayTable();
	}
	
	public function index() {   
		//LOAD LANGUAGE
		$this->load->language('module/mailrelay');

		//SET TITLE
		$this->document->setTitle($this->language->get('heading_title'));
		
		//SAVE SETTINGS (on submission of form)
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
		    switch ($this->request->get['action']) {
		        case 'save':
		            $credentials = $this->_saveCredentials();
		            break;
		        case 'sync':
		            $credentials = $this->_getCredentials();
		            $this->_sync($credentials);
		            break;
		    }
		} else {
		    $credentials = $this->_getCredentials();
		}

		//LANGUAGE
		$text_strings = array(
				'heading_title',
				'text_module',
				'text_config',
				'text_sync',
				'text_start_sync',
				'text_success',
				'entry_hostname',
				'entry_username',
				'entry_password',
				'entry_groups',
				'error_permission',
				'error_please_fill_in_all_required_fields',
				'button_save',
				'button_cancel',
		);
		
		foreach ($text_strings as $text) {
			$this->data[$text] = $this->language->get($text);
		}
		//END LANGUAGE
		
		//CONFIG
		$config_data = array(
			'hostname',
			'username',
			'password',
			'key',
			'last_group'
		);
		
		foreach ($config_data as $conf) {
			if (isset($this->request->post[$conf])) {
				$this->data[$conf] = $this->request->post[$conf];
			} else {
				$this->data[$conf] = $credentials[$conf];
			}
		}
		
		$this->data['groups'] = $this->_assignGroups($this->data);
		$this->data['last_group'] = $this->data['last_group'];

 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}
		
		if (!isset($this->data['success'])) {
		    $this->data['success'] = '';
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_module'),
			'href'      => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);
		
   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('module/mailrelay', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);
		
		$this->data['action_save'] = $this->url->link('module/mailrelay', 'action=save&token=' . $this->session->data['token'], 'SSL');
		
		$this->data['action_sync'] = $this->url->link('module/mailrelay', 'action=sync&token=' . $this->session->data['token'], 'SSL');
		
		$this->data['cancel'] = $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL');

		$this->load->model('design/layout');
		
		$this->data['layouts'] = $this->model_design_layout->getLayouts();

		//Choose which template file will be used to display this request.
		$this->template = 'module/mailrelay.tpl';
		$this->children = array(
			'common/header',
			'common/footer',
		);

		$this->response->setOutput($this->render());
	}
	
	protected function _getCredentials() {
        $row = null;
        $db_prefix = DB_PREFIX;

        $sql = "SELECT *, aes_decrypt(unhex(`password`), `hostname`) as `password`, `key` FROM `{$db_prefix}mailrelay` LIMIT 1";
        $result = $this->db->query($sql);
        if ($result->row) {
            $row = $result->row;
        }

        return $row;
	}
	
	protected function _saveCredentials() {
	    $hostname = $this->db->escape($this->request->post['hostname']);
        $username = $this->db->escape($this->request->post['username']);
        $password = $this->db->escape($this->request->post['password']);
        
        if (empty($hostname) || empty($username) || empty($password)) {
            $this->error['warning'] = $this->language->get('error_please_fill_in_all_required_fields');
            return;
        }
        
        $validate = new Zend_Validate_Hostname();
        if (!$validate->isValid($hostname)) {
            $this->error['warning'] = $this->language->get('error_please_provide_a_valid_hostname');
            return;
        }
        
        try {
            $apiKey = '';
            $client = $this->_getClient ($hostname);
            $params['username'] = $username;
            $params['password'] = $password;
            $result = $this->_execute($client, 'doAuthentication', $params);
            
            if (is_array ($result)) {
                if ($result['status']) {
                    $apiKey = $result['data'];
                } else {
                    $this->error['warning'] = $result['error'];
                    return;
                }
            } else {
                $this->error['warning'] = $this->language->get('error_please_verify_the_hostname');
                return;
            }
            
            $row = $this->_getCredentials();
            $db_prefix = DB_PREFIX;
            
            if ($row) {
                $id = $row['id'];
                
                $sql = "UPDATE `{$db_prefix}mailrelay` SET `username` =  '$username', `password` = hex(aes_encrypt('$password', '$hostname')), `key` = '$apiKey', `hostname` = '$hostname' WHERE `id` = $id";
                $flag = $this->db->query($sql);
                
                if (!$flag) {
                    $this->error['warning'] = mysql_error();
                    return;
                }
            } else {
                $sql = "INSERT INTO `{$db_prefix}mailrelay`(`id`, `username`, `password`, `hostname`, `key`, `last_group`) 
                VALUES(NULL, '$username', hex(aes_encrypt('$password', '$hostname')), '$hostname', '$apiKey', 0)";
                $flag = $this->db->query($sql);
                
                if (!$flag) {
                    $this->error['warning'] = mysql_error();
                    return;
                }
                
                $id = $this->db->getLastId();
            }
        } catch (Exception $e) {
            $this->error['warning'] = sprintf($this->language->get('error_unable_to_connect_to'), $hostname);
            break;
        }
        $this->request->post['key'] = $apiKey;
        $this->request->post['last_group'] = 0;
        $this->data['success'] = $this->language->get('text_your_credentials_have_been_saved');
        return array (
            'id' => $id,
            'username' => $username,
            'password' => $password,
            'hostname' => $hostname,
            'key' => $apiKey,
            'last_group' => 0
        );
	}
	
	protected function _sync($credentials) {
	    if ((int)$this->request->post['last_group'] > 0) {
	        die('_sync under construction.');
	    } else {
	        $this->error['warning'] = $this->language->get('error_please_select_a_group');
	    }
	}
	
	protected function _getClient($hostName, $apiKey = null) {
        $uri = Zend_Uri_Http::fromString('http://example.com/ccm/admin/api/version/2/&type=json');
        $uri->setHost($hostName);

        $client = new Zend_Http_Client();
        $client->setUri($uri);

        if ($apiKey) {
            $client->setParameterPost('apiKey', $apiKey);
        }

        return $client;
	}
	
	protected function _execute(Zend_Http_Client $client, $function, array $params = array()) {
        $result = null;
        $client->setParameterPost('function', $function);

        foreach ( $params as $key => $value ) {
            $client->setParameterPost($key, $value);
        }

        $response = $client->request(Zend_Http_Client::POST);

        if (200 == $response->getStatus()) {
            $responseBody = $response->getBody();
            $result = Zend_Json::decode($responseBody);
        }

        return $result;
	}
	
	protected function _assignGroups($credentials) {
        $options = array (
            0 => $this->language->get('text_select_a_group')
        );
        
        if ($credentials) {
            $client = $this->_getClient($credentials['hostname'], $credentials['key']);
            $params['enable'] = true;
            $result = $this->_execute($client, 'getGroups', $params);
        	
            if ($result && $result['status']) {
                foreach($result['data'] as $item) {
                    $options[$item['id']] = $item['name'];
                }
            }
        }
        
        return $options;
	}

}
