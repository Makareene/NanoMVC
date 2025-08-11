<?php if(!$outputed):?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>NanoMVC Not Found</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="The requested resource was not found on this server.">
    <style>
    body {
      font-family: system-ui, sans-serif;
      background-color: #fff;
      color: #000;
    }
    h1 {
      font-size: 1.5em;
      color: #b35900;
      margin-bottom: 1em;
    }
    </style>
  </head>
  <body>
    <h1>NanoMVC Not Found</h1>
<?php endif?>

    <div style="display: block; margin: 1em 0; padding: .33em 6px; background-color: #fff3cd; border: 1px solid #ffb84d; color: #7a4d00; text-align: left">
      <b>Type:</b> <?=NanoMVC_Script_Helper::esc_html($code_val)?>
      <?php if($show_error):?>
      <br><b>Message:</b> <?=NanoMVC_Script_Helper::esc_html($message)?><br>
      <b>File:</b> <?=NanoMVC_Script_Helper::esc_html($file)?><br>
      <b>Line:</b> <?=NanoMVC_Script_Helper::esc_html($line)?>
      <?php endif?>
    </div>

<?php if(!$outputed):?>
  </body>
</html>
<?php endif?>
