<?php
$dirname = dirname(__FILE__);
$path = array();
$path[] = $dirname . DIRECTORY_SEPARATOR . 'library';
$path[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $path));

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace('Zend');

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

	public function uninstall() {
	    $this->db->query('DROP TABLE IF EXISTS `' . DB_PREFIX . 'mailrelay`');

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('mailrelay', array('mailrelay_status' => 0));
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
				'text_home',
				'text_module',
				'text_config',
				'text_sync',
				'text_start_sync',
				'text_success',
				'entry_hostname',
				'entry_key',
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

		if (!isset($this->data['success']) || $this->data['error_warning'] != '') {
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

        $sql = "SELECT `id`, `hostname`, `key`, `last_group` FROM `{$db_prefix}mailrelay` LIMIT 1";
        $result = $this->db->query($sql);
        if ($result->row) {
            $row = $result->row;
        }

        return $row;
	}

	protected function _saveCredentials() {
	    $hostname = $this->db->escape($this->request->post['hostname']);
        $key = $this->db->escape($this->request->post['key']);

        if (empty($hostname) || empty($key)) {
            $this->error['warning'] = $this->language->get('error_please_fill_in_all_required_fields');
            return;
        }

        $validate = new Zend_Validate_Hostname();
        if (!$validate->isValid($hostname)) {
            $this->error['warning'] = $this->language->get('error_please_provide_a_valid_hostname');
            return;
        }

        try {
            $row = $this->_getCredentials();
            $db_prefix = DB_PREFIX;

            if ($row) {
                $id = $row['id'];

                $sql = "UPDATE `{$db_prefix}mailrelay` SET `hostname` = '$hostname', `key` = '$key' WHERE `id` = $id";
                $flag = $this->db->query($sql);

                if (!$flag) {
                    $this->error['warning'] = mysql_error();
                    return;
                }
            } else {
                $sql = "INSERT INTO `{$db_prefix}mailrelay`(`id`, `hostname`, `key`, `last_group`) VALUES(NULL, '$hostname', '$key', 0)";
                $flag = $this->db->query($sql);

                if (!$flag) {
                    $this->error['warning'] = mysql_error();
                    return;
                }

                $id = $this->db->getLastId();
            }
        } catch(Exception $e) {
            $this->error['warning'] = sprintf($this->language->get('error_unable_to_connect_to'), $hostname);
            break;
        }

        $this->request->post['key'] = $key;
        $this->request->post['last_group'] = 0;
        $this->data['success'] = $this->language->get('text_your_credentials_have_been_saved');
        return array(
            'id' => $id,
            'hostname' => $hostname,
            'key' => $key,
            'last_group' => 0
        );
	}

	protected function _sync($credentials) {
	    $group = (int)$this->request->post['last_group'];
	    if ($group > 0) {
            $db_prefix = DB_PREFIX;
            $summary['total'] = 0;
            $summary['new'] = 0;
            $summary['updated'] = 0;
            $summary['failed'] = 0;

            $client = $this->_getClient($credentials['hostname'], $credentials['key']);

            $sql = "SELECT * FROM `{$db_prefix}customer` WHERE `newsletter` = 1";
			$rowset = $this->db->query($sql);

            foreach($rowset->rows as $row) {
                $name = "{$row['firstname']} {$row['lastname']}";
                $email = $row['email'];

                $params = array();
                $params['email'] = $email;
                $result = $this->_execute($client, 'getSubscribers', $params);

                if ($result) {
                    $summary['total']++;

                    if (!count($result['data'])) {
                        $params['name'] = $name;
                        $params['groups'] = array($group);
                        $result = $this->_execute($client, 'addSubscriber', $params);

                        if ($result && 1 == $result['status']) {
                            $summary['new'] ++;
                        } else {
                            $summary['failed'] ++;
                        }
                    } else {
                        $params['id'] = $result['data'][0]['id'];
                        $params['name'] = $name;
                        $params['groups'] = array($group);
                        $result = $this->_execute($client, 'updateSubscriber', $params);

                    	if ($result && 1 == $result['status']) {
                            $summary['updated'] ++;
                    	} else {
                            $summary['failed'] ++;
                    	}
                    }
                } else {
                    $summary['failed'] ++;
                }
            }

            // Update the last selected group
            $sql = "UPDATE `{$db_prefix}mailrelay` SET `last_group` = $group";
            $this->db->query($sql);

            $message  = '';
            $message .= $this->language->get('text_total_subscribers') . ": ({$summary['total']})<br />";
            $message .= $this->language->get('text_new_subscribers') . ": ({$summary['new']})<br />";
            $message .= $this->language->get('text_updated_subscribers') . ": ({$summary['updated']})<br />";
            $message .= $this->language->get('text_failed_subscribers') . ": ({$summary['failed']})<br />";

            $this->data['success'] = $message;
	    } else {
	        $this->error['warning'] = $this->language->get('error_please_select_a_group');
	    }
	}

	protected function _getClient($hostName, $key = null) {
        $uri = Zend_Uri_Http::fromString('http://example.com/ccm/admin/api/version/2/&type=json');
        $uri->setHost($hostName);

        $client = new Zend_Http_Client();
        $client->setUri($uri);

        if ($key) {
            $client->setParameterPost('apiKey', $key);
        }

        return $client;
	}

	protected function _execute(Zend_Http_Client $client, $function, array $params = array()) {
        $result = null;

        $client->setHeaders('X-Request-Origin: OpenCart|1.1|'. VERSION);

        $client->setParameterPost('function', $function);

        foreach ($params as $key => $value) {
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
        $options = array(
            0 => $this->language->get('text_select_a_group')
        );

        if ($credentials) {
            $validate = new Zend_Validate_Hostname();
		    if (!$validate->isValid($credentials['hostname'])) {
		    	$this->error['warning'] = $this->language->get('error_please_provide_a_valid_hostname');
		    	return $options;
		    }

            $client = $this->_getClient($credentials['hostname'], $credentials['key']);
            $params['enable'] = true;
            $result = $this->_execute($client, 'getGroups', $params);

            if ($result && $result['status']) {
                foreach($result['data'] as $item) {
                    $options[$item['id']] = $item['name'];
                }
            } else {
                $this->error['warning'] = $this->language->get('error_invalid_api_key');
		    	return $options;
            }
        }

        return $options;
	}

}
