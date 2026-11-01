<?php

/**
 * NanoMVC_Library_Markdown
 *
 * Small, dependency-free UTF-8 Markdown renderer.
 *
 * Specification: CommonMark 0.31.2 (2024-01-28).
 * https://spec.commonmark.org/0.31.2/
 * https://spec.commonmark.org/0.31.2/#emphasis-and-strong-emphasis
 * https://spec.commonmark.org/0.31.2/#an-algorithm-for-parsing-nested-emphasis-and-links
 *
 * Currently implements only emphasis, strong emphasis, and ASCII punctuation
 * escapes. Returns an HTML fragment, without paragraphs or automatic <br> tags.
 * Links, code spans/blocks, entities, and raw HTML are not recognized as Markdown
 * constructs yet. Their text can contain emphasis markers just like other text.
 * Literal HTML is escaped; pre-escaped entities are treated as literal input.
 *
 * @package NanoMVC
 * @license LGPL v2.1 or later
 */
class NanoMVC_Library_Markdown {

  /**
   * Render all currently supported Markdown constructs.
   *
   * Future constructs belong in the parsing stage, before HTML rendering.
   * Do not chain HTML-producing methods: that would reparse generated tags
   * and escape the same text repeatedly.
   *
   * @param string $text Raw UTF-8 Markdown, not previously rendered HTML.
   * @return string HTML fragment for insertion into an element's content.
   * @throws InvalidArgumentException If the input is not valid UTF-8.
   */
  public function parse(string $text): string {
    return $this->parseEmphasis($text);
  }

  /**
   * Render only italic (* or _) and bold (** or __) text.
   *
   * Uses CommonMark delimiter runs, Unicode flanking rules, and the rule of
   * three. Unmatched markers remain literal; nested tags are kept.
   * This is an inline fragment parser: it does not recognize paragraph
   * boundaries, so blank lines do not split the input into separate blocks.
   * Line endings are preserved. NUL is replaced with U+FFFD per CommonMark.
   * The result is already escaped HTML; do not pass it through esc_html().
   *
   * @param string $text Raw UTF-8 text.
   * @return string HTML fragment containing only generated em/strong tags.
   * @throws InvalidArgumentException If the input is not valid UTF-8.
   */
  public function parseEmphasis(string $text): string {
    if (preg_match('//u', $text) !== 1)
      throw new InvalidArgumentException('Markdown text must be valid UTF-8.', 500);

    $text = str_replace("\0", "\u{FFFD}", $text);
    [$tokens, $first_delimiter] = $this->tokenize($text);
    $this->resolveEmphasis($tokens, $first_delimiter);

    return $this->renderTokens($tokens);
  }

  /**
   * Split literal text and delimiter runs, retaining original run lengths.
   *
   * String tokens contain literal text. Array tokens also hold links to the
   * previous/next active delimiter and the tags matched at each end.
   *
   * @param string $text Valid UTF-8 text.
   * @return array Tokens and the index of the first active delimiter, or null.
   */
  protected function tokenize(string $text): array {
    $tokens = [];
    $first = null;
    $last = null;
    $position = 0;
    $length = strlen($text);

    while ($position < $length) {
      $literal_length = strcspn($text, '*_\\', $position);

      if ($literal_length > 0) {
        $tokens[] = substr($text, $position, $literal_length);
        $position += $literal_length;
        continue;
      }

      $marker = $text[$position];

      if ($marker === '\\') {
        if ($position + 1 < $length && $this->isEscapable($text[$position + 1])) {
          $tokens[] = $text[$position + 1];
          $position += 2;
        } else {
          $tokens[] = '\\';
          $position++;
        }
        continue;
      }

      $run_length = strspn($text, $marker, $position);
      $before = $this->previousCharacter($text, $position);
      $after = $this->nextCharacter($text, $position + $run_length);
      [$can_open, $can_close] = $this->delimiterFlags($marker, $before, $after);

      if (!$can_open && !$can_close) {
        $tokens[] = str_repeat($marker, $run_length);
      } else {
        $index = count($tokens);
        $tokens[] = [ 'marker' => $marker
                     ,'length' => $run_length
                     ,'remaining' => $run_length
                     ,'can_open' => $can_open
                     ,'can_close' => $can_close
                     ,'previous' => $last
                     ,'next' => null
                     ,'open' => []
                     ,'close' => []
                    ];

        if ($last !== null) $tokens[$last]['next'] = $index;
        $first ??= $index;
        $last = $index;
      }

      $position += $run_length;
    }

    return [$tokens, $first];
  }

  /**
   * Check the ASCII punctuation characters escapable with a backslash.
   *
   * @param string $character One input byte.
   * @return bool
   */
  protected function isEscapable(string $character): bool {
    $code = ord($character);

    return ($code >= 0x21 && $code <= 0x2F)
        || ($code >= 0x3A && $code <= 0x40)
        || ($code >= 0x5B && $code <= 0x60)
        || ($code >= 0x7B && $code <= 0x7E);
  }

  /**
   * Read the Unicode character immediately before a byte offset.
   *
   * @param string $text Valid UTF-8 text.
   * @param int $position Byte offset at a character boundary.
   * @return string A newline represents the beginning of the input.
   */
  protected function previousCharacter(string $text, int $position): string {
    if ($position === 0) return "\n";

    $start = $position - 1;
    while ($start > 0 && (ord($text[$start]) & 0xC0) === 0x80) $start--;

    return substr($text, $start, $position - $start);
  }

  /**
   * Read the Unicode character at a byte offset without requiring mbstring.
   *
   * @param string $text Valid UTF-8 text.
   * @param int $position Byte offset at a character boundary.
   * @return string A newline represents the end of the input.
   */
  protected function nextCharacter(string $text, int $position): string {
    if ($position === strlen($text)) return "\n";

    $code = ord($text[$position]);
    $length = $code < 0x80 ? 1 : ($code < 0xE0 ? 2 : ($code < 0xF0 ? 3 : 4));

    return substr($text, $position, $length);
  }

  /**
   * Apply CommonMark 0.31.2 Unicode flanking rules to a delimiter run.
   *
   * Whitespace is Zs, TAB, LF, FF, or CR; punctuation includes Unicode P and S.
   * Unlike asterisks, underscores cannot normally delimit text within a word.
   *
   * @param string $marker Asterisk or underscore.
   * @param string $before Previous Unicode character, or a boundary newline.
   * @param string $after Next Unicode character, or a boundary newline.
   * @return array Whether the run can open and/or close emphasis.
   */
  protected function delimiterFlags(string $marker, string $before, string $after): array {
    $before_space = (bool)preg_match('/^[\p{Zs}\t\n\f\r]$/u', $before);
    $after_space = (bool)preg_match('/^[\p{Zs}\t\n\f\r]$/u', $after);
    $before_punctuation = (bool)preg_match('/^[\p{P}\p{S}]$/u', $before);
    $after_punctuation = (bool)preg_match('/^[\p{P}\p{S}]$/u', $after);

    $left = !$after_space && (!$after_punctuation || $before_space || $before_punctuation);
    $right = !$before_space && (!$before_punctuation || $after_space || $after_punctuation);

    if ($marker === '_')
      return [ $left && (!$right || $before_punctuation)
              ,$right && (!$left || $after_punctuation)
             ];

    return [$left, $right];
  }

  /**
   * Match delimiters in source order without recursive parsing.
   *
   * Original lengths determine the rule of three; remaining lengths determine
   * whether a match consumes one or two markers. Failed-search lower bounds
   * are cached for each marker, opener flag, and original length modulo three.
   * Removing intervening delimiters prevents crossing HTML tags.
   *
   * @param array $tokens Text and delimiter tokens, updated in place.
   * @param int|null $closer First active delimiter.
   * @return void
   */
  protected function resolveEmphasis(array &$tokens, ?int $closer): void {
    $bottoms = ['*' => array_fill(0, 6, -1), '_' => array_fill(0, 6, -1)];

    while ($closer !== null) {
      if (!$tokens[$closer]['can_close']) {
        $closer = $tokens[$closer]['next'];
        continue;
      }

      $marker = $tokens[$closer]['marker'];
      $category = ($tokens[$closer]['can_open'] ? 3 : 0) + $tokens[$closer]['length'] % 3;
      $opener = $tokens[$closer]['previous'];
      $found = false;

      while ($opener !== null && $opener >= $bottoms[$marker][$category]) {
        if ($tokens[$opener]['marker'] === $marker && $tokens[$opener]['can_open']) {
          $odd_match = ($tokens[$opener]['can_close'] || $tokens[$closer]['can_open'])
                    && ($tokens[$opener]['length'] + $tokens[$closer]['length']) % 3 === 0
                    && ($tokens[$opener]['length'] % 3 !== 0 || $tokens[$closer]['length'] % 3 !== 0);

          if (!$odd_match) {
            $found = true;
            break;
          }
        }

        $opener = $tokens[$opener]['previous'];
      }

      if (!$found) {
        $bottoms[$marker][$category] = $closer;
        $next = $tokens[$closer]['next'];
        if (!$tokens[$closer]['can_open']) $this->removeDelimiter($tokens, $closer);
        $closer = $next;
        continue;
      }

      $used = $tokens[$opener]['remaining'] >= 2 && $tokens[$closer]['remaining'] >= 2 ? 2 : 1;
      $tag = $used === 2 ? 'strong' : 'em';
      $tokens[$opener]['open'][] = $tag;
      $tokens[$closer]['close'][] = $tag;
      $tokens[$opener]['remaining'] -= $used;
      $tokens[$closer]['remaining'] -= $used;

      $between = $tokens[$opener]['next'];
      while ($between !== $closer) {
        $next = $tokens[$between]['next'];
        $this->removeDelimiter($tokens, $between);
        $between = $next;
      }

      if ($tokens[$opener]['remaining'] === 0) $this->removeDelimiter($tokens, $opener);

      if ($tokens[$closer]['remaining'] === 0) {
        $next = $tokens[$closer]['next'];
        $this->removeDelimiter($tokens, $closer);
        $closer = $next;
      }
    }
  }

  /**
   * Unlink a matched or unusable delimiter, retaining its output token.
   *
   * @param array $tokens Text and delimiter tokens.
   * @param int $index Active delimiter index.
   * @return void
   */
  protected function removeDelimiter(array &$tokens, int $index): void {
    $previous = $tokens[$index]['previous'];
    $next = $tokens[$index]['next'];

    if ($previous !== null) $tokens[$previous]['next'] = $next;
    if ($next !== null) $tokens[$next]['previous'] = $previous;
  }

  /**
   * Escape literal text and emit only tags created by delimiter matching.
   *
   * Matching discovers inner tags first, so opening tags are emitted in
   * reverse order. Literal marker remnants stay outside the matched tags.
   * Quotes follow CommonMark's HTML examples: double quotes are escaped;
   * apostrophes remain literal text. This output is not an attribute value.
   *
   * @param array $tokens Text and resolved delimiter tokens.
   * @return string HTML fragment.
   */
  protected function renderTokens(array $tokens): string {
    $html = [];

    foreach ($tokens as $token) {
      if (is_string($token)) {
        $html[] = htmlspecialchars($token, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
        continue;
      }

      foreach ($token['close'] as $tag) $html[] = "</{$tag}>";
      $html[] = str_repeat($token['marker'], $token['remaining']);
      for ($i = count($token['open']) - 1; $i >= 0; $i--) $html[] = "<{$token['open'][$i]}>";
    }

    return implode('', $html);
  }

}
