<?php

	Class extension_export_ensemble extends Extension{

		public function about(){
			return array('name' => 'Export Ensemble',
						 'version' => '1.2',
						 'release-date' => '2011-01-02',
						 'author' => array('name' => 'Alistair Kearney',
										   'website' => 'http://pointybeard.com',
										   'email' => 'alistair@pointybeard.com')
				 		);
		}

		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),

					);
		}

		public function install(){

			if(!class_exists('ZipArchive')){
				if(isset(Administration::instance()->Page)){
					Administration::instance()->Page->pageAlert(__('Export Ensemble cannot be installed, since the "<a href="http://php.net/manual/en/book.zip.php">ZipArchive</a>" class is not available. Ensure that PHP was compiled with the <code>--enable-zip</code> flag.'), AdministrationPage::PAGE_ALERT_ERROR);
				}
				return false;
			}
		}

		private function __addFolderToArchive(&$archive, $path, $parent=NULL){
			$iterator = new DirectoryIterator($path);
			foreach($iterator as $file){
				if($file->isDot() || preg_match('/^\./', $file->getFilename())) continue;

				elseif($file->isDir()){
					$this->__addFolderToArchive($archive, $file->getPathname(), $parent);
				}

				else $archive->addFile($file->getPathname(), ltrim(str_replace($parent, NULL, $file->getPathname()), '/'));
			}
		}

		private function __export(){
			$sql_schema = $sql_data = NULL;

			require_once(dirname(__FILE__) . '/lib/class.mysqldump.php');

			$dump = new MySQLDump(Symphony::Database());

			$tables = array(
				'tbl_authors',
				'tbl_cache',
				'tbl_entries',
				'tbl_extensions',
				'tbl_extensions_delegates',
				'tbl_fields',
				'tbl_fields_%',
				'tbl_forgotpass',
				'tbl_pages',
				'tbl_pages_types',
				'tbl_sections',
				'tbl_sections_association'
			);

			## Grab the schema
			foreach($tables as $t) $sql_schema .= $dump->export($t, MySQLDump::STRUCTURE_ONLY);
			$sql_schema = str_replace('`' . Symphony::Configuration()->get('tbl_prefix', 'database'), '`tbl_', $sql_schema);

			$sql_schema = preg_replace('/AUTO_INCREMENT=\d+/i', NULL, $sql_schema);

			$tables = array(
				'tbl_entries',
				'tbl_extensions',
				'tbl_extensions_delegates',
				'tbl_fields',
				'tbl_pages',
				'tbl_pages_types',
				'tbl_sections',
				'tbl_sections_association'
			);

			## Field data and entry data schemas needs to be apart of the workspace sql dump
			$sql_data  = $dump->export('tbl_fields_%', MySQLDump::ALL);
			$sql_data .= $dump->export('tbl_entries_%', MySQLDump::ALL);

			## Grab the data
			foreach($tables as $t){
				$sql_data .= $dump->export($t, MySQLDump::DATA_ONLY);
			}

			$sql_data = str_replace('`' . Symphony::Configuration()->get('tbl_prefix', 'database'), '`tbl_', $sql_data);

			## Write the install.sql file to the workspace
			$path = '/workspace';

			$filename = 'install.sql';

			if(FALSE !== file_put_contents(DOCROOT . $path . '/' . $filename, $sql_data)) {
				Administration::instance()->Page->pageAlert(__('SQL data successfully dumped into <code>%s/%s</code>.',array($path,$filename)), Alert::SUCCESS);
			}
			else {
				Administration::instance()->Page->pageAlert(__('An error occurred while trying to write <code>%s/%s</code>.',array($path,$filename)), Alert::ERROR);
			}

		}

		public function __SavePreferences($context){
			$this->__export();
		}

		public function appendPreferences($context){

			if(isset($_POST['action']['export'])){
				$this->__SavePreferences($context);
			}

			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Save Forum Install File')));


			$div = new XMLElement('div', NULL, array('id' => 'file-actions', 'class' => 'label'));
			$span = new XMLElement('span', NULL, array('class' => 'frame'));

			$span->appendChild(new XMLElement('button', __('Save Forum Install File'), array('name' => 'action[export]', 'type' => 'submit')));

			$div->appendChild($span);

			$div->appendChild(new XMLElement('p', __('Saves the <code>install.sql</code> file to the <code>workspace</code> directory.'), array('class' => 'help')));

			$group->appendChild($div);
			$context['wrapper']->appendChild($group);

		}
	}
