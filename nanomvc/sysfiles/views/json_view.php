<?php
$arr_main = ['err_type' => $code != 404? 'Error': 'Type'
            ,'err_code' => $code
            ,'err_name' => $code_val
            ];

$arr_desc = [];

if ($show_error)
  $arr_desc = ['err_msg' => $message
              ,'err_file' => $file
              ,'err_line' => $line
              ];

$arr_res = array_merge($arr_main, $arr_desc);
?>

<?=json_encode($arr_res)?>
