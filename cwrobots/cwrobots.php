<?php
/**
 * @copyright    Copyright (C) 2017 creamiweb.es. All rights reserved.
 * @author Andres Castellano Escobar <acastellano@creamiweb.es>
 * @version v1.0
 */
if(!defined('_PS_VERSION_'))
 exit;

class Cwrobots extends Module
{
 public function __construct()
 {
  $this->name = 'cwrobots';
  $this->tab = 'seo';
  $this->version = '2.1.3';
  $this->author = 'Andres Castellano https://www.linkedin.com/in/andrescastellano/';
  $this->need_instance = 0;
  $this->ps_versions_compliancy = array('min' => '1.7', 'max' => '1.7.99.99');
  $this->bootstrap = true;

  parent::__construct();

  $this->displayName = $this->l('Robots NoIndex');
  $this->description = $this->l('Set category, page or product robots noindex');

  $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 }

 public function install()
 {
  if(!parent::install() ||
   !$this->registerHook('header') ||
   !$this->installDb() ||
   !Configuration::updateValue('cw_robots_category_enable', '1') ||
   !Configuration::updateValue('cw_robots_page_enable', '1')
  ){
   return false;
  }
  $this->_clearCache('cwrobots.tpl');
  $this->_clearCache('header.tpl');

  return true;
 }

 public function uninstall()
 {
  $this->_clearCache('cwrobots.tpl');
  $this->_clearCache('header.tpl');
  if(!parent::uninstall() ||
   !$this->uninstallDB() ||
   !Configuration::deleteByName('cw_robots_category_enable') ||
   !Configuration::deleteByName('cw_robots_page_enable')
  )
   return false;

  return true;
 }

 public function installDb()
 {
  return (Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cwrobots` (
			`id_content` VARCHAR(10) NOT NULL PRIMARY KEY,
			`noindex` INT(11) UNSIGNED NOT NULL,
			INDEX (`id_content`)
		) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;'));
 }

 protected function uninstallDb()
 {
  Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'cwrobots`');

  return true;
 }

 public function getContent()
 {
  $this->html = '';

  if(Tools::isSubmit('submit' . $this->name)){
   Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'cwrobots` WHERE 1=1');
   foreach($_POST as $key => $value){
    if(substr($key, 0, 3) == 'cat' || substr($key, 0, 3) == 'pag'){
     Db::getInstance()->insert(
      'cwrobots',
      array(
       'id_content' => $key,
       'noindex' => (int)$value
      )
     );
    }
   }
   if($_POST['products_ids'] != ''){
    $pids = explode(',', $_POST['products_ids']);
    foreach($pids as $key){
     $value = 1;
     Db::getInstance()->insert(
      'cwrobots',
      array(
       'id_content' => 'pro_' . $key,
       'noindex' => (int)$value
      )
     );
    }
   }
   Configuration::updateValue('cw_robots_category_enable', 1);
   Configuration::updateValue('cw_robots_page_enable', 1);
   $this->_clearCache('cwrobots.tpl');
   $this->_clearCache('header.tpl');
   $this->html .= $this->displayConfirmation($this->l('Settings updated successfully'));
  }
  if(Tools::isSubmit('clearcache' . $this->name)){
   $this->_clearCache('cwrobots.tpl');
   $this->_clearCache('header.tpl');
   $this->html .= $this->displayConfirmation($this->l('Clear cache successfully'));
  }

  return $this->html . $this->renderForm();
 }

 public function renderForm()
 {
  // Get default language
  $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

  $fields_form = array(
   'form' => array(
    'legend' => array(
     'title' => $this->l('Settings'),
     'icon' => 'icon-cogs'
    ),
    'tabs' => array('cat' => $this->l('Categories'), 'pag' => $this->l('Pages'), 'prod' => $this->l('Products')),
    'input' => array(),
    'submit' => array(
     'title' => $this->l('Save'),
     'class' => 'btn btn-default pull-right'
    ),
    'buttons' => array(
     array(
      'title' => $this->l('Clear Cache'),
      'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&clearcache' . $this->name .
       '&token=' . Tools::getAdminTokenLite('AdminModules'),
      'icon' => 'process-icon-refresh',
      'class' => 'btn btn-default pull-right'
     )
    )
   )
  );
  $maxdepth = 0;
  $range = '';
  $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT c.id_parent, c.id_category, cl.name, cl.description, cl.link_rewrite
			FROM `' . _DB_PREFIX_ . 'category` c
			INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.`id_lang` = ' . (int)$this->context->language->id . Shop::addSqlRestrictionOnLang('cl') . ')
			INNER JOIN `' . _DB_PREFIX_ . 'category_shop` cs ON (cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . (int)$this->context->shop->id . ')
			WHERE (c.`active` = 1 OR c.`id_category` = ' . (int)Configuration::get('PS_HOME_CATEGORY') . ')
			AND c.`id_category` != ' . (int)Configuration::get('PS_ROOT_CATEGORY') . '
			' . ((int)$maxdepth != 0 ? ' AND `level_depth` <= ' . (int)$maxdepth:'') . '
			' . $range . '
			ORDER BY `level_depth` ASC, cs.`position` ASC');
  foreach($result as &$row){
   array_push($fields_form['form']['input'],
    array(
     'type' => 'switch',
     'label' => $this->l($row['name']),
     'name' => 'cat_' . $row['id_category'],
     'desc' => $this->l('Yes = NoIndex'),
     'is_bool' => false,
     'tab' => 'cat',
     'values' => array(
      array(
       'id' => 'active_on',
       'value' => 0,
       'label' => $this->l('Index')
      ),
      array(
       'id' => 'active_off',
       'value' => 1,
       'label' => $this->l('NoIndex')
      )
     ),
    )
   );
  }

  $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT m.id_meta, m.page, ml.title, ml.url_rewrite
			FROM `' . _DB_PREFIX_ . 'meta` m
			INNER JOIN `' . _DB_PREFIX_ . 'meta_lang` ml ON (m.`id_meta` = ml.`id_meta` AND ml.`id_lang` = ' . (int)$this->context->language->id . Shop::addSqlRestrictionOnLang('ml') . ')
			ORDER BY `id_meta` ASC');
  foreach($result as &$row){
   array_push($fields_form['form']['input'],
    array(
     'type' => 'switch',
     'label' => $row['title'] . ' (' . $row['page'] . ')',
     'name' => 'pag_' . $row['id_meta'],
     'desc' => $this->l('Yes = NoIndex'),
     'is_bool' => false,
     'tab' => 'pag',
     'values' => array(
      array(
       'id' => 'active_on',
       'value' => 0,
       'label' => $this->l('Index')
      ),
      array(
       'id' => 'active_off',
       'value' => 1,
       'label' => $this->l('NoIndex')
      )
     ),
    )
   );
  }

  //input products
  array_push($fields_form['form']['input'],
   array(
    'type' => 'text',
    'label' => $this->l('Products IDs'),
    'name' => 'products_ids',
    'desc' => $this->l('Type products IDs separated by coma'),
    'is_bool' => false,
    'tab' => 'prod',
   )
  );

  $helper = new HelperForm();
  // Module, token and currentIndex
  $helper->module = $this;
  $helper->name_controller = $this->name;
  $helper->token = Tools::getAdminTokenLite('AdminModules');
  $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

  // Language
  $helper->default_form_language = $default_lang;
  $helper->allow_employee_form_lang = $default_lang;

  $helper->title = $this->displayName;
  $helper->show_toolbar = true;
  $helper->toolbar_scroll = true;
  $helper->table = $this->table;
  $helper->identifier = $this->identifier;
  $helper->submit_action = 'submit' . $this->name;
  $helper->tpl_vars = array(
   'fields_value' => $this->getConfigFieldsValues(),
   'languages' => $this->context->controller->getLanguages(),
   'id_language' => $this->context->language->id
  );

  return $helper->generateForm(array($fields_form));
 }

 public function getConfigFieldsValues()
 {
  $values = array();
  $productsIds = '';
  $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT *
			FROM `' . _DB_PREFIX_ . 'cwrobots`');
  foreach($result as &$row){
   $values[$row['id_content']] = $row['noindex'];
   if(substr($row['id_content'], 0, 3) == 'pro'){
    $productsIds .= substr($row['id_content'], 4, strlen($row['id_content'])) . ',';
   }
  }
  $values['products_ids'] = trim($productsIds, ',');

  return $values;
 }

 public function hookDisplayHeader($params)
 {

  $page = $this->context->smarty->getVariable('page');
  $phpself = $this->context->controller->php_self;
  $confVars = array('phpself' => $phpself);
  $this->smarty->assign('confVars', $confVars);

  if($phpself != NULL && $phpself == 'category'){
   $category = $this->context->controller->getCategory();
   $catId = $category->id;

   $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT *
			FROM `' . _DB_PREFIX_ . 'cwrobots`
			WHERE id_content="cat_' . $catId . '"');
   if($result){
    if($result['noindex'] == '1'){
     $page->value['meta']['robots'] = 'noindex';
     $this->context->smarty->assignGlobal('page', $page);
    }

    $html = $this->display(__FILE__, 'header.tpl');

    return $html;
   }
  }

  if($phpself != NULL && $phpself == 'product'){
   $product = $this->context->controller->getProduct();
   $catId = $product->id;

   $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT *
			FROM `' . _DB_PREFIX_ . 'cwrobots`
			WHERE id_content="pro_' . $catId . '"');
   if($result){
    if($result['noindex'] == '1'){
     $page->value['meta']['robots'] = 'noindex';
     $this->context->smarty->assignGlobal('page', $page);
    }
    $html = $this->display(__FILE__, 'header.tpl');

    return $html;
   }
  }

  if($phpself != NULL && $phpself != 'category' && $phpself != 'product' && ($cont = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'meta` WHERE page="' . $phpself . '"'))){
   $pagId = $cont['id_meta'];
   $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow('
			SELECT *
			FROM `' . _DB_PREFIX_ . 'cwrobots`
			WHERE id_content="pag_' . $pagId . '"');
   if($result){
    if($result['noindex'] == '1'){
     $page->value['meta']['robots'] = 'noindex';
     $this->context->smarty->assignGlobal('page', $page);
    }

    $html = $this->display(__FILE__, 'header.tpl');

    return $html;
   }
  }

  return '';
 }
}

?>