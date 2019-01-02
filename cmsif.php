<?php

const EOL                = PHP_EOL;
const CMSIF_VER          = '0.09b';
const CMSIF_TPL          = 'default';
const CMSIF_ENCODING     = 'UTF-8';

if (!defined('CMSIF_SESSION_ID')) { define('CMSIF_SESSION_ID', 'CMSIF'); }

if (!defined('CMSIF_WEBROOT')) { define('CMSIF_WEBROOT', __DIR__); }
if (!defined('CMSIF_COOKIE_LTIME')) { define('CMSIF_COOKIE_LTIME', 86400); }
if (!defined('CMSIF_TIMEZONE')) { define('CMSIF_TIMEZONE', 'Europe/Moscow'); }
if (!defined('CMSIF_DEFAULT_LANG')) { define('CMSIF_DEFAULT_LANG', 'en'); }

if (!defined('CMSIF_FILES')) { define('CMSIF_FILES', __DIR__.'/__FILES__/'); }
if (!defined('CMSIF_MODULES')) { 
    define('CMSIF_MODULES', __DIR__.'/__MODULES__/'); 
}
if (!defined('CMSIF_TEMPLATES')) { 
    define('CMSIF_TEMPLATES', __DIR__.'/__TEMPLATES__/'); 
}

//DB
if (!defined('CMSIF_DB_HOST')) { define('CMSIF_DB_HOST', 'localhost'); }
if (!defined('CMSIF_DB_USER')) { define('CMSIF_DB_USER', 'root'); }
if (!defined('CMSIF_DB_PASS')) { define('CMSIF_DB_PASS', 'toor'); }
if (!defined('CMSIF_DB_NAME')) { define('CMSIF_DB_NAME', 'app'); }

const CMSIF_CSRF_POST_KEY      = 'csrf-token';
const CMSIF_CSRF_HEADER_KEY    = 'X-CSRF-TOKEN';
const CMSIF_CSRF_TOKEN_INVALID = 'Invalid CSRF-token.';

const CMSIF_AUTH_TYPES  = ['file', 'http', 'db'];
if (!defined('CMSIF_DEFAULT_AUTH_TYPE')) { 
    define('CMSIF_DEFAULT_AUTH_TYPE', 'file');
}

if (!defined('CMSIF_ASSETS')) { define('CMSIF_ASSETS', '/'); }

$data = []; //global data container

function init()
{
    if (!defined('CMSIF_SYSTEM_NAME')) {
        define('CMSIF_SYSTEM_NAME' , 'CMSIF ver. '. version());
    }

    /* Default route */
    dataSet('route', 'error404');

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
 * @param string $default_language
 * @return void
 */
function languageSet($default_language = CMSIF_DEFAULT_LANG)
{
    $language = $default_language;
    $languages_client = language_client();
    $languages = language_mapping($language);

    foreach ($languages_client as $l => $v) {
        $s = strtok($l, '-');
        if (isset($languages[$s])) {
            $language = $languages[$s];
        }
    }

    dataSet('language', $language);
}

/**
 * Language mapping - supporting function
 *
 * @param string $default_language
 * @return array $languages
 */
function language_mapping($default_language)
{
    $languages = [];
    $langs_mapping = dataGet('languages', 
        [$default_language=>$default_language]);

    foreach ($langs_mapping as $lang => $alias) {
        $lang = stringLow($lang);
        if (is_array($alias)) {
            foreach ($alias as $alias_lang) {
                $alias_lang = stringLow($alias_lang);
                $languages[$alias_lang] = $lang;
            }
        } else {
            $alias = stringLow($alias);
            $languages[$alias] = $lang;
        }
    }

    return $languages;
}

/**
 * Useragent Preferred Languages - supporting function
 *
 * @return array $languages
 */
function language_client()
{
    $languages = [];

    if ($list = stringLow( getHeader('http-accept-language') )) {
        if (preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/', 
            $list, $list)) {
            $languages = array_combine($list[1], $list[2]);
            foreach ($languages as $n => $v) {
                $languages[$n] = $v ? $v : 1;
            }
            arsort($languages, SORT_NUMERIC);
        }
    }

    return $languages;
}

/**
 * Strip Slashes If Magic Quotes ON
 *
 * @return array|string $opt
 */
function filterSlashes($opt=[])
{
    if (ini_get('magic_quotes_gpc')) {
        $opt = filter_strip_slashes($opt);
    }

    return $opt;
}

/**
 * Multidimensional Strip Slashes - supporting function
 *
 * @return array|string $opt
 */
function filter_strip_slashes($opt)
{
    if (is_array($opt) && count($opt)) {
        foreach ($opt as $k => $v) {
            $opt[filter_strip_slashes($k)] = filter_strip_slashes($v);
        }
    } else {
        $opt = stripslashes($opt);
    }

    return $opt;
}

function filterGET()
{
    return filter(filterSlashes($_GET));
}

function filterPOST()
{
    return filter(filterSlashes($_POST));
}

function filterCOOKIE()
{
    return filter(filterSlashes($_COOKIE));
}

function filterSERVER()
{
    $server = [];

    foreach ($_SERVER as $key => $value) {
        $server[stringLow($key)] = $value;
        $server[stringLow(str_replace('_', '-', $key))] = $value;
    }

    return $server;
}

function filter($var=[], $filter = " \t\n\r\0\x0B", $default = '')
{
    if (is_array($var) && count($var)) {
        foreach ($var as $key=>$value) {
            if (is_string($value)) {
                // Translate HTML entities
                $value = strtr($value, 
                    array_flip(
                        get_html_translation_table(
                            HTML_ENTITIES, ENT_QUOTES
                        )
                    )
                );

                // Remove multi whitespace
                $value = preg_replace("@\s+\s@Ui", " ", $value);
                $value = trim($value, $filter);
                if (empty($value)) {
                    unset($var[$key]);
                } else {
                    $var[$key]=$value;
                }
            }
        }
    } else {
        if (!empty($var)) {
            $var = filter([$var=>$var], $filter);
            return array_pop( $var );
        }
    }

    return $var;
}

function cookieGet($name = '')
{
    return (!empty($name) && isset($_COOKIE[$name]))? 
        unserialize(base64_decode($_COOKIE[$name])): null;
}

/*
setcookie ( string $name [, 
    string $value = "" [, 
        int $expire = 0 [, 
            string $path = "" [, 
                string $domain = "" [, 
                    bool $secure = FALSE [, 
                        bool $httponly = FALSE 
]]]]]] )
*/
function cookieSet($key='', $value='', $days = 1)
{
    if (!empty($key) && !empty($value)) {
        $days = !empty($days)? (int) $days: 1;
        $value = base64_encode(serialize($value));
        $expire = time() + (60*60*24*$days);

        return setcookie($key, $value, $expire);
    }

    return false;
}

function cookieDelete($name)
{
    if (session_id() && !empty($name) && isset($_COOKIE[$name])) {
        return setcookie($name, '', time() - CMSIF_COOKIE_LTIME);
    }

    return false;
}

function sessionStart()
{
    if (!isset($_SESSION['safety'])) {
        session_id(CMSIF_SESSION_ID);
        session_start([
            'cookie_lifetime' => CMSIF_COOKIE_LTIME
        ]);
        $_SESSION['safety'] = true;
    }

    $_SESSION['sessionid'] = session_id();
}

function sessionGet($key)
{
    return (!empty($key) && isset($_SESSION[$key]))? $_SESSION[$key]: null;
}

function sessionSet($key = '', $value = '')
{
    if (!empty($key)) {
        $_SESSION[$key] = $value;
        return true;
    }
    return false;
}

function sessionFlush($key='')
{
    if (!empty($key) && isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
        return true;
    }

    return false;
}

function sessionUnset() 
{
    if (!empty(session_id())) {
        session_unset();
    }
}

function fileRead($file = '', $format = 'plain') //plain, html, json
{
    $file_path = filePath($file);

    if (!empty($file_path))
    {
        if (!file_exists($file_path) || !is_readable($file_path))
        {
            return null;
        }

        $content = file_get_contents($file_path);

        switch ($format)
        {
            case 'html':
                $content = htmlentities($content);
                break;
            case 'json':
                $content = json_decode($content , true);
                break;
        }

        return $content;
    }

    return false;
}

function fileWrite($file = '', $opt = '', $format = 'plain')
{
    $file_path = filePath($file);

    if (!empty($file_path))
    {
        if($handle = fopen($file_path, 'w'))
        {
            switch ($format)
            {
                case 'json':
                    $opt = json_encode($opt);
                    break;
            }

            return (bool) fwrite($handle, $opt);
        }
    }

    return false;
}

function filePath($file, $base = '')
{
    $file_path = '';
    $file = filter($file, ' ./');
    if (!empty($file)) {
        $file_path = (!empty($base)? $base : CMSIF_FILES) . $file;
    }

    return $file_path;
}

function fileExecute($file = '', $opt = [])
{
    $out = '';

    if (!empty($file)) {

        $type = isset($opt['type'])? $opt['type']: 'template';

        if ('module' == $type) {
            $file = filePath($file, CMSIF_MODULES);
        } else if (in_array($type, ['template', 'partial'])) {
            $file = filePath($file, CMSIF_TEMPLATES);
        } else {
            $file = filePath($file);
        }

        if (!empty($file)) {
            ob_start();
            extract($opt);
            include $file;
            $out = ob_get_contents();
            ob_clean();
        }
    }

    return $out;
}

function router($method = null, $match_path = '/', $call)
{
    if ($method === getMethod()) {
        if ($match_path === getUrl()) {
            if (is_callable($call)) {
                is_string($call) ? $call() : call_user_func($call);
            } else {
                require $call;
            }
            dataSet('route', $match_path);
            exit;
        }

        $matches = [];
        preg_match( '@^'.$match_path.'@', getUrl(), $matches);
        if (!empty($matches[1]) || $match_path === getUrl())
        {
            if (is_callable($call))
            {
                is_string($call) ? $call($matches): call_user_func($call, $matches);
            } else {
                require $call;
            }
            dataSet('route', $match_path);
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
    $form_elements = ['text', 'password', 'checkbox', 'select', 'submit'];

    $out = '<form action="'.(!empty($options['action'])? $options['action']: 
        getUrl()).'" method="'.($options['method']).'">';

    if (count($options['fields'])) {
        foreach ($options['fields'] as $k=>$v) {
            if (in_array($k, $form_elements)) {
                $vars = json_decode($v, true);
                if (!isset($vars['name']) || empty($vars['name'])) {
                    $vars['name'] = uniqid('name_');
                }
                if (!isset($vars['id']) || empty($vars['id'])) {
                    $vars['id'] = uniqid('id_');
                }
                $out .= '<input type="'.$k.'" name="'.$vars['name'].
                    '" id="'.$vars['id'].'" placeholder="'.$vars['id'].
                        '" value="'.('submit'==$k? $vars['id']: '').'">';
            } else {
                $out .= '<p class="alert">Unknown element: '.$k.'</p>'.EOL;
            }
        }
    }

    $out .= '</form>';

    return $out;
}

function dbh()
{
    $dbh = dataGet('dbh');
    if (null == $dbh) { 
        $dbh = dbConnect();
        dataSet('dbh', $dbh);
    }
    return $dbh;
}

function dbQuery($query, $opt = [])
{
    if (empty($query)) { return null; }

    $out = [];

    if (is_array($query) && count($query)) {
        $out = dbMultiQuery($query, $opt);
    } else {
        $result = mysqli_query(dbh(), $query);
        if (!$result) {
            if (!empty(dbh()->error)) {
                dump([$query, dbh()->error]);
            }
        } else {
            while ($row = mysqli_fetch_assoc($result)) {
                $out[] = $row;
            }
            mysqli_free_result($result);
        }
    }

    return $out;
}

function dbMultiQuery($query, $opt = [])
{
    if (empty($query)) { return null; }

    $out = [];

    $query = implode(';', $query);

    if (mysqli_multi_query(dbh(), $query)) {
        do {
            /* store first result set */
            $result = mysqli_store_result(dbh());
            $out = dbFetchAssoc($result);
        }
        while (@mysqli_next_result(dbh()));
    }

    return $out;
}

function dbFetchAssoc($result = null) 
{
    $out = [];
    if (!$result) {
        if (!empty(dbh()->error)) {
            dump([$query, dbh()->error]);
        }
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $out[] = $row;
        }
        mysqli_free_result($result);
    }

    return $out;
}

function dbConnect()
{
    $dbh = new mysqli(CMSIF_DB_HOST, CMSIF_DB_USER, CMSIF_DB_PASS, CMSIF_DB_NAME);
    if (mysqli_connect_errno()) {
        dump('Connect failed: '. mysqli_connect_error() . EOL);
        exit();
    }
    return $dbh;
}

function dbDisconnect()
{
    return mysqli_close(dbh());
}

function CSRFToken($name = 'CSRFToken')
{
    $key = base64_encode(uniqid());
    sessionSet($name, $key);
    return $key;
}

function CSRFTokenCheck($name = 'CSRFToken', $value)
{
    $key = sessionGet($name);
    return ($key === $value);
}

function getHeader($key = '', $default = null)
{
    $server = filterSERVER();
    return (isset($server[$key]) && !empty($server[$key])? $server[$key]: $default);
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
    $method = getHeader('request-method');
    return stringLow( $method );
}

function getUrl()
{
    $url = explode('?', getHeader('request-uri'));
    return $url[0];
}

function getHost()
{
    $host = getHeader('http-host');
    return stringLow( '//'.$host );
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
    $ip = getHeader('http-cf-connecting-ip');
    if ($ip !== null) {
        return $ip;
    }

    $ip = getHeader('http-x-forwarded-for');
    if ($ip !== null) {
        return $ip;
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

function dataIsset($name)
{
    global $data;
    return array_key_exists($name, $data) === true;
}

function dataSet($name, $value = null)
{
    global $data;
    $data[$name] = $value;
}

function dataGet($name, $default = null)
{
    global $data;
    return isset($data[$name]) ? $data[$name]: $default;
}

function dataUnset($name, $value = null)
{
    global $data;
    if(dataIsset($name))
    {
        unset($data[$name]);
    }
}

function headers($headers = [])
{
    foreach ($headers as $header) {
        header($header);
    }
}


function auth($type = CMSIF_DEFAULT_AUTH_TYPE)
{
    if (in_array($type, CMSIF_AUTH_TYPES)) {
        switch ($type) {
            case 'file':
                break;

            case 'db':
                break;

            case 'http':
                authHTTP(CMSIF_SYSTEM_NAME);
                break;
        }
    } else {
        renderView('error', 'Wrong Auth Type Selected!');
    }
}

function authHTTP($name = '')
{
    headers([
        'WWW-Authenticate: Basic realm="'.$name.'"',
        'HTTP/1.0 401 Unauthorized',
    ]);
    exit(0);
}

function authTokenGenerate($length = 256) 
{
    $out = '';

    $keys = array_merge(
        ['^','-','#','&','%','@','?'],
        [1, 2, 3, 4, 5, 6, 7, 8, 9],
        range('a', 'z'), range('A', 'Z')
    );

    for ($i = 0; $i < $length; $i++) 
    {
        $out .= $keys[mt_rand(0, count($keys) - 1)];
    }

    return $out;
}

function headerHTML()
{
    headers(['Content-Type: text/html; charset='.CMSIF_ENCODING]);
    echo view(['<!DOCTYPE html>', '<html lang="en">', 
        '<head><meta charset="'.CMSIF_ENCODING.'">'.
        '<meta name="viewport" content="width=device-width, '.
        'initial-scale=1, shrink-to-fit=no"></head>']);
}

function footerHTML()
{
    echo view('</html>');
}

function view($content = '')
{
    $out = '';

    if (is_array($content)) {
        foreach ($content as $content_part) {
            $out .= view($content_part);
        }
    } else {
        if (!empty($content)) {
            $out .= $content . EOL;
        }
    }

    return $out;
}

function renderView($template = 'main', $content = '')
{
    $render_template = renderTemplate($template);

    if (is_array($content)) {
        $content = view($content);
    }

    $assets          = '';
    $assets_external = dataGet('assets_external', []);
    $assets_local    = dataGet('assets_local', []);
    $blocks          = dataGet('blocks', []);
    $partials        = dataGet('partials', []);
    $assets_out      = [];

    if (count($assets_external)) {
        $assets_out[] = implode(EOL, $assets_external);
    }

    if (count($assets_local)) {
        $assets_out[] = implode(EOL, $assets_local);
    }

    $assets = implode(EOL, $assets_out);
    $sections = ['header', 'aside', 'article', 'section', 'footer'];

    if (count($blocks)) {
        foreach ($blocks as $id=>$block) {
            if (in_array($block['type'], $sections)) {
                $content .= '<'.$id. (isset($block['name'])?' id="'.$block['name'].'"':''). '>'. $block['content'].'</'.$id.'>'.EOL;
            } else {
                $content .= sprintf('<div id="%s">%s</div>', 
                    $id, $block['content']). EOL;
            }
        }
    }

    if (count($partials)) {
        foreach ($partials as $search => $partial) {
            $content = str_replace($search, $partial, $content);
        }
    }

    headerHTML();

    echo fileExecute($render_template, compact('assets', 'content'));
}

function renderTemplate($template = '')
{
    $file_path = CMSIF_TEMPLATES.'/'.$template.'.php';
    if (file_exists($file_path) && is_readable($file_path)) {
        return '/'.$template.'.php';
    } else {
        error404();
    }
}

function renderBlock($content = '', $name = '', $type = 'block')
{
    $blocks = dataGet('blocks', []);
    if (empty($name)) {
        $name = 'block_'. (count($blocks) + 1);
    }
    $blocks[ $name ] = ['content' => $content, 'type' => $type];
    dataSet('blocks', $blocks);
    return true;
}

function renderPartial($partial = '', $name = '', $opt = [])
{
    $partial = fileExecute('_partials/'.$partial.'.php', $opt);
    $partials = dataGet('partials', []);
    if (empty($name)) {
        $name = 'partial_'. (count($partials) + 1);
    }
    $partials[ '{{ '.$name.' }}' ] = $partial;
    dataSet('partials', $partials);
    return true;
}

function error404()
{
    headers([
        'HTTP/1.0 404 Not Found',
    ]);
    exit(0);
}

function asset($asset = '', $opt = [])
{
    $out = '';
    if (!empty($asset)) {
        $assets = dataGet('assets_local', []);
        if (isset($assets[ md5($asset) ])) {
            return;
        }

        $type        = isset($opt['type'])? $opt['type']: null;
        $media       = isset($opt['media'])? $opt['media']: null;
        $integrity   = isset($opt['integrity'])? $opt['integrity']: null;
        $crossorigin = isset($opt['crossorigin'])? $opt['crossorigin']: null;
        $version     = isset($opt['version'])? $opt['version']: null;

        $file = filter($asset, ' .\/');
        $ext = stringLow( pathinfo($file, PATHINFO_EXTENSION) );
        $file_path = CMSIF_WEBROOT .'/'. $ext .'/'.  $file;
        $file_url  = getHost() . CMSIF_ASSETS . $ext.'/'. $file .
            (!is_null($version)? '?'.$version: '');

        if (file_exists($file_path) && is_readable($file_path)) {
            $options  = $media? ' media="'.$media.'"': '';
            $options .= $integrity? ' integrity="'.$integrity.'"': '';
            $options .= $crossorigin? ' crossorigin="'.$crossorigin.'"': '';

            switch ($ext) {
                case 'css':
                    $out = '<link rel="stylesheet" type="text/css" href="'.
                        $file_url.'"'.$options.'>';
                    break;

                case 'js':
                    $out = '<script language="javascript" src="'.
                        $file_url.'"'.$options.'></script>';
                    break;

                default:
                    break;
            }
        }

        if (!empty($out)) {
            $assets[ md5($asset) ] = $out;
            dataSet('assets_local', $assets);
        }
    }

    return $out;
}

function assetExternal($asset = '', $opt = [])
{
    $out = '';
    if (!empty($asset)) {
        $assets = dataGet('assets_external', []);
        if (isset($assets[ md5($asset) ])) { 
            return ''; 
        }

        $type        = isset($opt['type'])? $opt['type']: null;
        $media       = isset($opt['media'])? $opt['media']: null;
        $integrity   = isset($opt['integrity'])? $opt['integrity']: null;
        $crossorigin = isset($opt['crossorigin'])? $opt['crossorigin']: null;

        $ext = stringLow( !is_null($type)? $type: 
            pathinfo($asset, PATHINFO_EXTENSION) );

        $options  = $media? ' media="'.$media.'"': '';
        $options .= $integrity? ' integrity="'.$integrity.'"': '';
        $options .= $crossorigin? ' crossorigin="'.$crossorigin.'"': '';

        switch ($ext) {
            case 'css':
                $out = '<link rel="stylesheet" type="text/css" href="'.
                    $asset.'"'.$options.'>';
                break;

            case 'js':
                $out = '<script language="javascript" src="'.
                    $asset.'"'.$options.'></script>';
            default:
                break;
        }
        
        if (!empty($out)) {
            $assets[md5($asset)] = $out;
            dataSet('assets_external', $assets);
        }
    }

    return $out;
}

function stringLow($opt = '')
{
    return strtolower($opt);
}

function stringUp($opt = '')
{
    return strtoupper($opt);
}

function stringLen($opt = '')
{
    return strlen($opt);
}

function stringReplace($opt = '', $from = '', $to = '')
{
    return str_ireplace($from, $to, $opt);
}
