<?php

const EOL                = PHP_EOL;
const CMSIF_VER          = '0.07b';
const CMSIF_TPL          = 'default';
const CMSIF_ENCODING     = 'UTF-8';

if(!defined('CMSIF_SESSION_ID')) define('CMSIF_SESSION_ID', 'CMSIF');

if(!defined('CMSIF_WEBROOT')) define('CMSIF_WEBROOT', __DIR__);
if(!defined('CMSIF_COOKIE_LTIME')) define('CMSIF_COOKIE_LTIME', 86400);
if(!defined('CMSIF_TIMEZONE')) define('CMSIF_TIMEZONE', 'Europe/Moscow');
if(!defined('CMSIF_DEFAULT_LANG')) define('CMSIF_DEFAULT_LANG', 'en');

if(!defined('CMSIF_FILES')) define('CMSIF_FILES', __DIR__.'/__FILES__/');
if(!defined('CMSIF_MODULES')) define('CMSIF_MODULES', __DIR__.'/__MODULES__/');
if(!defined('CMSIF_TEMPLATES')) define('CMSIF_TEMPLATES', __DIR__.'/__TEMPLATES__/');

//DB
if(!defined('CMSIF_DB_HOST')) define('CMSIF_DB_HOST', 'localhost');
if(!defined('CMSIF_DB_USER')) define('CMSIF_DB_USER', 'root');
if(!defined('CMSIF_DB_PASS')) define('CMSIF_DB_PASS', 'toor');
if(!defined('CMSIF_DB_NAME')) define('CMSIF_DB_NAME', 'app');

const CMSIF_CSRF_POST_KEY      = 'csrf-token';
const CMSIF_CSRF_HEADER_KEY    = 'X-CSRF-TOKEN';
const CMSIF_CSRF_TOKEN_INVALID = 'Invalid CSRF-token.';

const CMSIF_DEFAULT_AUTH_TYPE  = 'file';

if(!defined('CMSIF_ASSETS'))
{
    define('CMSIF_ASSETS', '/');
}

$data = []; //global data container

function init()
{
    if(!defined('CMSIF_SYSTEM_NAME'))
    {
        define('CMSIF_SYSTEM_NAME' , 'CMSIF ver. '. version());
    }

    dataSet('route', 'error404');

    dataSet('auth_types', ['file', 'http', 'db']);

    sessionStart();

    /**
     * HTML Language Code Reference
     * https://www.w3schools.com/tags/ref_language_codes.asp
     */
    dataSet('languages', [
        'ru'=>[
            'ru','be','uk','bg',
            'ky','kk','kv','ce',
            'ab','az','mo','hy',
            'et','lv','lt','cv',
            'ka','tg','tt','tk',
            'uz'],
        'de'=>'de',
        'tr'=>'tr',
        'it'=>'it',
        'es'=>[
            'es', 'ca', 'co'
        ],
    ]);

    languageSet();
    
    // Set timezone to default, falls back to system if php.ini not set
    date_default_timezone_set(CMSIF_TIMEZONE); //@date_default_timezone_get()
    
    // Set internal encoding if mbstring loaded
    if (!extension_loaded('mbstring')) {
        die("'mbstring' extension is not loaded.");
    }
    
    mb_internal_encoding(CMSIF_ENCODING); 
    mb_http_output(CMSIF_ENCODING); 
    mb_http_input(CMSIF_ENCODING); 
    mb_regex_encoding(CMSIF_ENCODING);
    
    cookieSet('language', languageGet());
    
    //dbConnect();
}

/**
 * Get User Preferred Language or default
 *
 * @return string Useragent Language guess
 */
function languageGet()
{
    return dataGet('language', CMSIF_DEFAULT_LANG);
}

/**
 * Set Language
 *
 * @param string $_default_language
 * @return void
 */
function languageSet($_default_language=CMSIF_DEFAULT_LANG)
{
    $_lang = $_default_language;
    $_languages_client = _language_client();
    $_languages = _language_mapping($_lang);

    foreach($_languages_client as $l => $v)
    {
        $s = strtok($l, '-');
        if(isset($_languages[$s]))
        {
            $_lang = $_languages[$s];
        }
    }

    dataSet('language', $_lang);
}

/**
 * Language mapping - supporting function
 *
 * @param string $_default_language
 * @return array $_languages
 */
function _language_mapping($_default_language)
{
    $_languages = [];
    $_langs_mapping = dataGet('languages', [$_default_language=>$_default_language]);

    foreach($_langs_mapping as $_lang => $_alias)
    {
        $_lang = stringLow($_lang);
        if(is_array($_alias))
        {
            foreach($_alias as $_alias_lang)
            {
                $_alias_lang = stringLow($_alias_lang);
                $_languages[$_alias_lang] = $_lang;
            }
        }
        else
        {
            $_alias = stringLow($_alias);
            $_languages[$_alias] = $_lang;
        }
    }
    return $_languages;
}

/**
 * Useragent Preferred Languages - supporting function
 *
 * @return array $_languages
 */
function _language_client()
{
    $_languages = [];
    
    if($list = stringLow( getHeader('http-accept-language') ))
    {
        
        if(preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', $list, $list))
        {
            $_languages = array_combine($list[1], $list[2]);

            foreach ($_languages as $n => $v)
            {
                $_languages[$n] = $v ? $v : 1;
            }

            arsort($_languages, SORT_NUMERIC);
        }
    }

    return $_languages;
}

/**
 * Strip Slashes If Magic Quotes ON
 *
 * @return array|string $_opt
 */
function filterSlashes($_opt=[])
{
    if(ini_get('magic_quotes_gpc'))
    {
    	$_opt = _filter_strip_slashes($_opt);
    }
    return $_opt;
}

/**
 * Multidimensional Strip Slashes - supporting function
 *
 * @return array|string $_opt
 */
function _filter_strip_slashes($_opt)
{
    if(is_array($_opt) && count($_opt))
    {
        foreach($_opt as $_k => $_v)
        {
            $_opt[_filter_strip_slashes($_k)] = _filter_strip_slashes($_v);
        }
    }
    else
    {
        $_opt = stripslashes($_opt);
    }
    return $_opt;
}

function filterGET()
{
    global $_GET;
    return filter(filterSlashes($_GET));
}

function filterPOST()
{
    global $_POST;
    return filter(filterSlashes($_POST));
}

function filterCOOKIE()
{
    global $_COOKIE;
    return filter(filterSlashes($_COOKIE));
}

function filterSERVER()
{
    global $_SERVER;
    $_server = [];

    foreach ($_SERVER as $_key => $_value) {
        $_server[stringLow($_key)] = $_value;
        $_server[stringLow(str_replace('_', '-', $_key))] = $_value;
    }
    return $_server;
}

function filter($_var=[], $_filter = " \t\n\r\0\x0B", $_default = '')
{
    if(is_array($_var) && count($_var))
    {
        foreach($_var as $_key=>$_value)
        {
            if(is_string($_value))
            {
                // Translate HTML entities
                $_value = strtr($_value, 
                    array_flip(
                        get_html_translation_table(
                            HTML_ENTITIES, ENT_QUOTES
                        )
                    )
                );
                
                // Remove multi whitespace
                $_value = preg_replace("@\s+\s@Ui"," ",$_value);
                $_value = trim($_value, $_filter);
                if(empty($_value))
                {
                    unset($_var[$_key]);
                } else { 
                    $_var[$_key]=$_value;
                }
            }
        }
    }
    else
    {
        if(!empty($_var))
        {
            $_var = filter([$_var=>$_var], $_filter);
            return array_pop( $_var );
        }
    }
    return $_var;
}

function cookieGet($_name='')
{
    global $_COOKIE;
    $_value = null;
    if(isset($_COOKIE[$_name]))
    {
        $_value = unserialize(base64_decode($_COOKIE[$_name]));
    }
    return $_value;
}

/*
setcookie ( string $name [, string $value = "" [, int $expire = 0 [, string $path = "" [, string $domain = "" [, bool $secure = FALSE [, bool $httponly = FALSE ]]]]]] )
*/
function cookieSet($_key='', $_value='', $_days = 1)
{
    if(!empty($_key) && !empty($_value))
    {
        $_value = base64_encode(serialize($_value));
        $_expire = time()+ (60*60*24*( (int) $_days ));
        setcookie($_key, $_value, $_expire);
    }
}

function cookieDelete($_name='')
{
    global $_COOKIE;
    if (session_id()) 
    {
        if(isset($_COOKIE[$_name]))
        {
            setcookie($_name, "", time()-CMSIF_COOKIE_LTIME);
        }
    }
}

function sessionStart()
{
    global $_SESSION; 
    if (!isset($_SESSION['safety']))
    {
        //session_regenerate_id(true);
        session_id(CMSIF_SESSION_ID);
        session_start([
            'cookie_lifetime' => CMSIF_COOKIE_LTIME
        ]);
        $_SESSION['safety'] = true;
    }
    $_SESSION['sessionid'] = session_id();
}

function sessionGet($_key='')
{
    global $_SESSION;
    if(isset($_SESSION[$_key]))
    {
        return $_SESSION[$_key];
    }
    return null;
}

function sessionSet($_key='', $_value='')
{
    global $_SESSION;
    $_SESSION[$_key] = $_value;
}

function sessionDelete($key='')
{
    global $_SESSION;
    if (!empty($key)) 
    {
        if(isset($_SESSION[$key]))
        {
            unset($_SESSION[$key]);
            return true;
        }
    }
    return false;
}

function sessionUnset(){
    if(!empty(session_id()))
    {
        session_unset();
    }
}

function fileRead($_file = '', $_format = 'plain') //plain, html, json
{
    $_file_path = filePath($_file);

    if(!empty($_file_path))
    {
        if( !file_exists($_file_path) || !is_readable($_file_path) )
        {
            return null;
        }

        $_content = file_get_contents( $_file_path );

        switch($_format)
        {
            case 'html':
                $_content = htmlentities( $_content );
                break;
            case 'json':
                $_content = json_decode( $_content , true);
                break;
        }
        return $_content;
    }
    return false;
}

function fileWrite($_file = '', $_opt = '', $_format = 'plain')
{
    $_file_path = filePath($_file);

    if(!empty($_file_path))
    {
        $e = fopen($_file_path, 'w');
        if($e)
        {
            switch($_format)
            {
                case 'json':
                    $_opt = json_encode($_opt);
                    break;
            }
            
            fwrite($e, $_opt );
            return true;
        }
    }
    return false;
}

function filePath($_file)
{
    $_file_path = '';
    $_file = filter($_file, ' ./');
    if(!empty($_file))
    {
        $_file_path = CMSIF_FILES. $_file;
    }
    return $_file_path;
}

function router($_method=null, $_match_path='/', $_call)
{
    if($_method === getMethod())
    {
        if($_match_path === getUrl())
        {
            if(is_callable($_call))
            {
                is_string($_call) ? $_call() : call_user_func($_call);
            } else {
                require $_call;
            }
            dataSet('route', $_match_path);
            exit;
        }

        $_matches = [];
        preg_match( '@^'.$_match_path.'@', getUrl(), $_matches);
        if(!empty($_matches[1]) || $_match_path === getUrl())
        {
            if(is_callable($_call))
            {
                is_string($_call) ? $_call($_matches) : call_user_func($_call, $_matches);
            } else {
                require $_call;
            }
            dataSet('route', $_match_path);
            exit;
        }
    }
}

function version()
{
    return CMSIF_VER; 
}

function dump($option)
{
    echo '<pre>';
    echo '<hr />';

    var_dump($option);

    echo '<hr />';
    echo '</pre>';
}

function form($options=['method'=>'GET', 'action'=>'', 'fields'=>[]])
{
    $s_out = '<form action="'.(!empty($options['action'])? $options['action']: getUrl()).'" method="'.($options['method']).'">';

    if(count($options['fields']))
    {
        foreach($options['fields'] as $_k=>$_v)
        {
            if(in_array($_k, ['text', 'password', 'checkbox', 'select', 'submit']))
            {
                $_vars = json_decode($_v, true);
                if(!isset($_vars['name']) || empty($_vars['name']))
                {
                    $_vars['name'] = uniqid('name_');
                }

                if(!isset($_vars['id']) || empty($_vars['id']))
                {
                    $_vars['id'] = uniqid('id_');
                }

                $s_out .= '<input type="'.$_k.'" name="'.$_vars['name'].'" id="'.$_vars['id'].'" placeholder="'.$_vars['id'].'" value="'.('submit'==$_k? $_vars['id']: '').'">';
            }
            else
            {
                $s_out .= '<p class="alert">Unknown element: '.$_k.'</p>'.EOL;
            }
        }
    }

    $s_out .= '</form>';

    renderBlock($s_out);
}

function dbh()
{
    $dbh = dataGet('dbh');
    if(null == $dbh) 
    { 
        $dbh = dbConnect();
        dataSet('dbh', $dbh);
    }
    return $dbh;    
}

function dbQuery($_query, $_opt=[])
{
	if(empty($_query)){ return null; }

	$_out = [];
    
    if(is_array($_query) && count($_query))
    {
        $_out = dbMultiQuery($_query, $_opt);
    }
    else
    {
	    $_result = mysqli_query(dbh(), $_query);
	    while ($_row = mysqli_fetch_assoc($_result))
	    {
		    $_out[] = $_row;
		}
		mysqli_free_result($_result);
	}
	
	return $_out;
}

function dbMultiQuery($_query, $_opt=[])
{
	if(empty($_query)){ return null; }

	$_out = [];

    $query = implode(';', $_query);

    if(mysqli_multi_query(dbh(), $query))
    {
        do
        {
            /* store first result set */
            if($_result = mysqli_store_result(dbh())) {
                while($_row = mysqli_fetch_assoc($_result))
                {
                    $_out[] = $_row;
                }
                mysqli_free_result($_result);
            }
        }
        while (@mysqli_next_result(dbh()));
    }
    
    return $_out;
}

function dbConnect()
{
    $_dbh = new mysqli(CMSIF_DB_HOST, CMSIF_DB_USER, CMSIF_DB_PASS, CMSIF_DB_NAME);
    if (mysqli_connect_errno()) {
        dump("Connect failed: ". mysqli_connect_error(). EOL);
        exit();
    }
    return $_dbh;
}

function dbDisconnect()
{
    return mysqli_close(dbh());
}


function csrf_token()
{
    
}

function getHeader($_key='', $_default = null)
{
    $_server = filterSERVER();
    return (isset($_server[$_key]) && !empty($_server[$_key])? $_server[$_key]: $_default);
}

function getHeaders(){ return filterSERVER(); }

function getId()
{
    return dataGet('id');
}

function getModule()
{
    return dataGet('module');
}

function getMethod()
{
    $_method = getHeader('request-method');
    return stringLow( $_method );
}

function getUrl()
{
    $_url = explode('?', getHeader('request-uri'));
    return $_url[0];
}

function getHost()
{
    $_host = getHeader('http-host');
    return stringLow( '//'.$_host );
}

function getUser()
{
    return getHeader('php-auth-user');
}

function getPassword()
{
    return getHeader('php-auth-pw');
}

function getIp()
{
    $_ip = getHeader('http-cf-connecting-ip');
    if ($_ip !== null) {
        return $_ip;
    }
    
    $_ip = getHeader('http-x-forwarded-for');
    if ($_ip !== null) {
        return $_ip;
    }
    return getHeader('remote-addr');
}

function getUserAgent()
{
    return getHeader('http-user-agent');
}

function isAjax()
{
    return (stringLow(getHeader('http-x-requested-with')) === 'xmlhttprequest');
}

function dataAll()
{
    global $data;
    return $data;
}

function dataIsset($_name)
{
    global $data;
    return array_key_exists($_name, $data) === true;
}

function dataSet($_name, $_value = null)
{
    global $data;
    $data[$_name] = $_value;
}

function dataGet($_name, $_default = null)
{
    global $data;
    return isset($data[$_name]) ? $data[$_name]: $_default;
}

function dataUnset($_name, $_value = null)
{
    global $data;
    if(dataIsset($_name))
    {
        unset($data[$_name]);
    }
}

function headers($headers = [])
{
    foreach ($headers as $header) {
        header($header);
    }
}


function auth($_type=CMSIF_DEFAULT_AUTH_TYPE)
{
    if(in_array($_type, dataGet('auth_types')))
    {
        switch($_type)
        {
            case 'file':
                
                break;
            case 'db':
                
                break;
            case 'http':
                authHTTP(CMSIF_SYSTEM_NAME);
                break;
        }
    }
    else
    {
        renderHTML('error', 'Wrong Auth Type Selected!');
    }
}

function authHTTP($_name='')
{
    headers([
        'WWW-Authenticate: Basic realm="' . $_name . '"',
        'HTTP/1.0 401 Unauthorized',
    ]);
    exit(0);
}

function headerHTML()
{
    headers(['Content-Type: text/html; charset='.CMSIF_ENCODING]);
    echo view(['<!DOCTYPE html>', '<html>', '<head><meta charset="'.CMSIF_ENCODING.'"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>']);
}

function view($_content='')
{
    $_out = '';
    
    if(is_array($_content))
    {
        foreach($_content as $_content_part)
        {
            $_out .= view($_content_part);
        }
    }
    else
    {
        if(!empty($_content)) 
        {
            $_out .= $_content. EOL;    
        }
    }
    return $_out;
}

function renderHTML($_template = 'main', $_content = '')
{
    $_render_template = renderTemplate($_template);

    if(is_array($_content))
    {
        $_content = view($_content);
    }

    $_assets          = '';
    $_assets_external = dataGet('assets_external', []);
    $_assets_local    = dataGet('assets_local', []);
    $_blocks          = dataGet('blocks', []);
    $_assets_out      = [];

    if(count($_assets_external))
    {
        $_assets_out[] = implode(EOL, $_assets_external);
    }

    if(count($_assets_local))
    {
        $_assets_out[] = implode(EOL, $_assets_local);
    }
    
    $_assets = implode(EOL, $_assets_out);

    if(count($_blocks))
    {
        foreach($_blocks as $_id=>$_block)
        {
            $_content .= sprintf('<div id="%s">%s</div>', $_id, $_block);
        }
    }
    //$_content = implode(EOL, $_blocks);

    headerHTML();
    @include( $_render_template );
}

function renderTemplate($_template = '')
{
    $_file_path = CMSIF_TEMPLATES.'/'.$_template.'.php';
    if (file_exists($_file_path) && is_readable($_file_path))
    {
        return $_file_path;
    }
    else
    {
        error404();
    }
}

function renderBlock($_block='', $_name='')
{
    $_blocks = dataGet('blocks', []);
    if(empty($_name))
    {
        $_name = 'block_'. (count($_blocks) + 1);
    }
    $_blocks[ $_name ] = $_block;
    dataSet('blocks', $_blocks);
    return true;
}

function error404()
{
    headers([
        'HTTP/1.0 404 Not Found',
    ]);
    exit(0);
}

function asset($_asset = '', $_opt=[])
{
    if(!empty($_asset))
    {
        $_assets = dataGet('assets_local', []);
        if(isset($_assets[ md5($_asset) ])) return;

        $_type        = isset($_opt['type'])? $_opt['type']: null;
        $_media       = isset($_opt['media'])? $_opt['media']: null;
        $_integrity   = isset($_opt['integrity'])? $_opt['integrity']: null;
        $_crossorigin = isset($_opt['crossorigin'])? $_opt['crossorigin']: null;
        $_version     = isset($_opt['version'])? $_opt['version']: null;

        $_out = '';
        $_file = filter($_asset, ' .\/');
        $_ext = stringLow( pathinfo($_file, PATHINFO_EXTENSION) );
        $_file_path = CMSIF_WEBROOT .'/'. $_ext .'/'.  $_file;
        $_file_url  = getHost() . CMSIF_ASSETS . $_ext.'/'. $_file .(!is_null($_version)? '?'.$_version:'');

        if(file_exists($_file_path) && is_readable($_file_path))
        {
            $_options  = $_media? ' media="'.$_media.'"':'';
            $_options .= $_integrity? ' integrity="'.$_integrity.'"':'';
            $_options .= $_crossorigin? ' crossorigin="'.$_crossorigin.'"':'';

            switch($_ext)
            {
                case 'css':
                    $_out = '<link rel="stylesheet" type="text/css" href="'. $_file_url .'"'. $_options .'>';
                    break;
                case 'js':
                    $_out = '<script language="javascript" src="'. $_file_url .'"'.$_options.'></script>';
                    break;
                default:
                    break;
            }
        }
        
        if(!empty($_out))
        {    
            $_assets[md5($_asset)] = $_out;
            dataSet('assets_local', $_assets);
        }
    }

    return false;
}

function assetExternal($_asset = '', $_opt=[])
{
    if(!empty($_asset))
    {
        $_assets = dataGet('assets_external', []);
        if(isset($_assets[ md5($_asset) ])) return;
        
        $_type        = isset($_opt['type'])? $_opt['type']: null;
        $_media       = isset($_opt['media'])? $_opt['media']: null;
        $_integrity   = isset($_opt['integrity'])? $_opt['integrity']: null;
        $_crossorigin = isset($_opt['crossorigin'])? $_opt['crossorigin']: null;

        $_ext = stringLow( !is_null($_type)? $_type: pathinfo($_asset, PATHINFO_EXTENSION) );

        $_options  = $_media? ' media="'.$_media.'"':'';
        $_options .= $_integrity? ' integrity="'.$_integrity.'"':'';
        $_options .= $_crossorigin? ' crossorigin="'.$_crossorigin.'"':'';

        switch($_ext)
        {
            case 'css':
                $_out = '<link rel="stylesheet" type="text/css" href="'. $_asset .'"'.$_options.'>';
                break;
            case 'js':
                $_out = '<script language="javascript" src="'. $_asset .'"'.$_options.'></script>';
            default:
                break;
        }
        
        if(!empty($_out))
        {
            $_assets[md5($_asset)] = $_out;
            dataSet('assets_external', $_assets);
        }
    }
}

function stringLow($_opt = '')
{
    return strtolower($_opt);
}

function stringUp($_opt = '')
{
    return strtoupper($_opt);
}

function stringLen($_opt = '')
{
    return strlen($_opt);
}

function stringReplace($_opt = '', $_from = '', $_to = '')
{
    return str_ireplace($_from, $_to, $_opt);
}
