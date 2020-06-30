<?php declare(strict_types=1);

/**
 * Based on code released under a BSD-style license. For complete license text
 * see http://sgraastra.net/code/license/.
 */

namespace StudyPortals\Template\Parser;

use StudyPortals\Template\Node;
use StudyPortals\Template\NodeTree;
use StudyPortals\Template\TemplateException;
use StudyPortals\Template\Text;

/**
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */

class HandlebarsFactory extends Factory
{

    /**
     * Build a Template from a TokenList
     *
     * @param TokenList $TokenList
     * @param NodeTree $Parent
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
                    new Text('{{' . $TokenList->token_data . '}}', $Parent);

                    break;

                // Elements

                case TokenList::T_START_ELEMENT:
                    $element_id = $TokenList->token_data;

                    $element_type = $TokenList->nextData(
                        TokenList::T_START_DEFINITION
                    );
                    $element_name = $TokenList->nextData(TokenList::T_NAME);

                    switch ($element_type) {
                        // Condition

                        case 'condition':
                            $operator = $TokenList->nextData(
                                TokenList::T_OPERATOR
                            );
                            $value = $TokenList->nextData(TokenList::T_VALUE);

                            $helper = 'if';

                            switch ($operator) {
                                // Scalar

                                case '==':
                                    $helper = 'ifE';
                                    break;
                                case '!=':
                                    $helper = 'ifNE';
                                    break;
                                case '<':
                                    $helper = 'ifLT';
                                    break;
                                case '<=':
                                    $helper = 'ifLTE';
                                    break;
                                case '>':
                                    $helper = 'ifGT';
                                    break;
                                case '>=':
                                    $helper = 'ifGTE';
                                    break;

                                case 'in':
                                case '!in':
                                    throw new TemplateException(
                                        'Operators "in" and "!in" are not
                                        implemented for Handlebars'
                                    );

                                default:
                                    throw new TemplateException(
                                        "Unknown comparison operator
                                        {$operator} encountered"
                                    );
                            }

                            // Check for logical condition

                            if ($value === true) {
                                $value = 'true';
                            } elseif ($value === false) {
                                $value = 'false';
                            }

                            new Text(
                                '{{#' . "$helper $element_name '$value'" . '}}',
                                $Parent
                            );

                            // Build Element content

                            $TokenList->nextToken(TokenList::T_END_DEFINITION);

                            self::buildTemplate(
                                $TokenList->collectTokens(
                                    TokenList::T_END_ELEMENT,
                                    $element_id
                                ),
                                $Parent
                            );

                            new Text('{{/' . $helper . '}}', $Parent);

                            break;

                        // Repeater

                        case 'repeater':
                            new Text(
                                '{{#each ' . $element_name . '}}',
                                $Parent
                            );

                            $TokenList->nextToken(TokenList::T_END_DEFINITION);

                            self::buildTemplate(
                                $TokenList->collectTokens(
                                    TokenList::T_END_ELEMENT,
                                    $element_id
                                ),
                                $Parent
                            );

                            new Text('{{/each}}', $Parent);

                            break;

                        default:
                            throw new FactoryException(
                                "Invalid element \"$element_type\" encountered"
                            );
                    }

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
