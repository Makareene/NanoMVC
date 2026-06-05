<?php

$result = [ 'err_type' => isset($statuses[$code]) ? 'HTTP' : 'Error'
           ,'err_code' => $code
           ,'err_name' => $code_val
          ];

if ($show_error)
  $result += [ 'err_msg'  => $message
              ,'err_file' => $file
              ,'err_line' => $line
             ];

echo json_encode( $result
                 ,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                );

?>
