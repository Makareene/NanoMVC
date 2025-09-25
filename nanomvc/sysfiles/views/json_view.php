<?php
$arr_main = ['err_type' => $code != 404? 'Error': 'Type'
            ,'err_code' => $code
            ,'err_name' => NanoMVC_Script_Helper::esc_html($code_val)
            ];

$arr_desc = [];

if ($show_error)
  $arr_desc = ['err_msg' => NanoMVC_Script_Helper::esc_html($message)
              ,'err_file' => NanoMVC_Script_Helper::esc_html($file)
              ,'err_line' => NanoMVC_Script_Helper::esc_html($line)
              ];

$arr_res = array_merge($arr_main, $arr_desc);
?>

<?=json_encode($arr_res)?>
