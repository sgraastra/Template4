<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template\Parser;

use StudyPortals\Template\Condition;
use StudyPortals\Template\Module;
use StudyPortals\Template\Node;
use StudyPortals\Template\NodeTree;
use StudyPortals\Template\Repeater;
use StudyPortals\Template\Replace;
use StudyPortals\Template\Section;
use StudyPortals\Template\Template;
use StudyPortals\Template\TemplateException;
use StudyPortals\Template\Text;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class Factory
{

    protected const MARKER_START = '[{';
    protected const MARKER_END = '}]';

    /**
     * Parse raw template data into a well-structured TokenList.
     *
     * @param  string  $template_file
     * @return TokenList
     * @throws FactoryException, InvalidSyntaxException
     * @see    Factory::_parseTemplate()
     */

    public static function parseTemplate($template_file)
    {

        if (!file_exists($template_file) || !is_readable($template_file)) {
            throw new FactoryException(
                'Cannot parse template "' . basename($template_file) . '",
                file not found or access denied'
            );
        }

        $raw_data = (string) @file_get_contents($template_file);
        $raw_data = self::preParseTemplate($raw_data);

        return self::parseTemplateData($raw_data);
    }

    /**
     * Clean raw template data before starting the actual parse operation.
     *
     * This method is called at the start of {@link Factory::parseTemplate()}
     * and allows the {@link $raw_data} to be "pre-parsed" before being
     * transformed into a structured TokenList.
     *
     * @param  string $raw_data
     * @return string
     * @see    Factory::parseTemplate()
     */

    protected static function preParseTemplate(string $raw_data)
    {

        // Replace "comment-markers" with actual template-markers

        $comment_start = '/(?:<!--|\/\*)\s*' .
            preg_quote(self::MARKER_START, '/') . '/';
        $comment_end = '/' .
            preg_quote(self::MARKER_END, '/') . '\s*(?:-->|\*\/)/';

        $raw_data = (string) preg_replace(
            [
                $comment_start,
                $comment_end
            ],
            [
                self::MARKER_START,
                self::MARKER_END
            ],
            $raw_data
        );

        return $raw_data;
    }

    /**
     * Parse raw template data into a well-structured TokenList.
     *
     * Work-horse method for the Factory::parseTemplate() public method.
     *
     * @param string  $raw_data
     * @throws InvalidSyntaxException
     * @throws FactoryException
     * @return TokenList
     * @see    Factory::parseTemplate()
     */

    protected static function parseTemplateData($raw_data)
    {

        // Setup the parser-state

        $TemplateTokens = new TokenList();

        $in_definition = false;
        $definition_data = '';
        $element_data = '';

        $stack = [];

        $scope = [];
        $scope_depth = 0;

        $length = strlen($raw_data);

        if (strlen(self::MARKER_START) !== strlen(self::MARKER_END)) {
            throw new FactoryException('Failed to setup Template-parser:
                MARKER_START and MARKER_END should be of equal length');
        }
        $marker_length = strlen(self::MARKER_START);

        $line = 1;

        // Start parsing

        for ($i = 0; $i < $length; $i++) {
            // Line counter

            if (
                ($raw_data[$i] == "\r" && $raw_data[$i + 1] == "\n")
                || ($raw_data[$i] == "\n" && $raw_data[$i - 1] != "\r")
                || $raw_data[$i] == "\r"
            ) {
                $line++;
            }

            switch (substr($raw_data, $i, $marker_length)) {
             // Definition start

                case self::MARKER_START:
                    if ($in_definition) {
                        throw new InvalidSyntaxException(
                            'Unexpected start of definition'
                        );
                    }

                    self::addTextToken($TemplateTokens, $element_data);

                    $in_definition = true;
                    $element_data = '';

                    // Skip to end of tag marker

                    $i = $i + $marker_length - 1;

                    break;

             // Definition end

                case self::MARKER_END:
                    if (!$in_definition) {
                        throw new InvalidSyntaxException(
                            'Unexpected end of definition'
                        );
                    }

                    $definition = self::tokeniseString($definition_data);

                    try {
                        switch (strtolower($definition[0])) {
                        // Replace

                            case 'replace':
                            case 'var':
                                self::parseDefReplace(
                                    $TemplateTokens,
                                    $definition
                                );

                                break;

                            case 'module':
                                self::parseDefModule(
                                    $TemplateTokens,
                                    $definition,
                                    $stack
                                );

                                break;

                        // Include

                            case 'include':
                                self::parseDefInclude(
                                    $TemplateTokens,
                                    $definition
                                );

                                break;

                        // Elements with content

                            case 'condition':
                            case 'if':
                            case 'repeater':
                            case 'loop':
                            case 'section':
                                self::parseDefContentElements(
                                    $TemplateTokens,
                                    $definition,
                                    $stack,
                                    $scope,
                                    $scope_depth
                                );

                                break;

                        // Unknown element

                            default:
                                if (empty($element_type)) {
                                    $element_type = strtolower($definition[0]);
                                }

                                throw new InvalidSyntaxException(
                                    "Unknown element $element_type encountered"
                                );
                        }
                    } catch (InvalidSyntaxException | TokenListException $e) {
                        /*
                         * Add line-number (from the current template file being
                         * parsed) to the exceptions. There is a nicer way to do
                         * this (by reworking the exception-logic), but for now
                         * this wil have to do...
                         */

                        throw new InvalidSyntaxException(
                            $e->getMessage(),
                            $line
                        );
                    }

                    $in_definition = false;
                    $definition_data = '';

                    // Skip to end of tag marker

                    $i = $i + $marker_length - 1;

                    break;

             // Collect text

                default:
                    if ($in_definition) {
                        $definition_data .= $raw_data[$i];

                        break;
                    }

                    $element_data .= $raw_data[$i];
            }
        }

        // Collect final text characters

        self::addTextToken($TemplateTokens, $element_data);

        $TemplateTokens->reset();

        return $TemplateTokens;
    }

    /**
     * @param TokenList    $TemplateTokens
     * @param array<mixed> $definition
     * @return void
     * @throws TokenListException
     * @throws InvalidSyntaxException
     */

    protected static function parseDefReplace(
        TokenList $TemplateTokens,
        array $definition
    ) {
        $TemplateTokens->addToken(TokenList::T_REPLACE, $definition[1]);

        if (isset($definition[2]) && $definition[2] == 'raw') {
            $TemplateTokens->addToken(TokenList::T_RAW);
            return;
        }

        if (count($definition) > 2) {
            throw new InvalidSyntaxException(
                'Invalid number of arguments for replace-statement'
            );
        }
    }

    /**
     * @param TokenList    $TemplateTokens
     * @param array<mixed> $definition
     * @param array<mixed> $stack
     * @return void
     * @throws InvalidSyntaxException
     * @throws TokenListException
     */

    protected static function parseDefModule(
        TokenList $TemplateTokens,
        array $definition,
        array $stack
    ) {

        /*
         * Module cannot occur anywhere inside a Repeater; scan the
         * stack to ensure no repeaters are present.
         */

        foreach ($stack as $stack_element) {
            if ($stack_element[0] == 'repeater') {
                throw new InvalidSyntaxException(
                    'Module-element cannot occur inside a repeater'
                );
            }
        }

        $TemplateTokens->addToken(TokenList::T_MODULE, $definition[1]);
    }

    /**
     * @param TokenList    $TemplateTokens
     * @param array<mixed> $definition
     * @return void
     * @throws InvalidSyntaxException
     * @throws TokenListException
     */

    protected static function parseDefInclude(
        TokenList $TemplateTokens,
        array $definition
    ) {

        // Include Template

        if (strtolower($definition[1]) == 'template') {
            // Check parameters

            if (count($definition) != 5 && count($definition) != 3) {
                throw new InvalidSyntaxException(
                    'Invalid parameters for include-statement'
                );
            }

            $TemplateTokens->addToken(
                TokenList::T_INCLUDE_TEMPLATE,
                $definition[2]
            );

            // Named include

            if (count($definition) == 5) {
                if (strtolower($definition[3]) != 'as') {
                    throw new InvalidSyntaxException(
                        "Invalid syntax for include-statement, expected \"as\",
                        got \"$definition[3]\""
                    );
                }

                $TemplateTokens->addToken(TokenList::T_NAME, $definition[4]);
            }

            return;
        }

        // Include non-parseable File

        if (count($definition) != 2) {
            throw new InvalidSyntaxException(
                'Invalid parameters for include-statement'
            );
        }

        $TemplateTokens->addToken(TokenList::T_INCLUDE, $definition[1]);
    }

    /**
     * @param TokenList    $TemplateTokens
     * @param array<mixed> $definition
     * @param array<mixed> $stack
     * @param array<mixed> $scope
     * @param int          $scope_depth
     * @return void
     * @throws InvalidSyntaxException
     * @throws TokenListException
     */

    protected static function parseDefContentElements(
        TokenList $TemplateTokens,
        array $definition,
        array &$stack,
        array &$scope,
        int &$scope_depth
    ) {

        $element_type = strtolower($definition[0]);
        $element_name = $definition[1];

        // Process aliasses

        if ($element_type == 'if') {
            $element_type = 'condition';
        }
        if ($element_type == 'loop') {
            $element_type = 'repeater';
        }

        // Closing statement

        if (
            isset($definition[2])
            && strtolower($definition[2]) == 'end'
            && count($definition) == 3
        ) {
            // Read stack

            [$expected_type, $expected_name, $expected_uid] = array_pop($stack);

            // Type match

            if ($expected_type != $element_type) {
                throw new InvalidSyntaxException(
                    "Expected $expected_type got $element_type"
                );
            } elseif ($expected_name != $element_name) { // Name match
                throw new InvalidSyntaxException(
                    "Expected $element_type with name \"$expected_name\",
                    got \"$element_name\""
                );
            }

            $TemplateTokens->addToken(TokenList::T_END_ELEMENT, $expected_uid);

            // Reset scope (for Template and children only)

            if ($element_type != 'condition') {
                unset($scope[$scope_depth]);

                --$scope_depth;
            }

            return;
        }

        // Opening statement

        $element_uid = md5(uniqid($element_type . $element_name, true));

        // Check scope (for Template and children only)

        if ($element_type != 'condition') {
            ++$scope_depth;

            // Duplicate element detected

            if (
                isset($scope[$scope_depth])
                && is_array($scope[$scope_depth])
                && in_array($element_name, $scope[$scope_depth])
            ) {
                throw new InvalidSyntaxException(
                    "Duplicate element $element_name encountered in scope"
                );
            }

            $scope[$scope_depth][] = $element_name;
        }

        // Update stack

        $stack[] = [
            $element_type,
            $element_name,
            $element_uid,
        ];

        $TemplateTokens->addToken(TokenList::T_START_ELEMENT, $element_uid);
        $TemplateTokens->addToken(TokenList::T_START_DEFINITION, $element_type);
        $TemplateTokens->addToken(TokenList::T_NAME, $element_name);

        // Condition

        if ($element_type == 'condition') {
            // Operator

            if (!isset($definition[2])) {
                throw new InvalidSyntaxException(
                    'Invalid parameter count for condition-statement'
                );
            }

            try {
                self::addOperatorToken($definition[2], $TemplateTokens);
            } catch (FactoryException $e) {
                throw new InvalidSyntaxException($e->getMessage());
            }

            // Comparison value

            $TemplateTokens->end();

            if ($TemplateTokens->token !== TokenList::T_OPERATOR) {
                throw new FactoryException(
                    'Invalid token-list: expected "' . TokenList::T_OPERATOR .
                    '", got "' . $TemplateTokens->token . '"'
                );
            }

            try {
                switch ($TemplateTokens->token_data) {
                    case 'in':
                    case '!in':
                        // Sets of values
                        if (count($definition) < 3) {
                            throw new InvalidSyntaxException(
                                'Invalid parameter count for
                                condition-statement (set of values)'
                            );
                        }
                        /** @var array<string> $definition */
                        self::addValueToken(
                            array_slice($definition, 3),
                            $TemplateTokens
                        );

                        break;

                    default:
                        // Scalar values
                        if (count($definition) != 4) {
                            throw new InvalidSyntaxException(
                                'Invalid parameter count for
                                condition-statement (scalar value)'
                            );
                        }

                        self::addValueToken($definition[3], $TemplateTokens);
                }
            } catch (FactoryException $e) {
                throw new InvalidSyntaxException($e->getMessage());
            }
        }

        $TemplateTokens->addToken(TokenList::T_END_DEFINITION, $element_type);
    }

    /**
     * Split a string into tokens.
     *
     * Tokenises a string using white-space characters. Both single- and
     * double-quote characters can be used to start a quoted section. A
     * backslash can be used to escape a single- or double-quote character.
     *
     * @param  string $string
     * @return array<string>
     */

    protected static function tokeniseString($string)
    {

        $token_list = [];

        $in_quote = false;
        $length = strlen($string);
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            switch ($string[$i]) {
                // Token separators

                case ' ':
                case "\r":
                case "\n":
                case "\t":
                    if ($in_quote) {
                        $token .= $string[$i];
                    } elseif ($token != '') {
                        $token_list[] = $token;
                        $token = '';
                    }

                    break;

                // Quote separators

                case '\'':
                case '"':
                    if (!$in_quote) {
                        $in_quote = $string[$i];
                        break;
                    }

                    if ($in_quote == $string[$i]) {
                        if ($string[$i - 1] == '\\') {
                            break;
                        }

                        $in_quote = false;

                        if ($token != '') {
                            $token_list[] = $token;
                            $token = '';
                        }

                        break;
                    }

                    $token .= $string[$i];

                    break;

                default:
                    $token .= $string[$i];
            }
        }

        if ($token != '') {
            $token_list[] = $token;
        }

        return $token_list;
    }

    /**
     * Add a text (TokenList::T_TEXT_HTML) token to the TokenList.
     *
     * @param  string    $text
     * @param  TokenList $TemplateTokens
     * @throws TokenListException
     * @return void
     * @see    Factory::parseTemplate()
     */

    private static function addTextToken(TokenList $TemplateTokens, $text)
    {
        if ($text == '') {
            return;
        }

        if (trim($text) == '') {
            return;
        }

        // Condense all whitespace characters into a single space
        $text = preg_replace('/[\s]+/', ' ', $text);

        $TemplateTokens->addToken(TokenList::T_TEXT_HTML, $text);
    }

    /**
     * Add an operator ({@link TokenList::T_OPERATOR}) token to the TokenList.
     *
     * This method is used internally by Factory::parseTemplate() to convert
     * a multitude of operator tokens into the limited set of operators
     * accepted by the Condition object.
     *
     * @param  string    $operator
     * @param  TokenList $TemplateTokens
     * @return void
     * @throws FactoryException
     * @see    Factory::parseTemplate()
     */

    private static function addOperatorToken(
        $operator,
        TokenList $TemplateTokens
    ) {
        switch (strtolower($operator)) {
         // Equals

            case 'is':
            case '=':
            case '==':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '==');

                return;

         // Not equals

            case 'not':
            case '!is':
            case '!=':
            case '<>':      // XXX: deprecated
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '!=');

                return;

         // Sets

            case 'in':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, 'in');

                return;

            case '!in':
            case 'notin':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '!in');

                return;

         // Greater

            case 'greater': // XXX: deprecated
            case 'gt':      // XXX: deprecated
            case '>':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '>');

                return;

            case 'gte':     // XXX: deprecated
            case '>=':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '>=');

                return;

         // Smaller

            case 'smaller': // XXX: deprecated
            case 'lt':      // XXX: deprecated
            case '<':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '<');

                return;

            case 'lte':     // XXX: deprecated
            case '<=':
                $TemplateTokens->addToken(TokenList::T_OPERATOR, '<=');

                return;

         // Invalid Operator

            default:
                throw new FactoryException(
                    "Invalid condition operator \"$operator\" specified"
                );
        }
    }

    /**
     * Add a value (TokenList::T_VALUE_*) token to the TokenList.
     *
     * This method is used internally by Factory::parseTemplate() to properly
     * parse values and add them to the TokenList.
     *
     * @param  string|integer|array<string|integer> $value
     * @param  TokenList                            $TemplateTokens
     * @return void
     * @throws FactoryException
     * @see    Factory::parseTemplate()
     */

    private static function addValueToken($value, TokenList $TemplateTokens)
    {

        if (is_array($value)) {
            $TemplateTokens->addToken(
                TokenList::T_VALUE_ARRAY,
                serialize($value)
            );

            return;
        }

        if (is_numeric($value)) {
            $TemplateTokens->addToken(TokenList::T_VALUE_INT, (string) $value);

            return;
        }

        switch (strtolower($value)) {
            case 'true':
                $TemplateTokens->addToken(TokenList::T_VALUE_BOOLEAN, '1');

                return;

            case 'false':
                $TemplateTokens->addToken(TokenList::T_VALUE_BOOLEAN, '0');

                return;

            case 'null':
                $TemplateTokens->addToken(TokenList::T_VALUE_NULL);

                return;

            default:
                $TemplateTokens->addToken(
                    TokenList::T_VALUE_STRING,
                    (string) $value
                );

                return;
        }
    }

    /**
     * Build a Template from a TokenList.
     *
     * Builds a Template object from a provided TokenList. When called
     * externally, the {@link $Parent} will in most cases be an empty Template4
     * object. Any Node object will do, so it is also possible to parse a
     * TokenList "into" an existing Template.
     *
     * See {@link Factory::parseTemplate()} for details on the optional
     * {@link $html} argument. In the context of buildTemplate() this argument
     * is only used when external entities are included (i.e. "include"
     * statements are encountered).
     *
     * @param TokenList $TokenList
     * @param NodeTree  $Parent
     * @throws FactoryException
     * @throws TemplateException
     * @throws TokenListException
     * @return Node
     */

    public static function buildTemplate(TokenList $TokenList, NodeTree $Parent)
    {
        // If the TokenList is empty we can directly return the Parent

        if (count($TokenList->tokens) == 0) {
            return $Parent;
        }

        do {
            switch ($TokenList->token) {
                // Text

                case TokenList::T_TEXT_HTML:
                    new Text($TokenList->token_data, $Parent);

                    break;

                // Replace

                case TokenList::T_REPLACE:
                    $replace = $TokenList->token_data;

                    // "raw"-replace

                    $TokenList->nextToken();

                    if ($TokenList->token === TokenList::T_RAW) {
                        new Replace($Parent, $replace, true);
                        break;
                    }

                    $TokenList->previousToken();

                    // Regular-replace

                    new Replace($Parent, $replace);

                    break;

                case TokenList::T_MODULE:
                    new Module($TokenList->token_data, $Parent);

                    break;

                // Includes

                case TokenList::T_INCLUDE:
                case TokenList::T_INCLUDE_TEMPLATE:
                    $RootNode = $Parent->getRoot();

                    if (!($RootNode instanceof Template)) {
                        throw new FactoryException(
                            'Expecting root-node to be instance of Template,
                            got ' . get_class($RootNode)
                        );
                    }

                    // Get the file name of the base template file

                    $base_dir = dirname($RootNode->getFileName());
                    $file_name = "$base_dir/{$TokenList->token_data}";

                    $TemplateTokens = null;

                    // Try an include path relative to the base template file

                    if (!file_exists($file_name) || !is_readable($file_name)) {
                        // Fall-back to path relative to the PHP-file

                        $file_name = $TokenList->token_data;

                        if (
                            !file_exists($file_name) ||
                            !is_readable($file_name)
                        ) {
                            throw new FactoryException(
                                'Error while including "' . basename($file_name)
                                . '", file not found or access denied'
                            );
                        }
                    }

                    // Include non-parseable file

                    if ($TokenList->token == TokenList::T_INCLUDE) {
                        $file_contents =
                            (string) @file_get_contents($file_name);

                        if (trim($file_contents) != '') {
                            new Text($file_contents, $Parent);
                        }

                        unset($file_contents);
                    } elseif (
                        $TokenList->token == TokenList::T_INCLUDE_TEMPLATE
                    ) {
                        // Include template

                        $TemplateTokens = self::parseTemplate($file_name);

                        // Attempt to use name provided in TokenList

                        try {
                            $node_name = $TokenList->nextData(
                                TokenList::T_NAME
                            );
                        } catch (TokenListException $e) {
                            $node_name = null;
                        }

                        // Fallback to filename

                        if ($node_name === null) {
                            $node_name = basename($TokenList->token_data);
                            $node_name = substr(
                                $node_name,
                                0,
                                (int) strrpos($node_name, '.')
                            );
                            $node_name = preg_replace(
                                '/[^A-Z0-9]+/i',
                                '',
                                $node_name
                            );
                        }

                        self::buildTemplate(
                            $TemplateTokens,
                            new Section(
                                $node_name,
                                $Parent
                            )
                        );

                        unset($TemplateTokens);
                    }

                    break;

                // Elements

                case TokenList::T_START_ELEMENT:
                    $element_id = $TokenList->token_data;

                    $element_type = $TokenList->nextData(
                        TokenList::T_START_DEFINITION
                    );
                    $element_name = $TokenList->nextData(TokenList::T_NAME);

                    switch ($element_type) {
                        // Section

                        case 'section':
                            $Child = new Section($element_name, $Parent);

                            break;

                        case 'module':
                            $Child = new Module($element_name, $Parent);

                            break;

                        // Condition

                        case 'condition':
                            $Child = new Condition(
                                $Parent,
                                $element_name,
                                $TokenList->nextData(TokenList::T_OPERATOR),
                                $TokenList->nextData(TokenList::T_VALUE)
                            );

                            break;

                        // Repeater

                        case 'repeater':
                            $Child = new Repeater($element_name, $Parent);

                            break;

                        default:
                            throw new FactoryException(
                                "Invalid element \"$element_type\" encountered"
                            );
                    }

                    // Build Element content

                    $TokenList->nextToken(TokenList::T_END_DEFINITION);

                    self::buildTemplate(
                        $TokenList->collectTokens(
                            TokenList::T_END_ELEMENT,
                            $element_id
                        ),
                        $Child
                    );

                    unset($Child);

                    break;

                // Unexpected token

                default:
                    throw new FactoryException(
                        "Error while building Template,
                        unexpected \"$TokenList->token\" encountered"
                    );
            }
        } while ($TokenList->nextToken());

        return $Parent;
    }
}
