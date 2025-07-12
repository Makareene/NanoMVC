<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/strict.dtd">
<html>
  <head>
    <title>Welcome to NanoMVC!</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style type="text/css">
      body {
        background: #9dbde1 url(https://www.tinymvc.com/images/bg-gradient.gif) top repeat-x;
        color: #666666;
        font-family: arial, sans-serif;
        font-size: 100%;
        line-height: 1.7em;
        margin: 0 auto;
        text-align: center;
        width: 500px;
      }

      h1 {
        font-size: 2.18em;
        letter-spacing: -0.01em;
      }

      a:link {
        color: #134c8c;
      }

      a:visited {
        color: #666666;
      }

      .code {
        text-align: left;
        margin: 0 0 1.5em 0;
        font-size: 1em;
        border: 1px solid #134c8c;
        background-color: #cae3ff;
        color: #c44242;
        padding: 0.2em 1em 0.4em;
      }

      #bottom {
        border-top: 1px solid #134c8c;
        margin-top: 1em;
        padding-top: 1em;
        font-size: 0.8em;
      }
    </style>
  </head>
  <body>

    <h1>Welcome to NanoMVC!</h1>

    <p>This is NanoMVC based on original TinyMVC <?=NMVC_VERSION?>.</p>
    <p>The view file for this page is here:</p>
    <div class="code">nanomvc/myapp/views/index_view.php</div>

    <p>The controller for this page is here:</p>
    <div class="code">nanomvc/myapp/controllers/default.php</div>

    Let's get started, head to the <a href="https://www.tinymvc.com/wiki/index.php/Documentation">documentation</a>!

    <div id="bottom">
      <a href="https://www.tinymvc.com/">TinyMVC</a> was originally licensed under the GNU <a rel="license" href="https://www.gnu.org/licenses/lgpl.html">LGPL</a> license.<br>
      <span style="font-size: 0.8em">This page was rendered in {TMVC_TIMER} seconds.</span>
    </div>
  </body>
</html>