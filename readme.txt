abbr:
1: sgi -- Simple Gateway Interface
2: sgs -- Simple Gateway Service
3: sga -- Simple Gateway Action

=================================================================================

mapping rule:
for example url: http://www.host.com/dir_abc_def/dir_ijk_gh/file_name_xxx/method_name_yyy.sgi?data=json_encoded_input_data
sgi suffix: default: .sgi, defined in Sgi::SGI_SUFFIX
sgs dir path: YOUR_SGS_DIR . '/' . 'dir_abc_def/dir_ijk_gh'
sgs class name: DirAbcDef_DirIjkGh_FileNameXxx
sga method name: SGA_MethodNameYyy, default sag prefix: SGA_, defined in SGA_PREFIX
tpl file path: YOUR_TPL_DIR . '/' . 'dir_abc_def/dir_ijk_gh/file_name_xxx/mehod_name_yyy.html'
tpl suffix: default: .html, defined in Sgi::TPL_SUFFIX

=================================================================================

input key rule:
1. default output format key: of, defined in Sgi::OF_KEY
2. default input data key: data, defined in Sgi::INPUT_DATA_KEY

=================================================================================

support protocol: only http

=================================================================================

support input format: only json

=================================================================================

support output format:
1: json
2: jsonp
3: xml
4: tpl
5: raw

if use raw output format, sgi echo $output[0] directly.
=================================================================================

related nginx config:

location ~ \.sgi$ 
{
	if ( -f $request_filename) 
	{
		break;
	}

	if ( !-f $request_filename) 
	{
		rewrite ^/(.+)$ /index.php last;
		break;
	}
}

=================================================================================
sample usage:

index.php in dir htdocs--------------------------

<?php
date_default_timezone_set('Asia/Shanghai');
define('PROJECT_ROOT', realpath(__DIR__ . '/../../../'));
define('TIMESTAMP', $_SERVER['REQUEST_TIME']);

require_once PROJECT_ROOT . '/component/smarty/libs/Smarty.class.php';
require_once PROJECT_ROOT . '/component/sgi/sgi.php';

$smartyConfig = new SgiSmartyConfig(PROJECT_ROOT . '/src/frontend/htdocs/tpl', '/tmp/compiled');
Sgi::setSmartyConfig($smartyConfig);
Sgi::run(PROJECT_ROOT . '/src/backend/sgs');

pri_operate.php in dir qq_friend----------------
<?php
class QqFriend_PriOperate {

	public function SGA_GetTheName(array $inputData) {
        return array('ret' => 0, 'data'=> array('msg' => 'hello, sgi', 'input' => $inputData));
	}

    public function SGA_TestRaw() {
        $a = $_REQUEST['a'];
        return array('success');
    }
}

url---------------------------------------------
http://www.host.com/qq_friend/pri_operate/get_the_name.sgi?data={"a":2,"b":"msg"}&of=json
output------------------------------------------
{"ret":0,"data":{"msg":"hello, sgi","input":{"a":2,"b":"msg"}}}

url---------------------------------------------
http://www.host.com/qq_friend/pri_operate/test_raw.sgi?a=2
output------------------------------------------
success

=================================================================================

sgs action rule:
1: action input data is an array passed as action function argument, the value of Sgi::INPUT_DATA_KEY.
2: action return is the output, must be array type
3: action name prefixed with SGA_PREFIX

=================================================================================
EOF

