<?php
namespace PhpCsFixer\Fixer\Gt;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;

final class NoSpaceAfterPhpMethodFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAnyTokenKindsFound($this->getPhpMethodTokenKinds());
    }

    /**
     * {@inheritdoc}
     */
    public function fix(\SplFileInfo $file, Tokens $tokens)
    {
        $phpMethodTokens = $this->getPhpMethodTokenKinds();
        foreach ($tokens as $index => $token) {
            // looking for start brace
            if (!$token->equals('(')) {
                continue;
            }

            // last non-whitespace token
            $lastTokenIndex = $tokens->getPrevNonWhitespace($index);

            if (null === $lastTokenIndex) {
                continue;
            }

            // check for ternary operator
            $endParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index);
            $nextNonWhiteSpace = $tokens->getNextMeaningfulToken($endParenthesisIndex);
            if (
                null !== $nextNonWhiteSpace
                && $tokens[$nextNonWhiteSpace]->equals('?')
            ) {
                continue;
            }

            // check if it is a function call
            if ($tokens[$lastTokenIndex]->isGivenKind($phpMethodTokens)) {
                $this->fixFunctionCall($tokens, $index);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getDescription()
    {
        return 'When making a method or function call, there MUST NOT be a space between the method or function name and the opening parenthesis.';
    }

    public function getPriority()
    {
        // should be run after the ElseIfFixer, NoEmptyStatementFixer and NoUselessElseFixer
        return -27;
    }

    /**
     * Fixes whitespaces around braces of a function(y) call.
     *
     * @param Tokens $tokens tokens to handle
     * @param int    $index  index of token
     */
    private function fixFunctionCall(Tokens $tokens, $index)
    {
        // remove space before opening brace
        if ($tokens[$index - 1]->isWhitespace()) {
            $tokens[$index - 1]->clear();
        }
    }

    /**
     * Gets the token kinds which can work as function calls.
     *
     * @return int[] Token names
     */
    private function getPhpMethodTokenKinds()
    {
        static $tokens = null;

        if (null === $tokens) {
            $tokens = array(
                T_IF,
                T_ELSEIF,
                T_SWITCH,
                T_FOR,
                T_FOREACH,
                T_WHILE
            );
        }

        return $tokens;
    }

    /**
     * Gets the token kinds of actually language construction.
     *
     * @return int[]
     */
//    private function getLanguageConstructionTokenKinds()
//    {
//        static $languageConstructionTokens = array(
//            T_ECHO,
//            T_PRINT,
//            T_INCLUDE,
//            T_INCLUDE_ONCE,
//            T_REQUIRE,
//            T_REQUIRE_ONCE,
//        );
//
//        return $languageConstructionTokens;
//    }
}
