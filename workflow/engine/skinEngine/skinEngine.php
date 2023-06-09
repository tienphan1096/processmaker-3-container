<?php
/**
 * Class SkinEngine
 *
 * This class load and dispatch the main systems layouts
 */

use ProcessMaker\Core\System;
use ProcessMaker\Plugins\PluginRegistry;

define('SE_LAYOUT_NOT_FOUND', 6);

class SkinEngine
{
    private $skinDefault = '';

    private $layout = '';
    private $template = '';
    private $skin = '';
    private $content = '';
    private $mainSkin = '';

    private $skinFiles = array();

    private $forceTemplateCompile = true;
    private $skinVariants = array();

    private $skinsBasePath = array();
    private $configurationFile = array();
    private $layoutFile = array();
    private $layoutFileBlank = array();
    private $layoutFileExtjs = array();
    private $layoutFileRaw = array();
    private $layoutFileTracker = array();
    private $layoutFileSubmenu = array();
    private $layoutFileViena = array();

    private $cssFileName = '';

    public function __construct($template, $skin, $content)
    {
        $this->template = $template;
        $this->skin = $skin;
        $this->content = $content;
        $this->skinVariants = array('blank', 'extjs', 'raw', 'tracker', 'submenu');
        $this->skinsBasePath = G::ExpandPath("skinEngine");
        $sysConf = System::getSystemConfiguration(PATH_CONFIG . 'env.ini');
        $this->skinDefault = (isset($sysConf['default_skin']) && $sysConf['default_skin'] != '') ? $sysConf['default_skin'] : 'classic';
        $this->_init();
    }

    private function _init()
    {
        // setting default skin
        if (!isset($this->skin) || $this->skin == "") {
            $this->skin = $this->skinDefault;
        }

        // deprecated submenu type ""green-submenu"" now is mapped to "submenu"
        if ($this->skin == "green-submenu") {
            $this->skin = "submenu";
        }

        if (!in_array(strtolower($this->skin), $this->skinVariants)) {
            $this->forceTemplateCompile = true; //Only save in session the main SKIN

            if (isset($_SESSION['currentSkin']) && $_SESSION['currentSkin'] != $this->skin) {
                $this->forceTemplateCompile = true;
            }
            $_SESSION['currentSkin'] = SYS_SKIN;
        } else {
            $_SESSION['currentSkin'] = SYS_SKIN;
            $_SESSION['currentSkinVariant'] = $this->skin;
        }

        // setting default skin
        if (!isset($_SESSION['currentSkin'])) {
            $_SESSION['currentSkin'] = $this->skinDefault;
        }

        $this->mainSkin = $_SESSION['currentSkin'];


        $skinObject = null;

        //Set defaults "classic"
        $configurationFile = $this->skinsBasePath . 'base' . PATH_SEP . 'config.xml';
        $layoutFile = $this->skinsBasePath . 'base' . PATH_SEP . 'layout.html';
        $layoutFileBlank = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-blank.html';
        $layoutFileExtjs = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-extjs.html';
        $layoutFileRaw = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-raw.html';
        $layoutFileTracker = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-tracker.html';
        $layoutFileSubmenu = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-submenu.html';
        $layoutFileViena = $this->skinsBasePath . 'base' . PATH_SEP . 'layout-viena.html';

        //Based on requested Skin look if there is any registered with that name
        if (strtolower($this->mainSkin) != "classic") {
            if (defined('PATH_CUSTOM_SKINS') && is_dir(PATH_CUSTOM_SKINS . $this->mainSkin)) { // check this skin on user skins path
                $skinObject = PATH_CUSTOM_SKINS . $this->mainSkin;
            } else {
                if (is_dir($this->skinsBasePath . $this->mainSkin)) { // check this skin on core skins path
                    $skinObject = $this->skinsBasePath . $this->mainSkin;
                } else { //Skin doesn't exist
                    $this->mainSkin = $this->skinDefault;
                    if (defined('PATH_CUSTOM_SKINS') && is_dir(PATH_CUSTOM_SKINS . $this->mainSkin)) { // check this skin on user skins path
                        $skinObject = PATH_CUSTOM_SKINS . $this->mainSkin;
                    } else {
                        if (is_dir($this->skinsBasePath . $this->mainSkin)) { // check this skin on core skins path
                            $skinObject = $this->skinsBasePath . $this->mainSkin;
                        }
                    }
                }
            }
        }

        //This should have an XML definition and a layout html
        if ($skinObject && file_exists($skinObject . PATH_SEP . 'config.xml')
            && file_exists($skinObject . PATH_SEP . 'layout.html')
        ) {
            $configurationFile = $skinObject . PATH_SEP . 'config.xml';
            $layoutFile = $skinObject . PATH_SEP . 'layout.html';

            if (file_exists($skinObject . PATH_SEP . 'layout-blank.html')) {
                $layoutFileBlank = $skinObject . PATH_SEP . 'layout-blank.html';
            }
            if (file_exists($skinObject . PATH_SEP . 'layout-extjs.html')) {
                $layoutFileExtjs = $skinObject . PATH_SEP . 'layout-extjs.html';
            }
            if (file_exists($skinObject . PATH_SEP . 'layout-raw.html')) {
                $layoutFileRaw = $skinObject . PATH_SEP . 'layout-raw.html';
            }
            if (file_exists($skinObject . PATH_SEP . 'layout-tracker.html')) {
                $layoutFileTracker = $skinObject . PATH_SEP . 'layout-tracker.html';
            }
            if (file_exists($skinObject . PATH_SEP . 'layout-submenu.html')) {
                $layoutFileSubmenu = $skinObject . PATH_SEP . 'layout-submenu.html';
            }
            if (file_exists($skinObject . PATH_SEP . 'layout-viena.html')) {
                $layoutFileViena = $skinObject . PATH_SEP . 'layout-viena.html';
            }
        }

        $this->layoutFile = pathInfo($layoutFile);
        $this->layoutFileBlank = pathInfo($layoutFileBlank);
        $this->layoutFileExtjs = pathInfo($layoutFileExtjs);
        $this->layoutFileTracker = pathInfo($layoutFileTracker);
        $this->layoutFileRaw = pathInfo($layoutFileRaw);
        $this->layoutFileSubmenu = pathInfo($layoutFileSubmenu);
        $this->layoutFileViena = pathInfo($layoutFileViena);

        $this->cssFileName = $this->mainSkin;

        if ($this->skin != $this->mainSkin && in_array(strtolower($this->skin), $this->skinVariants)) {
            $this->cssFileName .= "-" . $this->skin;
        }
    }

    public function setLayout($layout)
    {
        $this->layout = $layout;
    }

    public function dispatch()
    {
        $skinMethod = '_' . strtolower($this->skin);

        try {
            if (!method_exists($this, $skinMethod)) {
                $skinMethod = '_default';
            }

            $this->$skinMethod();
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case SE_LAYOUT_NOT_FOUND:

                    $data['exception_type'] = G::LoadTranslation('ID_SKIN_EXCEPTION');
                    $data['exception_title'] = G::LoadTranslation('ID_SKIN_LAYOUT_NOT_FOUND');
                    $data['exception_message'] = G::LoadTranslation('ID_SKIN_INCORRECT_VERIFY_URL');
                    $data['exception_list'] = array();
                    if (substr($this->mainSkin, 0, 2) != 'ux') {
                        $url = '../login/login';
                    } else {
                        $url = '../main/login';
                    }

                    $link = '<a href="' . $url . '">Try Now</a>';

                    $data['exception_notes'][] = G::LoadTranslation('ID_REDIRECT_URL') . $link;

                    G::renderTemplate(PATH_TPL . 'exception', $data);
                    break;
            }

            exit(0);
        }
    }

    /**
     * Skins Alternatives
     */

    private function _raw()
    {
        G::verifyPath(PATH_SMARTY_C, true);
        G::verifyPath(PATH_SMARTY_CACHE, true);

        $smarty = new Smarty();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty->template_dir = $this->layoutFileRaw['dirname'];
        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';

        if (isset($oHeadPublisher)) {
            $header = $oHeadPublisher->printRawHeader();
        }

        $smarty->assign('header', $header);
        $smarty->force_compile = $this->forceTemplateCompile;
        $smarty->display($this->layoutFileRaw['basename']);
    }

    private function _plain()
    {
        $oHeadPublisher = headPublisher::getSingleton();
        echo $oHeadPublisher->renderExtJs();
    }

    private function _extjs()
    {
        $oServerConf = ServerConf::getSingleton();
        $oHeadPublisher = headPublisher::getSingleton();

        if ($oHeadPublisher->extJsInit === true) {
            $header = $oHeadPublisher->getExtJsVariablesScript();
            $styles = $oHeadPublisher->getExtJsStylesheets($this->cssFileName);
            $body = $oHeadPublisher->getExtJsScripts();

            //default
            $templateFile = G::ExpandPath("skinEngine") . 'base' . PATH_SEP . 'extJsInitLoad.html';
            //Custom skins
            if (defined('PATH_CUSTOM_SKINS') && is_dir(PATH_CUSTOM_SKINS . $this->mainSkin)) {
                $templateFile = PATH_CUSTOM_SKINS . $this->mainSkin . PATH_SEP . 'extJsInitLoad.html';
            }
            //Skin uxs - simplified
            if (!isset($_SESSION['user_experience'])) {
                $_SESSION['user_experience'] = 'NORMAL';
            }
            if ($_SESSION['user_experience'] != 'NORMAL') {
                $templateFile = (is_dir(PATH_CUSTOM_SKINS . 'uxs')) ? PATH_CUSTOM_SKINS . 'simplified' . PATH_SEP . 'extJsInitLoad.html' : $templateFile;
            }
        } else {
            $styles = "";
            $header = $oHeadPublisher->getExtJsStylesheets($this->cssFileName);
            $header .= $oHeadPublisher->includeExtJs();
            $body = $oHeadPublisher->renderExtJs();

            $templateFile = $this->layoutFile['dirname'] . PATH_SEP . $this->layoutFileExtjs['basename'];
        }

        $template = new TemplatePower($templateFile);
        $template->prepare();
        $header = '<meta name="csrf-token" content="' . csrfToken() . '" />' . "\n" . $header;
        $template->assign('header', $header);
        $template->assign('styles', $styles);
        $template->assign('bodyTemplate', $body);
        $template->assign('csrf_token', csrfToken());

        $doctype = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">";
        $meta = null;
        $dirBody = null;

        if (isset($_SERVER["HTTP_USER_AGENT"]) && preg_match(
                "/^.*\(.*Trident.(\d+)\..+\).*$/",
                $_SERVER["HTTP_USER_AGENT"],
                $arrayMatch
            )
        ) {

            //Get the IE version
            if (preg_match(
                    "/^.*\(.*MSIE (\d+)\..+\).*$/",
                    $_SERVER["HTTP_USER_AGENT"],
                    $arrayMatch
                ) || preg_match("/^.*\(.*rv.(\d+)\..+\).*$/", $_SERVER["HTTP_USER_AGENT"], $arrayMatch)
            ) {
                $ie = intval($arrayMatch[1]);
            }
            $isIE = Bootstrap::isIE();

            $swTrident = (preg_match("/^.*Trident.*$/", $_SERVER["HTTP_USER_AGENT"])) ? 1 : 0; //Trident only in IE8+

            $sw = 1;

            if ((($ie == 7 && $swTrident == 1) || $ie == 8) && !preg_match("/^ux.+$/", SYS_SKIN)) { //IE8
                $sw = 0;
            }


            if ($sw == 1) {
                if ($ie == 10 || $ie == 11) {
                    $ie = 8;
                }

                $doctype = null;
                $meta = "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=$ie\" />";

                if (SYS_COLLECTION == 'cases') {
                    if ($isIE) {
                        $meta = "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\" />";
                    }
                }
            }
        }

        $serverConf = ServerConf::getSingleton();

        if ($serverConf->isRtl(SYS_LANG)) {
            $dirBody = "dir=\"RTL\"";
        }

        $template->assign("doctype", $doctype);

        if (defined('LOAD_HEADERS_IE') && LOAD_HEADERS_IE == 0) {
            $template->assign('meta', $meta);
        }

        $template->assign("dirBody", $dirBody);

        echo $template->getOutputContent();
    }

    /**
     * Render "viena" view
     */
    private function _viena()
    {
        $templateFile = $this->layoutFile['dirname'] . PATH_SEP . $this->layoutFileViena['basename'];
        if (file_exists($templateFile)) {
            $oHeadPublisher = headPublisher::getSingleton();
            $header = $oHeadPublisher->getExtJsStylesheets($this->cssFileName . '-viena');
            $body = $oHeadPublisher->getExtJsVariablesScript('');

            $template = new TemplatePower($templateFile);
            $template->prepare();
            $template->assign('header', $header);
            $template->assign('bodyTemplate', $body);
            echo $template->getOutputContent();
        } else {
            $userCanAccess = 1;
            echo View::make('Views::home.home', compact('userCanAccess'))->render();
        }
    }

    private function _blank()
    {
        G::verifyPath(PATH_SMARTY_C, true);
        G::verifyPath(PATH_SMARTY_CACHE, true);

        $smarty = new Smarty();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty->template_dir = $this->layoutFileBlank['dirname'];
        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';

        if (isset($oHeadPublisher)) {
            $header = $oHeadPublisher->printHeader();
            $header .= $oHeadPublisher->getExtJsStylesheets($this->cssFileName);
        }

        $smarty->assign('username',
            (isset($_SESSION['USR_USERNAME']) ? '(' . $_SESSION['USR_USERNAME'] . ' ' . G::LoadTranslation('ID_IN') . ' ' . config("system.workspace") . ')' : ''));
        $smarty->assign('header', $header);
        $smarty->force_compile = $this->forceTemplateCompile;

        // display
        $smarty->display($this->layoutFileBlank['basename']);
    }

    private function _submenu()
    {
        global $G_ENABLE_BLANK_SKIN;
        //menu
        global $G_MAIN_MENU;
        global $G_SUB_MENU;
        global $G_MENU_SELECTED;
        global $G_SUB_MENU_SELECTED;
        global $G_ID_MENU_SELECTED;
        global $G_ID_SUB_MENU_SELECTED;

        G::verifyPath(PATH_SMARTY_C, true);
        G::verifyPath(PATH_SMARTY_CACHE, true);

        $smarty = new Smarty();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty->template_dir = $this->layoutFileSubmenu['dirname'];
        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';

        if (isset($G_ENABLE_BLANK_SKIN) && $G_ENABLE_BLANK_SKIN) {
            $smarty->display($layoutFileBlank['basename']);
        } else {
            $header = '';

            if (isset($oHeadPublisher)) {
                $oHeadPublisher->title = isset($_SESSION['USR_USERNAME']) ? '(' . $_SESSION['USR_USERNAME'] . ' ' . G::LoadTranslation('ID_IN') . ' ' . config("system.workspace") . ')' : '';
                $header = $oHeadPublisher->printHeader();
                $header .= $oHeadPublisher->getExtJsStylesheets($this->cssFileName);
            }

            $footer = '';

            if (strpos($_SERVER['REQUEST_URI'], '/login/login') !== false) {
                $freeOfChargeText = "";
                if (!defined('SKIP_FREE_OF_CHARGE_TEXT')) {
                    $freeOfChargeText = "Supplied free of charge with no support, certification, warranty, <br>maintenance nor indemnity by Processmaker and its Certified Partners.";
                }
                if (file_exists(PATH_CLASSES . "class.pmLicenseManager.php")) {
                    $freeOfChargeText = "";
                }

                $fileFooter = PATH_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                if (file_exists($fileFooter)) {
                    $footer .= file_get_contents($fileFooter);
                } else {
                    $fileFooter = PATH_SKIN_ENGINE . SYS_SKIN . PATH_SEP . 'footer.html';
                    if (file_exists($fileFooter)) {
                        $footer .= file_get_contents($fileFooter);
                    } else {
                        $fileFooter = PATH_CUSTOM_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                        if (file_exists($fileFooter)) {
                            $footer .= file_get_contents($fileFooter);
                        } else {
                            $footer .= "$freeOfChargeText  <br />Copyright &copy; 2000-" . date('Y') . " <a href=\"http://www.processmaker.com\" alt=\"ProcessMaker Inc.\" target=\"_blank\">ProcessMaker </a>Inc. All rights reserved.<br />" . "<br><br/><a href=\"http://www.processmaker.com\" alt=\"Powered by ProcessMaker - Open Source Workflow & Business Process Management (BPM) Management Software\" title=\"Powered by ProcessMaker\" target=\"_blank\"></a>";
                        }
                    }
                }
            }

            $oMenu = new Menu();
            $menus = $oMenu->generateArrayForTemplate(
                $G_MAIN_MENU,
                'SelectedMenu',
                'mainMenu',
                $G_MENU_SELECTED,
                $G_ID_MENU_SELECTED
            );
            $smarty->assign('menus', $menus);

            if (substr(SYS_SKIN, 0, 2) == 'ux') {
                $smarty->assign('exit_editor', 1);
                $smarty->assign('exit_editor_label', G::loadTranslation('ID_CLOSE_EDITOR'));
            }

            $oSubMenu = new Menu();
            $subMenus = $oSubMenu->generateArrayForTemplate(
                $G_SUB_MENU,
                'selectedSubMenu',
                'subMenu',
                $G_SUB_MENU_SELECTED,
                $G_ID_SUB_MENU_SELECTED
            );
            $smarty->assign('subMenus', $subMenus);

            if (!defined('NO_DISPLAY_USERNAME')) {
                define('NO_DISPLAY_USERNAME', 0);
            }
            if (NO_DISPLAY_USERNAME == 0) {
                $smarty->assign('userfullname', isset($_SESSION['USR_FULLNAME']) ? $_SESSION['USR_FULLNAME'] : '');
                $smarty->assign('user', isset($_SESSION['USR_USERNAME']) ? '(' . $_SESSION['USR_USERNAME'] . ')' : '');
                $smarty->assign('rolename', isset($_SESSION['USR_ROLENAME']) ? $_SESSION['USR_ROLENAME'] . '' : '');
                $smarty->assign('pipe', isset($_SESSION['USR_USERNAME']) ? ' | ' : '');
                $smarty->assign('logout', G::LoadTranslation('ID_LOGOUT'));
                $smarty->assign('workspace', !empty(config("system.workspace")) ? config("system.workspace") : '');
                $uws = (isset($_SESSION['USR_ROLENAME']) && $_SESSION['USR_ROLENAME'] != '') ? strtolower(G::LoadTranslation('ID_WORKSPACE_USING')) : G::LoadTranslation('ID_WORKSPACE_USING');
                $smarty->assign('workspace_label', $uws);

                $conf = new Configurations();
                $conf->getFormats();
                $name = $conf->userNameFormat(
                    isset($_SESSION['USR_USERNAME']) ? $_SESSION['USR_USERNAME'] : '',
                    isset($_SESSION['USR_FULLNAME']) ? htmlentities(
                        $_SESSION['USR_FULLNAME'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) : '',
                    isset($_SESSION['USER_LOGGED']) ? $_SESSION['USER_LOGGED'] : ''
                );
                $smarty->assign('user', $name);
            }

            if (!empty(config("system.workspace"))) {
                $logout = '/sys' . config("system.workspace") . '/' . SYS_LANG . '/' . SYS_SKIN . '/login/login';
            } else {
                $logout = '/sys/' . SYS_LANG . '/' . SYS_SKIN . '/login/login';
            }

            $smarty->assign('linklogout', $logout);
            $smarty->assign('header', $header);
            $smarty->assign('footer', $footer);
            $smarty->assign('tpl_menu', PATH_TEMPLATE . 'menu.html');
            $smarty->assign('tpl_submenu', PATH_TEMPLATE . 'submenu.html');

            if (class_exists('ProcessMaker\Plugins\PluginRegistry')) {
                $oPluginRegistry = PluginRegistry::loadSingleton();
                $sCompanyLogo = $oPluginRegistry->getCompanyLogo('/images/processmaker.logo.jpg');
            } else {
                $sCompanyLogo = '/images/processmaker.logo.jpg';
            }

            $smarty->assign('logo_company', $sCompanyLogo);
            $smarty->display($this->layoutFileSubmenu['basename']);
        }
    }

    private function _tracker()
    {
        global $G_ENABLE_BLANK_SKIN;

        G::verifyPath(PATH_SMARTY_C, true);
        G::verifyPath(PATH_SMARTY_CACHE, true);

        $smarty = new Smarty();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty->template_dir = PATH_SKINS;
        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';

        if (isset($G_ENABLE_BLANK_SKIN) && $G_ENABLE_BLANK_SKIN) {
            $smarty->force_compile = $this->forceTemplateCompile;
            $smarty->display($this->layoutFileBlank['basename']);
        } else {
            $header = '';

            if (isset($oHeadPublisher)) {
                $oHeadPublisher->title = isset($_SESSION['USR_USERNAME']) ? '(' . $_SESSION['USR_USERNAME'] . ' ' . G::LoadTranslation('ID_IN') . ' ' . config("system.workspace") . ')' : '';
                $header = $oHeadPublisher->printHeader();
            }

            $footer = '';

            if (strpos($_SERVER['REQUEST_URI'], '/login/login') !== false) {
                $fileFooter = PATH_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                if (file_exists($fileFooter)) {
                    $footer .= file_get_contents($fileFooter);
                } else {
                    $fileFooter = PATH_SKIN_ENGINE . SYS_SKIN . PATH_SEP . 'footer.html';
                    if (file_exists($fileFooter)) {
                        $footer .= file_get_contents($fileFooter);
                    } else {
                        $fileFooter = PATH_CUSTOM_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                        if (file_exists($fileFooter)) {
                            $footer .= file_get_contents($fileFooter);
                        } else {
                            $footer .= "$freeOfChargeText <br />Copyright &copy; 2000-" . date('Y') . " <a href=\"http://www.processmaker.com\" alt=\"ProcessMaker Inc.\" target=\"_blank\">ProcessMaker </a>Inc. All rights reserved.<br />  " . "<br><br/><a href=\"http://www.processmaker.com\" alt=\"Powered by ProcessMaker - Open Source Workflow & Business Process Management (BPM) Management Software\" title=\"Powered by ProcessMaker\" target=\"_blank\"></a>";
                        }
                    }
                }
            }

            //menu
            global $G_MAIN_MENU;
            global $G_SUB_MENU;
            global $G_MENU_SELECTED;
            global $G_SUB_MENU_SELECTED;
            global $G_ID_MENU_SELECTED;
            global $G_ID_SUB_MENU_SELECTED;

            $oMenu = new Menu();
            $menus = $oMenu->generateArrayForTemplate(
                $G_MAIN_MENU,
                'SelectedMenu',
                'mainMenu',
                $G_MENU_SELECTED,
                $G_ID_MENU_SELECTED
            );
            $smarty->assign('menus', $menus);

            $oSubMenu = new Menu();
            $subMenus = $oSubMenu->generateArrayForTemplate(
                $G_SUB_MENU,
                'selectedSubMenu',
                'subMenu',
                $G_SUB_MENU_SELECTED,
                $G_ID_SUB_MENU_SELECTED
            );
            $smarty->assign('subMenus', $subMenus);

            $smarty->assign('user', isset($_SESSION['USR_USERNAME']) ? $_SESSION['USR_USERNAME'] : '');
            $smarty->assign('pipe', isset($_SESSION['USR_USERNAME']) ? ' | ' : '');
            $smarty->assign('logout', G::LoadTranslation('ID_LOGOUT'));
            $smarty->assign('header', $header);
            $smarty->assign('tpl_menu', PATH_TEMPLATE . 'menu.html');
            $smarty->assign('tpl_submenu', PATH_TEMPLATE . 'submenu.html');

            if (class_exists('ProcessMaker\Plugins\PluginRegistry')) {
                $oPluginRegistry = PluginRegistry::loadSingleton();
                $sCompanyLogo = $oPluginRegistry->getCompanyLogo('/images/processmaker.logo.jpg');
            } else {
                $sCompanyLogo = '/images/processmaker.logo.jpg';
            }

            $smarty->assign('logo_company', $sCompanyLogo);
            $smarty->force_compile = $this->forceTemplateCompile;
            $smarty->display($this->layoutFileTracker['basename']);
        }
    }

    private function _mvc()
    {
        $oServerConf = ServerConf::getSingleton();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty = new Smarty();

        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';
        $smarty->register_function('translate', 'translate');
        $smarty->register_function('csrf_token', 'csrfToken');

        $viewVars = $oHeadPublisher->getVars();

        // verify if is using extJs engine
        if (count($oHeadPublisher->extJsScript) > 0) {
            $header = $oHeadPublisher->getExtJsStylesheets($this->cssFileName . '-extJs');
            $header .= $oHeadPublisher->includeExtJs();

            $smarty->assign('_header', $header);
        }

        $contentFiles = $oHeadPublisher->getContent();
        $viewFile = isset($contentFiles[0]) ? $contentFiles[0] : '';

        if (empty($this->layout)) {
            $smarty->template_dir = PATH_TPL;
            $tpl = $viewFile . '.html';
        } else {
            $smarty->template_dir = $this->layoutFile['dirname'];
            $tpl = 'layout-' . $this->layout . '.html';
            //die($smarty->template_dir.PATH_SEP.$tpl);

            if (!file_exists($smarty->template_dir . PATH_SEP . $tpl)) {
                $e = new Exception("Layout $tpl does not exist!", SE_LAYOUT_NOT_FOUND);
                $e->layoutFile = $smarty->template_dir . PATH_SEP . $tpl;

                throw $e;
            }
            $smarty->assign('_content_file', $viewFile);
        }

        if (strpos($viewFile, '.') === false) {
            $viewFile .= '.html';
        }

        foreach ($viewVars as $key => $value) {
            $smarty->assign($key, $value);
        }

        if (defined('DEBUG') && DEBUG) {
            $smarty->force_compile = true;
        }

        $smarty->assign('_skin', $this->mainSkin);

        $smarty->display($tpl);
    }

    /**
     * this Method prints the same _default() environment except javascript
     */
    private function _minimal()
    {
        $enableJavascript = false;

        $this->_default($enableJavascript);
    }

    private function _default($enableJsScript = true)
    {
        global $G_ENABLE_BLANK_SKIN;
        //menu
        global $G_PUBLISH;
        global $G_MAIN_MENU;
        global $G_SUB_MENU;
        global $G_MENU_SELECTED;
        global $G_SUB_MENU_SELECTED;
        global $G_ID_MENU_SELECTED;
        global $G_ID_SUB_MENU_SELECTED;
        global $RBAC;

        G::verifyPath(PATH_SMARTY_C, true);
        G::verifyPath(PATH_SMARTY_CACHE, true);

        $smarty = new Smarty();
        $oHeadPublisher = headPublisher::getSingleton();

        $smarty->compile_dir = PATH_SMARTY_C;
        $smarty->cache_dir = PATH_SMARTY_CACHE;
        $smarty->config_dir = PATH_THIRDPARTY . 'smarty/configs';

        // Initializing template variables
        $smarty->assign('userfullname', null);

        //To setup en extJS Theme for this Skin

        $oServerConf = ServerConf::getSingleton();
        $extSkin = $oServerConf->getProperty("extSkin");

        if (!$extSkin) {
            $extSkin = [SYS_SKIN => "xtheme-gray"];
            $oServerConf->setProperty("extSkin", $extSkin);
        } elseif (empty($extSkin[SYS_SKIN])) {
            $extSkin[SYS_SKIN] = "xtheme-gray";
            $oServerConf->setProperty("extSkin", $extSkin);
        }

        //End of extJS Theme setup

        if (isset($G_ENABLE_BLANK_SKIN) && $G_ENABLE_BLANK_SKIN) {
            $smarty->template_dir = $this->layoutFileBlank['dirname'];
            $smarty->force_compile = $this->forceTemplateCompile;

            $smarty->display($layoutFileBlank['basename']);
        } else {
            $smarty->template_dir = $this->layoutFile['dirname'];

            $meta = null;
            $header = null;

            if (preg_match("/^.*\(.*Trident.(\d+)\..+\).*$/", $_SERVER["HTTP_USER_AGENT"], $arrayMatch)) {

                //Get the IE version
                if (preg_match(
                        "/^.*\(.*MSIE (\d+)\..+\).*$/",
                        $_SERVER["HTTP_USER_AGENT"],
                        $arrayMatch
                    ) || preg_match(
                        "/^.*\(.*rv.(\d+)\..+\).*$/",
                        $_SERVER["HTTP_USER_AGENT"],
                        $arrayMatch
                    )
                ) {
                    $ie = intval($arrayMatch[1]);
                }

                if ($ie == 10 || $ie == 11) {
                    $ie = 8;

                    $meta = "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=$ie\" />";
                }
            }

            if (isset($oHeadPublisher)) {
                if (!empty(config("system.workspace"))) {
                    $oHeadPublisher->title = isset($_SESSION['USR_USERNAME']) ? '(' . $_SESSION['USR_USERNAME'] . ' ' . G::LoadTranslation('ID_IN') . ' ' . config("system.workspace") . ')' : '';
                }
                $header = $enableJsScript ? $oHeadPublisher->printHeader() : '';
                $header .= $oHeadPublisher->getExtJsStylesheets($this->cssFileName);
            }

            if (defined('LOAD_HEADERS_IE') && LOAD_HEADERS_IE == 0) {
                $smarty->assign('meta', $meta);
            }

            $smarty->assign("header", $header);

            $footer = '';

            if (strpos($_SERVER['REQUEST_URI'], '/login/login') !== false) {
                $freeOfChargeText = "";
                if (!defined('SKIP_FREE_OF_CHARGE_TEXT')) {
                    $freeOfChargeText = "Supplied free of charge with no support, certification, warranty, maintenance nor indemnity by ProcessMaker and its Certified Partners.";
                }
                if (file_exists(PATH_CLASSES . "PmLicenseManager.php")) {
                    $freeOfChargeText = "";
                }

                $fileFooter = PATH_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                if (file_exists($fileFooter)) {
                    $footer .= file_get_contents($fileFooter);
                } else {
                    $fileFooter = PATH_SKIN_ENGINE . SYS_SKIN . PATH_SEP . 'footer.html';
                    if (file_exists($fileFooter)) {
                        $footer .= file_get_contents($fileFooter);
                    } else {
                        $fileFooter = PATH_CUSTOM_SKINS . SYS_SKIN . PATH_SEP . 'footer.html';
                        if (file_exists($fileFooter)) {
                            $footer .= file_get_contents($fileFooter);
                        } else {
                            $footer .= "$freeOfChargeText <br />Copyright &copy; 2000-" . date('Y') . " <a href=\"http://www.processmaker.com\" alt=\"ProcessMaker Inc.\" target=\"_blank\">ProcessMaker </a>Inc. All rights reserved.<br />" . "<br><br/><a href=\"http://www.processmaker.com\" alt=\"Powered by ProcessMaker - Open Source Workflow & Business Process Management (BPM) Management Software\" title=\"Powered by ProcessMaker\" target=\"_blank\"></a>";
                        }
                    }
                }
            }

            $oMenu = new Menu();
            $menus = $oMenu->generateArrayForTemplate(
                $G_MAIN_MENU,
                'SelectedMenu',
                'mainMenu',
                $G_MENU_SELECTED,
                $G_ID_MENU_SELECTED
            );
            $smarty->assign('menus', $menus);

            $oSubMenu = new Menu();
            $subMenus = $oSubMenu->generateArrayForTemplate(
                $G_SUB_MENU,
                'selectedSubMenu',
                'subMenu',
                $G_SUB_MENU_SELECTED,
                $G_ID_SUB_MENU_SELECTED
            );
            $smarty->assign('subMenus', $subMenus);

            if (!defined('NO_DISPLAY_USERNAME')) {
                define('NO_DISPLAY_USERNAME', 0);
            }
            if (NO_DISPLAY_USERNAME == 0) {
                $switch_interface = isset($_SESSION['user_experience']) && $_SESSION['user_experience'] == 'SWITCHABLE';

                $smarty->assign('user_logged', (isset($_SESSION['USER_LOGGED']) ? $_SESSION['USER_LOGGED'] : ''));
                if (SYS_SKIN == 'neoclassic') {
                    $smarty->assign(
                        'tracker',
                        (SYS_COLLECTION == 'tracker') ? (($G_PUBLISH->Parts[0]['File'] != 'tracker/loginpm3') ? true : '') : ''
                    );
                } else {
                    $smarty->assign(
                        'tracker',
                        (SYS_COLLECTION == 'tracker') ? (($G_PUBLISH->Parts[0]['File'] != 'tracker/login') ? true : '') : ''
                    );
                }
                $smarty->assign('timezone_status', (isset($_SESSION['__TIME_ZONE_FAILED__']) && $_SESSION['__TIME_ZONE_FAILED__']) ? 'failed' : 'ok');
                $smarty->assign('switch_interface', $switch_interface);
                $smarty->assign('switch_interface_label', G::LoadTranslation('ID_SWITCH_INTERFACE'));
                $smarty->assign('rolename', isset($_SESSION['USR_ROLENAME']) ? $_SESSION['USR_ROLENAME'] . '' : '');
                $smarty->assign('pipe', isset($_SESSION['USR_USERNAME']) ? ' | ' : '');
                $smarty->assign('logout', G::LoadTranslation('ID_LOGOUT'));
                $smarty->assign('workspace', !empty(config("system.workspace")) ? config("system.workspace") : '');
                $uws = (isset($_SESSION['USR_ROLENAME']) && $_SESSION['USR_ROLENAME'] != '') ? strtolower(G::LoadTranslation('ID_WORKSPACE_USING')) : G::LoadTranslation('ID_WORKSPACE_USING');
                $smarty->assign('workspace_label', $uws);
                $smarty->assign('msgVer', null);

                $conf = new Configurations();
                $conf->getFormats();
                $name = $conf->userNameFormat(
                    isset($_SESSION['USR_USERNAME']) ? $_SESSION['USR_USERNAME'] : '',
                    isset($_SESSION['USR_FULLNAME']) ? htmlentities(
                        $_SESSION['USR_FULLNAME'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) : '',
                    isset($_SESSION['USER_LOGGED']) ? $_SESSION['USER_LOGGED'] : ''
                );
                $smarty->assign('user', $name);
            }

            if (!empty(config("system.workspace"))) {
                $pmLicenseManagerO = PmLicenseManager::getSingleton();
                $expireIn = $pmLicenseManagerO->getExpireIn();
                $expireInLabel = $pmLicenseManagerO->getExpireInLabel();

                if (!is_null($RBAC) &&
                    isset($RBAC->aUserInfo['PROCESSMAKER']['ROLE']['ROL_CODE']) &&
                    $RBAC->aUserInfo['PROCESSMAKER']['ROLE']['ROL_CODE'] == 'PROCESSMAKER_ADMIN'
                ) {
                    if ($expireInLabel != '') {
                        $smarty->assign(
                            'msgVer',
                            '<label class="textBlack">' . $expireInLabel . '</label>&nbsp;&nbsp;'
                        );
                    }
                }
            }

            if (!empty(config("system.workspace"))) {
                $logout = "/sys" . config("system.workspace") . "/" . SYS_LANG . "/" . SYS_SKIN . ((SYS_COLLECTION != "tracker") ? "/login/login" : "/tracker/login");
            } else {
                $logout = '/sys/' . SYS_LANG . '/' . SYS_SKIN . '/login/login';
            }

            $smarty->assign('linklogout', $logout);
            $smarty->assign('footer', $footer);
            $smarty->assign('tpl_menu', PATH_TEMPLATE . 'menu.html');
            $smarty->assign('tpl_submenu', PATH_TEMPLATE . 'submenu.html');

            $oLogoR = new ReplacementLogo();

            if (!empty(config("system.workspace"))) {
                $aFotoSelect = $oLogoR->getNameLogo((isset($_SESSION['USER_LOGGED'])) ? $_SESSION['USER_LOGGED'] : '');

                if (is_array($aFotoSelect)) {
                    $sFotoSelect = trim($aFotoSelect['DEFAULT_LOGO_NAME']);
                    $sWspaceSelect = trim($aFotoSelect['WORKSPACE_LOGO_NAME']);
                }
            }
            if (class_exists('ProcessMaker\Plugins\PluginRegistry') && !empty(config("system.workspace"))) {
                $oPluginRegistry = PluginRegistry::loadSingleton();
                if (isset($sFotoSelect) && $sFotoSelect != '' && !(strcmp($sWspaceSelect,
                        config("system.workspace")))) {
                    $sCompanyLogo = $oPluginRegistry->getCompanyLogo($sFotoSelect);
                    $sCompanyLogo = "/sys" . config("system.workspace") . "/" . SYS_LANG . "/" . SYS_SKIN . "/setup/showLogoFile.php?id=" . base64_encode($sCompanyLogo);
                } else {
                    $sCompanyLogo = $oPluginRegistry->getCompanyLogo('/images/processmaker.logo.jpg');
                }
            } else {
                $sCompanyLogo = '/images/processmaker.logo.jpg';
            }

            $smarty->assign('logo_company', $sCompanyLogo);
            $smarty->force_compile = $this->forceTemplateCompile;

            $smarty->display($this->layoutFile['basename']);
        }
    }
}
