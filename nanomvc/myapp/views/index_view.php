<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to NanoMVC</title>
    <meta name="description" content="NanoMVC is a minimalist PHP MVC framework inspired by TinyMVC. Designed for simplicity, speed, and small-to-medium projects with clean architecture.">

    <style>
      :root {
        --bg: #121b24;
        --fg: #e0e6ed;
        --primary: #4db8ff;
        --accent: #86d3ff;
        --code-bg: #1f2c38;
        --code-border: #3a536b;
        --muted: #a0a7af;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        padding: 2em;
        background-color: var(--bg);
        color: var(--fg);
        font-family: system-ui, sans-serif;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
      }

      h1 {
        font-size: 2.2em;
        color: var(--primary);
        margin-bottom: 0.5em;
      }

      p {
        max-width: 600px;
        margin: 0.75em 0;
        line-height: 1.6em;
      }

      a {
        color: var(--accent);
        text-decoration: none;
      }

      a:hover {
        text-decoration: underline;
      }

      .code {
        font-family: monospace;
        background: var(--code-bg);
        border: 1px solid var(--code-border);
        color: #ff9999;
        padding: 0.5em 1em;
        margin: 1em 0;
        border-radius: 6px;
        text-align: left;
        width: fit-content;
        max-width: 90%;
      }

      #bottom {
        margin-top: 2em;
        padding-top: 1em;
        font-size: 0.85em;
        color: var(--muted);
        border-top: 1px solid var(--code-border);
      }
    </style>
    </head>
  <body>

    <h1>Welcome to NanoMVC</h1>

    <p>You’re now running <strong>NanoMVC <?=NMVC_VERSION?></strong>, a modernized and simplified framework based on TinyMVC 1.2.3.</p>

    <p>This page is generated using the following view:</p>
    <div class="code">nanomvc/myapp/views/index_view.php</div>

    <p>...and handled by the controller:</p>
    <div class="code">nanomvc/myapp/controllers/default.php</div>

    <p>
    Ready to build something great? <br>
    Head over to the <a href="https://nanomvc.nipaa.fyi/doc" rel="nofollow" target="_blank">NanoMVC Documentation</a> to get started.
    </p>

    <div id="bottom">
      <p>
        <a href="https://nanomvc.nipaa.fyi" rel="nofollow" target="_blank">NanoMVC</a> is open-source, originally licensed under the
        <a href="https://www.gnu.org/licenses/lgpl.html" rel="license nofollow" target="_blank">GNU LGPL</a>.
      </p>
      <p>This page was rendered in {NMVC_TIMER} seconds.</p>
    </div>

  </body>
</html>
