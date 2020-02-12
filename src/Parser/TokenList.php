<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template\Parser;

/**
 * @property string $token
 * @property mixed $token_data
 * @property array<string> $tokens
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */

class TokenList
{

    public const T_MODULE = 'MODULE';
    public const T_REPLACE = 'REPLACE';
    public const T_REPLACE_LOCAL = 'REPLACE_LOCAL';
    public const T_TEXT_HTML = 'TEXT_HTML';
    public const T_START_ELEMENT = 'START_ELEMENT';
    public const T_END_ELEMENT = 'END_ELEMENT';
    public const T_START_DEFINITION = 'START_DEFINITION';
    public const T_END_DEFINITION = 'END_DEFINITION';
    public const T_NAME = 'NAME';
    public const T_LOCAL = 'LOCAL';
    public const T_RAW = 'RAW';
    public const T_INCLUDE = 'INCLUDE';
    public const T_INCLUDE_TEMPLATE = 'INCLUDE_TEMPLATE';
    public const T_OPERATOR = 'OPERATOR';
    public const T_CLASS = 'CLASS';
    public const T_VALUE = 'VALUE';
    public const T_VALUE_BOOLEAN = 'VALUE_BOOLEAN';
    public const T_VALUE_NULL = 'VALUE_NULL';
    public const T_VALUE_INT = 'VALUE_INT';
    public const T_VALUE_ARRAY = 'VALUE_ARRAY';
    public const T_VALUE_STRING = 'VALUE_STRING';

    /**
     * @var array<string> $tokens
     */

    protected $tokens = [];

    /**
     * @var string $current_token
     */

    protected $current_token;

    /**
     * @var mixed $current_data
     */

    protected $current_data;

    /**
     * Construct a new TokenList.
     *
     * Constructs a new TokenList containing the set of tokens found in the
     * {@link $tokens} argument. Every token is added to the internal TokenList
     * using the {@link addToken()} method and is thus checked before inclusion.
     * Erroneous tokens will cause the {@link TokenListException} to be thrown.
     *
     *
     * @param array<string> $tokens Initial set of tokens for the TokenList
     * @return void
     * @throws TokenListException
     */

    public function __construct(array $tokens = [])
    {

        foreach ($tokens as $raw_token) {
            $this->addToken($raw_token);
        }
    }

    /**
     * Get a dynamic property.
     *
     * @param string $name
     * @return mixed
     */

    public function __get($name)
    {

        switch ($name) {
            case 'token':
                return $this->current_token;

            case 'token_data':
                return $this->current_data;

            case 'tokens':
                return $this->tokens;

            default:
                return null;
        }
    }

    /**
     * Add a token to the TokenList.
     *
     * The {@link $token} argument indicates the type of token and should be
     * one of the token constants (T_) defined in the {@link TokenList}
     * base class. The second argument {@link $token_data} should contain the
     * token data. The argument may be omitted if no token data is present.
     *
     * When provided, the {@link $token_data} should be implicitly convertable
     * to string. So, when using the T_VALUE_ARRAY token, its contents
     * should be serialised before being passed into this method.
     *
     * If the {@link $token} argument contains a space character and the
     * {@link $token_data} argument is set to null, the {@link $token}
     * argument is assumed to be a raw token. All data after the first space is
     * considered to be token data.
     *
     * @param string $token
     * @param string|null $token_data
     *
     * @return void
     * @throws TokenListException
     */

    public function addToken($token, $token_data = null)
    {

        // Split token

        if (strpos($token, ' ') !== false && is_null($token_data)) {
            list($token, $token_data) = explode(' ', $token, 2);
        }

        // Token data sanity checks

        switch ($token) {
            // Named tokens

            case self::T_REPLACE:
            case self::T_REPLACE_LOCAL:
            case self::T_NAME:
            case self::T_CLASS:
            case self::T_MODULE:
                if (
                    is_numeric($token_data) ||
                    !preg_match('/^[A-Z0-9_]+$/i', (string) $token_data)
                ) {
                    throw new TokenListException(
                        "Invalid token data provided for token \"$token\""
                    );
                }

                break;

            // Arrays

            case self::T_VALUE_ARRAY:
                if (!is_array(@unserialize((string) $token_data))) {
                    throw new TokenListException(
                        "Invalid token data provided for token \"$token\""
                    );
                }

                break;

            // Non-empty tokens

            case self::T_TEXT_HTML:
            case self::T_START_ELEMENT:
            case self::T_END_ELEMENT:
            case self::T_START_DEFINITION:
            case self::T_END_DEFINITION:
            case self::T_INCLUDE:
            case self::T_INCLUDE_TEMPLATE:
            case self::T_VALUE_STRING:
            case self::T_OPERATOR:
                if (trim((string) $token_data) == '') {
                    $token_data = null;
                }

                /*
                 * Empty boolean and integer tokens are allowed since they can
                 * contain a value of "0".
                 */

            case self::T_VALUE_BOOLEAN:
            case self::T_VALUE_INT:
                if (is_null($token_data)) {
                    throw new TokenListException(
                        "Token data not allowed to be empty for token \"$token\""
                    );
                }

                break;

            // Empty tokens

            case self::T_LOCAL:
            case self::T_RAW:
            case self::T_VALUE_NULL:
                if (trim((string) $token_data) == '') {
                    $token_data = null;
                }

                if (!is_null($token_data)) {
                    throw new TokenListException(
                        "Token data not allowed for token \"$token\""
                    );
                }

                break;

            // Unknown token

            default:
                throw new TokenListException(
                    "Unknown token \"$token\" encountered"
                );
        }

        $this->tokens[] =
            (is_null($token_data) ? $token : "$token $token_data");

        // Prime the TokenList

        if (count($this->tokens) == 1) {
            [$this->current_token, $this->current_data]
                = $this->parseRawToken((string) reset($this->tokens));
        }
    }

    /**
     * Forward the internal pointer to the next item in the TokenList.
     *
     * This method will set the "current" token to be the next item in the
     * internal TokenList. If no next item is present, false is
     * returned.
     *
     * A special token constant {@link TokenList::T_VALUE} is defined which
     * can be used to represent any other value token. This useful in cases
     * where the type of value does not matter. This special token constant is
     * never present in an actual TokenList.
     *
     * The optional {@link $expected_token} argument can be used to indicate
     * which token is expected. If the actual token does not match the expected
     * token an exception is thrown.
     *
     * @param string $expected_token
     *
     * @return boolean
     * @throws TokenListException
     * @see nextData()
     */

    public function nextToken($expected_token = null)
    {

        if (next($this->tokens) === false) {
            return false;
        }

        [$token, $token_data] =
            $this->parseRawToken(current($this->tokens));

        if (!is_string($token)) {
            throw new TokenListException('');
        }

        // Check expected token

        switch ($expected_token) {
            // Handle the special "T_VALUE" token

            case self::T_VALUE:
                if (strpos($token, (string) $expected_token) !== 0) {
                    prev($this->tokens);

                    throw new TokenListException(
                        "Expected next token to be
                        \"$expected_token\", \"$token\" encountered"
                    );
                }

                break;

            default:
                if (!is_null($expected_token) && $expected_token != $token) {
                    prev($this->tokens);

                    throw new TokenListException(
                        "Expected next token to be
                        \"$expected_token\", \"$token\" encountered"
                    );
                }
        }

        $this->current_token = $token;
        $this->current_data = $token_data;

        return true;
    }

    /**
     * Forward to the next item in the TokenList and return its token data.
     *
     * Similar to the {@link nextToken()} method, except for the fact that
     * this method will return the token data for the next token. If there is
     * no next token, null is returned.
     *
     * @param string $expected_token
     *
     * @throws TokenListException
     * @see nextToken()
     * @return mixed|null
     */

    public function nextData($expected_token = null)
    {

        if ($this->nextToken($expected_token)) {
            return $this->current_data;
        }

        return null;
    }

    /**
     * Rewind internal pointer to the previous item in the TokenList.
     *
     * @throws TokenListException
     * @return boolean
     */

    public function previousToken()
    {

        if (prev($this->tokens) === false) {
            return false;
        }

        [
            $token,
            $token_data
        ] = $this->parseRawToken(current($this->tokens));

        if (!is_string($token)) {
            throw new TokenListException('');
        }

        $this->current_token = $token;
        $this->current_data = $token_data;

        return true;
    }

    /**
     * Reset the TokenList to its initial state.
     *
     * @throws TokenListException
     * @return void
     */

    public function reset()
    {

        if (count($this->tokens) <= 1) {
            return;
        }

        [
            $this->current_token,
            $this->current_data
        ] = $this->parseRawToken((string) reset($this->tokens));
    }

    /**
     * Set the last token in the TokenList as active.
     *
     * @throws TokenListException
     * @return void
     */

    public function end()
    {

        if (count($this->tokens) == 1) {
            return;
        }

        [
            $this->current_token,
            $this->current_data
        ] = $this->parseRawToken((string) end($this->tokens));
    }

    /**
     * Parse raw token data into a token and its data.
     *
     * Returns an array containing two elements: The token and optionally its
     * data. If no data is present, the second array element will be set to
     * null.
     *
     * @param string $raw_token
     * @throws TokenListException
     * @return array<integer|string|boolean|array<integer|string>|null>
     */

    protected function parseRawToken($raw_token)
    {
        $token = $raw_token;
        $token_data = '';

        if (strpos($raw_token, ' ') !== false) {
            [$token, $token_data] = explode(' ', $raw_token, 2);
        }

        if (strpos($token, self::T_VALUE) === 0) {
            $token_data = $this->parseValueToken($token, $token_data);
        }

        return [$token, $token_data];
    }

    /**
     * Parse a value token.
     *
     * Used internally by {@link TokenList::parseRawToken()} to parse
     * value tokens. If fed with a proper value token it will return the token
     * value in the correct data type.
     *
     * @param string $token
     * @param string $token_data
     * @return integer|string|boolean|array<integer|string>|null
     * @throws TokenListException
     * @see TokenList::parseRawToken()
     */

    private function parseValueToken($token, $token_data)
    {
        switch ($token) {
            case self::T_VALUE_BOOLEAN:
                return (bool) $token_data;

            case self::T_VALUE_INT:
                return (int) $token_data;

            case self::T_VALUE_STRING:
                return (string) $token_data;

            case self::T_VALUE_ARRAY:
                $array = @unserialize($token_data);

                assert('is_array($array)');
                if (!is_array($array)) {
                    $array = [];
                }

                return $array;

            case self::T_VALUE_NULL:
                return null;

            default:
                throw new TokenListException(
                    "Expected next token to be a value token,
                    \"$token\" encountered"
                );
        }
    }

    /**
     * Collect a set of tokens into a new TokenList.
     *
     * This method creates a new TokenList and fills it with tokens,
     * starting at the current token of the old TokenList, until a token
     * matching {@link $end_token} and optionally {@link $end_data} is
     * encountered.
     * If no such token is found before the end of the TokenList, a
     * {@link TokenListException} gets thrown.
     *
     * @param string $end_token
     * @param mixed $end_data
     *
     * @return TokenList
     * @throws TokenListException
     */

    public function collectTokens($end_token, $end_data = null)
    {
        $collection = [];

        while ($this->nextToken()) {
            if ($this->current_token == $end_token) {
                if (is_null($end_data) || $end_data == $this->token_data) {
                    return new TokenList($collection);
                }
            }

            if ($this->current_data === null) {
                $collection[] = $this->current_token;
                continue;
            }

            $current_data = $this->current_data;

            if (is_array($this->current_data)) {
                $current_data = serialize($this->current_data);
            }

            $collection[] = "$this->current_token $current_data";
        }

        throw new TokenListException(
            "No matching end token found for token \"$end_token\""
        );
    }
}
